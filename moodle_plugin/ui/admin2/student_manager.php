<?php
/**
 * Student Manager — Admin Dashboard v2
 *
 * Mirrors the student-facing dashboard (Profile, Programs, Classes,
 * Requests, Student Card) for a chosen student.  Adds:
 *   • Admin search (name / email / student_id / moodle_user_id)
 *   • Manual Sync panel
 *   • Request Windows management
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/nav.php');

admin_externalpage_setup('local_moodle_zoho_sync_students');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

// ── GET params ────────────────────────────────────────────────────────────────
$search_q   = optional_param('q',   '',  PARAM_TEXT);
$student_id = optional_param('sid', 0,   PARAM_INT);
$active_tab = optional_param('tab', 'profile', PARAM_ALPHA);

// ── Search ────────────────────────────────────────────────────────────────────
$search_results = [];
$search_performed = false;
if ($search_q !== '') {
    $search_performed = true;
    try {
        $like = '%' . $DB->sql_like_escape($search_q) . '%';
        $search_results = $DB->get_records_sql(
            "SELECT s.id, s.first_name, s.last_name, s.email, s.academic_email,
                    s.student_id, s.zoho_student_id, s.moodle_user_id, s.status,
                    s.phone_number
             FROM {local_mzi_students} s
             WHERE " . $DB->sql_like('s.first_name',    ':q1',  false) . "
                OR " . $DB->sql_like('s.last_name',     ':q2',  false) . "
                OR " . $DB->sql_like('s.email',         ':q3',  false) . "
                OR " . $DB->sql_like('s.academic_email',':q4',  false) . "
                OR " . $DB->sql_like('s.student_id',    ':q5',  false) . "
                OR s.moodle_user_id = :exact
             ORDER BY s.first_name, s.last_name
             LIMIT 50",
            ['q1'=>$like,'q2'=>$like,'q3'=>$like,'q4'=>$like,'q5'=>$like,
             'exact' => (is_numeric($search_q) ? (int)$search_q : 0)]
        );
    } catch (Throwable $e) {
        $search_results = [];
    }
}

// ── Load selected student ─────────────────────────────────────────────────────
$student      = null;
$moodle_user  = null;
$registrations = [];
$enrollments   = [];
$requests_list = [];

if ($student_id > 0) {
    try {
        $student = $DB->get_record('local_mzi_students', ['id' => $student_id]);
    } catch (Throwable $e) {}

    if ($student) {
        // Moodle user
        try {
            $moodle_user = $DB->get_record('user', ['id' => $student->moodle_user_id]);
        } catch (Throwable $e) {}

        // ── Registrations + installments + payments (same as programs.php) ─────
        try {
            $regs = $DB->get_records_sql(
                "SELECT * FROM {local_mzi_registrations}
                 WHERE student_id = ? ORDER BY registration_date DESC",
                [$student->id]
            );
            foreach ($regs as $reg) {
                // installments
                try {
                    $reg->installments = $DB->get_records_sql(
                        "SELECT * FROM {local_mzi_installments}
                         WHERE registration_id = ? ORDER BY due_date ASC",
                        [$reg->id]
                    );
                } catch (Throwable $e) { $reg->installments = []; }

                // payments
                try {
                    $reg->payments = $DB->get_records_sql(
                        "SELECT * FROM {local_mzi_payments}
                         WHERE registration_id = ? ORDER BY payment_date DESC",
                        [$reg->id]
                    );
                } catch (Throwable $e) { $reg->payments = []; }

                // compute totals
                $reg->effective_total     = 0;
                $reg->computed_paid       = 0;
                $reg->inst_total_count    = count((array)$reg->installments);
                $reg->inst_overdue_count  = 0;
                $reg->inst_overdue_amount = 0;

                foreach ((array)$reg->installments as $inst) {
                    $reg->effective_total += (float)($inst->amount ?? 0);
                    if (strtolower($inst->status ?? '') === 'overdue') {
                        $reg->inst_overdue_count++;
                        $reg->inst_overdue_amount += (float)($inst->amount ?? 0);
                    }
                }
                foreach ((array)$reg->payments as $pay) {
                    if (!in_array(strtolower($pay->payment_status ?? ''), ['voided','cancelled'])) {
                        $reg->computed_paid += (float)($pay->amount ?? 0);
                    }
                }
                $reg->computed_balance = max(0, $reg->effective_total - $reg->computed_paid);

                $registrations[$reg->id] = $reg;
            }
        } catch (Throwable $e) {}

        // ── Enrollments + classes ─────────────────────────────────────────────
        try {
            $enrollments = $DB->get_records_sql(
                "SELECT e.id, e.class_id, e.enrolled_program, e.enrollment_status,
                        c.zoho_class_id, c.class_name, c.class_short_name,
                        c.teacher_name, c.start_date, c.end_date, c.class_status,
                        c.moodle_class_id, c.unit_name, c.program_level
                 FROM {local_mzi_enrollments} e
                 JOIN {local_mzi_classes} c ON c.id = e.class_id
                 WHERE e.student_id = ?
                 ORDER BY c.start_date DESC",
                [$student->id]
            );
        } catch (Throwable $e) { $enrollments = []; }

        // ── Grades (bulk load) ────────────────────────────────────────────────
        $grades_map = [];
        if ($enrollments) {
            try {
                $enroll_ids = array_keys($enrollments);
                [$in_sql, $in_params] = $DB->get_in_or_equal($enroll_ids);
                $grade_rows = $DB->get_records_sql(
                    "SELECT * FROM {local_mzi_grades} WHERE enrollment_id {$in_sql}",
                    $in_params
                );
                foreach ($grade_rows as $g) {
                    $grades_map[$g->enrollment_id][] = $g;
                }
            } catch (Throwable $e) {}
        }

        // ── Requests ─────────────────────────────────────────────────────────
        try {
            $requests_list = $DB->get_records_sql(
                "SELECT * FROM {local_mzi_requests}
                 WHERE student_id = ? ORDER BY created_at DESC",
                [$student->id]
            );
        } catch (Throwable $e) { $requests_list = []; }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function smgr_fmt_date(?string $v): string {
    if ($v === null || $v === '') return '—';
    if (is_numeric($v)) {
        $ts = strlen($v) >= 13 ? intval($v) / 1000 : intval($v);
        return date('d M Y', (int)$ts);
    }
    $ts = strtotime($v);
    return $ts ? date('d M Y', $ts) : $v;
}

function smgr_field(string $label, ?string $val, bool $mono = false): void {
    $v   = ($val !== null && $val !== '') ? htmlspecialchars($val, ENT_QUOTES) : '<span style="color:#bbb">—</span>';
    $cls = $mono ? ' style="font-family:monospace"' : '';
    echo "<div class=\"smgr-field\"><div class=\"smgr-field-lbl\">{$label}</div>"
       . "<div class=\"smgr-field-val\"{$cls}>{$v}</div></div>";
}

function smgr_next_installment(array $insts): ?object {
    $now = time();
    $upcoming = [];
    foreach ($insts as $inst) {
        $st = strtolower($inst->status ?? '');
        if (in_array($st, ['paid','voided','cancelled'])) continue;
        $ts = strtotime($inst->due_date ?? '');
        if ($ts) $upcoming[] = $inst;
    }
    usort($upcoming, fn($a,$b) => strtotime($a->due_date) <=> strtotime($b->due_date));
    return $upcoming[0] ?? null;
}

function smgr_win_status(string $type): string {
    // Uses config-based windows set in Settings → Request Windows
    $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($type));
    $now  = time();
    for ($n = 1; $n <= 4; $n++) {
        $date  = get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_date") ?: '';
        $weeks = (int)(get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_weeks") ?: 0);
        if (!$date || !$weeks) continue;
        $start = strtotime($date);
        if ($start === false) continue;
        $end = $start + $weeks * 7 * 86400;
        if ($now >= $start && $now < $end)
            return '<span class="smgr-win-badge open">Open</span>';
    }
    return '<span class="smgr-win-badge closed">Closed</span>';
}

// photo URL helper
$photo_base_url = (new moodle_url('/local/moodle_zoho_sync/ui/student/serve_photo.php'))->out(false);
$student_card_base = (new moodle_url('/local/moodle_zoho_sync/ui/student/student_card.php'))->out(false);

// ── Backend URL for manual sync ───────────────────────────────────────────────
$backend_url = get_config('local_moodle_zoho_sync', 'backend_url') ?: '';
$backend_url = rtrim($backend_url, '/');
$backend_token = get_config('local_moodle_zoho_sync', 'backend_api_token') ?: '';

// ── Page output ───────────────────────────────────────────────────────────────
echo $OUTPUT->header();
mzi2_nav_css();
mzi2_nav('students');
mzi2_breadcrumb('Student Manager');
?>

<style>
/* ══ Student Manager Styles ══════════════════════════════════════════════════ */
.smgr-search-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.smgr-search-bar input[type=text]{flex:1;min-width:240px;padding:10px 14px;border:1px solid #ccc;border-radius:8px;font-size:14px;outline:none;transition:border .2s}
.smgr-search-bar input[type=text]:focus{border-color:#0066cc;box-shadow:0 0 0 3px rgba(0,102,204,.1)}
.smgr-search-bar button{padding:10px 22px;background:#0066cc;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer}
.smgr-search-bar button:hover{background:#004fa3}

.smgr-results-list{margin-top:16px;display:flex;flex-direction:column;gap:6px}
.smgr-result-item{display:flex;align-items:center;gap:14px;padding:12px 16px;border:1px solid #e0e0e0;border-radius:10px;background:#fafafa;text-decoration:none;color:inherit;transition:background .15s}
.smgr-result-item:hover{background:#f0f6ff;border-color:#0066cc}
.smgr-result-photo{width:40px;height:40px;border-radius:50%;object-fit:cover;background:#ddd}
.smgr-result-name{font-weight:700;color:#1a1a2e;font-size:14px}
.smgr-result-meta{font-size:12px;color:#777;margin-top:2px}
.smgr-result-badge{margin-left:auto;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;white-space:nowrap}
.smgr-result-badge.active{background:#d4edda;color:#155724}
.smgr-result-badge.other{background:#e9ecef;color:#495057}

/* ── Student header ── */
.smgr-student-head{background:linear-gradient(135deg,#00305a 0%,#001d38 100%);color:#fff;border-radius:14px;padding:24px 28px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;margin-bottom:0}
.smgr-student-photo{width:72px;height:72px;border-radius:50%;border:3px solid rgba(255,255,255,.35);object-fit:cover;flex-shrink:0;background:#1d3d5c}
.smgr-student-info h2{margin:0 0 6px;font-size:22px;font-weight:800}
.smgr-student-info p{margin:0;font-size:13px;opacity:.75;display:flex;gap:14px;flex-wrap:wrap}
.smgr-student-info p span{display:inline-flex;align-items:center;gap:4px}
.smgr-sts-badge{padding:4px 14px;border-radius:20px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-top:8px;display:inline-block}
.smgr-sts-badge.active{background:rgba(56,239,125,.3);color:#b7ffd8;border:1px solid rgba(56,239,125,.4)}
.smgr-sts-badge.other{background:rgba(255,255,255,.15);color:rgba(255,255,255,.85);border:1px solid rgba(255,255,255,.25)}

/* ── Tabs ── */
.smgr-tabs{display:flex;gap:0;border-bottom:2px solid #e0e0e0;margin-bottom:0;flex-wrap:wrap}
.smgr-tabs a{padding:11px 18px;text-decoration:none;color:#666;font-size:13px;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .18s;white-space:nowrap}
.smgr-tabs a:hover{color:#0066cc;background:#f5f9ff}
.smgr-tabs a.active{color:#0066cc;border-bottom-color:#0066cc;background:#fff}
.smgr-tabs a i{margin-right:5px}

.smgr-tab-pane{display:none;padding:24px 0 8px}
.smgr-tab-pane.active{display:block}

/* ── Profile grid ── */
.smgr-fields-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px 24px;margin-bottom:22px}
.smgr-field .smgr-field-lbl{font-size:10.5px;color:#8a93a8;font-weight:700;text-transform:uppercase;letter-spacing:.35px;margin-bottom:4px}
.smgr-field .smgr-field-val{font-size:14px;color:#1a1a2e;font-weight:500}

/* ── Registration card ── */
.smgr-reg-card{background:#fff;border-radius:12px;box-shadow:0 2px 14px rgba(0,0,0,.08);margin-bottom:24px;overflow:hidden}
.smgr-reg-head{background:linear-gradient(135deg,#00305a,#001d38);color:#fff;padding:18px 22px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
.smgr-reg-head h4{margin:0 0 4px;font-size:17px;font-weight:800}
.smgr-reg-head p{margin:0;font-size:12px;opacity:.75}
.smgr-badge{padding:4px 14px;border-radius:20px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px}
.smgr-badge.active{background:rgba(56,239,125,.3);color:#b7ffd8;border:1px solid rgba(56,239,125,.4)}
.smgr-badge.completed{background:rgba(79,172,254,.3);color:#c6e8ff;border:1px solid rgba(79,172,254,.4)}
.smgr-badge.other{background:rgba(255,255,255,.15);color:rgba(255,255,255,.85);border:1px solid rgba(255,255,255,.25)}
.smgr-fin-strip{display:flex;flex-wrap:wrap;background:#f4f6fb;border-bottom:1px solid #e3e8f0}
.smgr-fin-cell{flex:1;min-width:120px;padding:14px 20px;border-right:1px solid #e3e8f0}
.smgr-fin-cell:last-child{border-right:none}
.smgr-fin-lbl{font-size:10px;color:#7a8499;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px}
.smgr-fin-val{font-size:20px;font-weight:800;color:#111}
.smgr-fin-val.green{color:#1a7a3c}
.smgr-fin-val.red{color:#b00020}
.smgr-fin-val.navy{color:#00305a}
.smgr-prog-wrap{padding:8px 20px 10px;background:#f4f6fb;border-bottom:1px solid #e3e8f0}
.smgr-prog-track{height:8px;background:#dce1ec;border-radius:5px;overflow:hidden;margin-top:4px}
.smgr-prog-fill{height:100%;border-radius:5px;transition:width .7s ease}
.smgr-reg-body{padding:18px 22px}

/* ── Installments/Payments table ── */
.smgr-tbl{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
.smgr-tbl th{background:#f8f9fc;color:#5a6478;font-weight:700;text-align:left;padding:8px 12px;border-bottom:2px solid #e3e8f0;font-size:10.5px;text-transform:uppercase;letter-spacing:.4px}
.smgr-tbl td{padding:8px 12px;border-bottom:1px solid #f1f3f8;color:#2a2d3a;vertical-align:middle}
.smgr-tbl tr:last-child td{border-bottom:none}
.smgr-tbl tr:hover td{background:#f5f8ff}
.smgr-tbl tr.row-paid td{background:#f0faf3}
.smgr-tbl tr.row-overdue td{background:#fff8f5}
.smgr-pill{display:inline-block;padding:2px 9px;border-radius:10px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.smgr-pill.paid,.smgr-pill.confirmed{background:#d1f0da;color:#0f6628}
.smgr-pill.pending{background:#fff5cc;color:#7a5800}
.smgr-pill.overdue{background:#ffe0d6;color:#9b2200}
.smgr-pill.voided,.smgr-pill.cancelled{background:#f3d6da;color:#7a1020}
.smgr-sec-head{display:flex;justify-content:space-between;align-items:center;cursor:pointer;padding:10px 14px;background:#f0f3f8;border-radius:8px;margin-bottom:0;user-select:none}
.smgr-sec-head h5{margin:0;font-size:13px;font-weight:700;color:#00305a}
.smgr-acc-body{overflow:hidden}

/* ── Enrollment cards ── */
.smgr-enroll-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}
.smgr-enroll-card{background:#fff;border:1px solid #e8ecf2;border-radius:12px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.06)}
.smgr-enroll-card-head{background:#f0f3f8;padding:12px 16px;border-bottom:1px solid #e8ecf2}
.smgr-enroll-card-head h5{margin:0;font-size:13.5px;font-weight:700;color:#00305a}
.smgr-enroll-card-head p{margin:3px 0 0;font-size:11.5px;color:#778}
.smgr-enroll-card-body{padding:12px 16px}
.smgr-enroll-meta{display:flex;flex-wrap:wrap;gap:6px 18px;font-size:12px;color:#555;margin-bottom:10px}
.smgr-grade-badge{display:inline-block;padding:3px 10px;border-radius:8px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.3px}
.smgr-grade-badge.P{background:#d4edda;color:#155724}
.smgr-grade-badge.M{background:#d1ecf1;color:#0c5460}
.smgr-grade-badge.D{background:#cce5ff;color:#004085}
.smgr-grade-badge.F,.smgr-grade-badge.R,.smgr-grade-badge.RR{background:#f8d7da;color:#721c24}
.smgr-grade-badge.other{background:#e9ecef;color:#495057}

/* ── Requests table ── */
.smgr-rtbl{width:100%;border-collapse:collapse;font-size:13px}
.smgr-rtbl th{background:#f8f9fa;color:#555;font-weight:600;text-align:left;padding:9px 12px;border-bottom:2px solid #e0e0e0;font-size:11px;text-transform:uppercase;letter-spacing:.4px}
.smgr-rtbl td{padding:9px 12px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
.smgr-rtbl tr:last-child td{border-bottom:none}
.smgr-rtbl tr:hover td{background:#f9fbff}
.smgr-rq-status{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;text-transform:uppercase}
.smgr-rq-status.submitted{background:#cce5ff;color:#004085}
.smgr-rq-status.under_review{background:#fff3cd;color:#856404}
.smgr-rq-status.approved{background:#d4edda;color:#155724}
.smgr-rq-status.rejected{background:#f8d7da;color:#721c24}
.smgr-rq-status.other{background:#e9ecef;color:#495057}

/* ── Request Windows ── */
.smgr-win-table{width:100%;border-collapse:collapse;font-size:13px}
.smgr-win-table th{background:#f0f3f8;color:#444;font-weight:700;text-align:left;padding:10px 14px;border-bottom:2px solid #dee2ea;font-size:11px;text-transform:uppercase;letter-spacing:.4px}
.smgr-win-table td{padding:12px 14px;border-bottom:1px solid #f0f2f6;vertical-align:middle}
.smgr-win-table tr:last-child td{border-bottom:none}
.smgr-win-badge{display:inline-block;padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700}
.smgr-win-badge.open{background:#d4edda;color:#155724}
.smgr-win-badge.closed{background:#f8d7da;color:#721c24}
.smgr-win-badge.pending{background:#fff3cd;color:#856404}
.smgr-win-input{width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px}
.smgr-win-input:focus{border-color:#0066cc;outline:none;box-shadow:0 0 0 2px rgba(0,102,204,.1)}

/* ── Manual Sync panel ── */
.smgr-sync-panel{background:#fff;border:1px solid #e0e8f5;border-radius:12px;padding:20px 24px}
.smgr-sync-panel h4{margin:0 0 16px;font-size:15px;font-weight:700;color:#1a1a2e}
.smgr-sync-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.smgr-sync-row input{flex:1;min-width:200px;padding:9px 12px;border:1px solid #ccc;border-radius:8px;font-size:14px}
.smgr-sync-row input:focus{border-color:#0066cc;outline:none}
.smgr-sync-row button{padding:9px 22px;background:#0066cc;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer}
.smgr-sync-row button:hover{background:#004fa3}
.smgr-sync-row button:disabled{background:#90b8e0;cursor:not-allowed}
#smgr_sync_result{margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px;display:none}
#smgr_sync_result.ok{background:#d4edda;color:#155724}
#smgr_sync_result.err{background:#f8d7da;color:#721c24}

/* ── Utils ── */
.smgr-empty{text-align:center;padding:32px;color:#aab2c8;font-size:13px;border:2px dashed #e5e8f0;border-radius:10px}
.smgr-empty i{display:block;font-size:28px;margin-bottom:10px;color:#d0d7e8}
.smgr-card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:20px 24px;margin-bottom:22px}
.smgr-section-title{font-size:15px;font-weight:800;color:#00305a;margin:0 0 16px;display:flex;align-items:center;gap:8px}
.smgr-btn{padding:8px 18px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid transparent;transition:all .18s}
.smgr-btn-primary{background:#0066cc;color:#fff;border-color:#0066cc}
.smgr-btn-primary:hover{background:#004fa3}
.smgr-btn-ghost{background:#fff;color:#555;border-color:#ccc}
.smgr-btn-ghost:hover{background:#f5f5f5}
.smgr-alert-ok{background:#d4edda;color:#155724;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;border:1px solid #c3e6cb}
.smgr-alert-err{background:#f8d7da;color:#721c24;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;border:1px solid #f5c6cb}

/* ── Moodle IDs row ── */
.smgr-id-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.smgr-id-chip{padding:4px 12px;border-radius:20px;font-size:11.5px;font-weight:600;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:rgba(255,255,255,.9)}
</style>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SEARCH SECTION
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="smgr-card">
    <form method="get" action="">
        <input type="hidden" name="tab" value="<?php echo s($active_tab); ?>">
        <p class="smgr-section-title"><i class="fa fa-search"></i> Search Student</p>
        <div class="smgr-search-bar">
            <input type="text" name="q" value="<?php echo s($search_q); ?>"
                   placeholder="Name, email, Student ID, or Moodle User ID…"
                   autofocus>
            <button type="submit"><i class="fa fa-search"></i> Search</button>
            <?php if ($student_id): ?>
                <a href="student_manager.php" class="smgr-btn smgr-btn-ghost">
                    <i class="fa fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($search_performed && !$search_results): ?>
        <div class="smgr-empty" style="margin-top:16px">
            <i class="fa fa-search"></i>
            No students matched <strong><?php echo s($search_q); ?></strong>.
        </div>
    <?php elseif ($search_results): ?>
        <div class="smgr-results-list">
            <?php foreach ($search_results as $r):
                $r_status = strtolower($r->status ?? '');
                $r_badge  = $r_status === 'active' ? 'active' : 'other';
                $r_photo  = $photo_base_url . '?uid=' . intval($r->moodle_user_id);
                $r_href   = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/student_manager.php',
                               ['sid' => $r->id, 'tab' => $active_tab]))->out(false);
                $r_selected = ($student_id === (int)$r->id);
            ?>
            <a href="<?php echo $r_href; ?>" class="smgr-result-item<?php if ($r_selected) echo ' selected'; ?>"
               style="<?php if ($r_selected) echo 'border-color:#0066cc;background:#f0f6ff'; ?>">
                <img src="<?php echo $r_photo; ?>" class="smgr-result-photo"
                     onerror="this.src='<?php echo $CFG->wwwroot; ?>/pix/u/f2.png'">
                <div>
                    <div class="smgr-result-name">
                        <?php echo s($r->first_name . ' ' . $r->last_name); ?>
                    </div>
                    <div class="smgr-result-meta">
                        <?php if (!empty($r->email)): ?><?php echo s($r->email); ?><?php endif; ?>
                        <?php if (!empty($r->student_id)): ?> · ID: <?php echo s($r->student_id); ?><?php endif; ?>
                        <?php if (!empty($r->phone_number)): ?> · <?php echo s($r->phone_number); ?><?php endif; ?>
                    </div>
                </div>
                <span class="smgr-result-badge <?php echo $r_badge; ?>"><?php echo s($r->status ?: 'Unknown'); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($student): ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     STUDENT HEADER
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php
$st_status     = strtolower($student->status ?? '');
$st_badge_cls  = ($st_status === 'active') ? 'active' : 'other';
$st_photo_url  = $photo_base_url . '?uid=' . intval($student->moodle_user_id);
$base_tab_url  = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/student_manager.php',
                   ['sid' => $student->id, 'q' => $search_q]))->out(false) . '&tab=';
?>
<div class="smgr-card" style="padding:0;overflow:hidden;margin-bottom:0;border-radius:14px 14px 0 0">
    <div class="smgr-student-head">
        <img src="<?php echo $st_photo_url; ?>" class="smgr-student-photo"
             onerror="this.src='<?php echo $CFG->wwwroot; ?>/pix/u/f1.png'"
             alt="Student Photo">
        <div class="smgr-student-info">
            <h2><?php echo s($student->first_name . ' ' . $student->last_name); ?></h2>
            <p>
                <?php if (!empty($student->email)): ?>
                    <span><i class="fa fa-envelope"></i><?php echo s($student->email); ?></span>
                <?php endif; ?>
                <?php if (!empty($student->academic_email)): ?>
                    <span><i class="fa fa-at"></i><?php echo s($student->academic_email); ?></span>
                <?php endif; ?>
                <?php if (!empty($student->phone_number)): ?>
                    <span><i class="fa fa-phone"></i><?php echo s($student->phone_number); ?></span>
                <?php endif; ?>
            </p>
            <div class="smgr-id-chips">
                <?php if (!empty($student->student_id)): ?>
                    <span class="smgr-id-chip"><i class="fa fa-id-badge"></i> ID: <?php echo s($student->student_id); ?></span>
                <?php endif; ?>
                <?php if (!empty($student->zoho_student_id)): ?>
                    <span class="smgr-id-chip"><i class="fa fa-cloud"></i> Zoho: <?php echo s($student->zoho_student_id); ?></span>
                <?php endif; ?>
                <?php if (!empty($student->moodle_user_id)): ?>
                    <span class="smgr-id-chip"><i class="fa fa-user"></i> Moodle UID: <?php echo s($student->moodle_user_id); ?></span>
                <?php endif; ?>
            </div>
            <span class="smgr-sts-badge <?php echo $st_badge_cls; ?>"><?php echo s($student->status ?: 'Unknown'); ?></span>
        </div>
        <!-- Quick admin actions -->
        <div style="margin-left:auto;display:flex;flex-direction:column;gap:8px;align-items:flex-end">
            <?php if ($moodle_user): ?>
            <a href="<?php echo $CFG->wwwroot; ?>/user/profile.php?id=<?php echo $moodle_user->id; ?>"
               target="_blank" class="smgr-btn smgr-btn-ghost" style="font-size:12px;background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.3)">
                <i class="fa fa-external-link"></i> Moodle Profile
            </a>
            <?php endif; ?>
            <button class="smgr-btn" style="font-size:12px;background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.3)"
                    onclick="document.getElementById('smgr_sync_wrap').scrollIntoView({behavior:'smooth'})">
                <i class="fa fa-refresh"></i> Manual Sync
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div style="background:#fff">
        <nav class="smgr-tabs">
            <a href="<?php echo $base_tab_url; ?>profile"  <?php if($active_tab==='profile')  echo 'class="active"'; ?>><i class="fa fa-user"></i> Profile</a>
            <a href="<?php echo $base_tab_url; ?>programs" <?php if($active_tab==='programs') echo 'class="active"'; ?>><i class="fa fa-graduation-cap"></i> Programs & Finance</a>
            <a href="<?php echo $base_tab_url; ?>classes"  <?php if($active_tab==='classes')  echo 'class="active"'; ?>><i class="fa fa-calendar"></i> Classes & Grades</a>
            <a href="<?php echo $base_tab_url; ?>requests" <?php if($active_tab==='requests') echo 'class="active"'; ?>><i class="fa fa-file-text"></i> Requests</a>
            <a href="<?php echo $base_tab_url; ?>card"     <?php if($active_tab==='card')     echo 'class="active"'; ?>><i class="fa fa-id-card"></i> Student Card</a>
        </nav>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB: PROFILE
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php if ($active_tab === 'profile'): ?>
<div class="smgr-card" style="border-radius:0 0 12px 12px">
    <div class="smgr-fields-grid">
        <?php
        smgr_field('First Name',      $student->first_name ?? null);
        smgr_field('Last Name',       $student->last_name  ?? null);
        smgr_field('Email',           $student->email      ?? null);
        smgr_field('Academic Email',  $student->academic_email ?? null);
        smgr_field('Phone Number',    $student->phone_number   ?? null);
        smgr_field('Date of Birth',   smgr_fmt_date($student->date_of_birth ?? null));
        smgr_field('Nationality',     $student->nationality ?? null);
        smgr_field('Address',         $student->address    ?? null);
        smgr_field('City',            $student->city       ?? null);
        smgr_field('Zoho Student ID', $student->zoho_student_id ?? null, true);
        smgr_field('Moodle User ID',  (string)($student->moodle_user_id ?? ''), true);
        smgr_field('Status',          $student->status     ?? null);
        $last_upd = !empty($student->updated_at) && $student->updated_at > 0
            ? userdate($student->updated_at, get_string('strftimedatetimeshort', 'langconfig'))
            : '—';
        smgr_field('Last Synced', $last_upd);
        if (isset($student->national_id) && $student->national_id !== '') {
            smgr_field('National ID', $student->national_id, true);
        }
        ?>
    </div>

    <?php if ($moodle_user): ?>
    <hr style="border:none;border-top:1px solid #f0f0f0;margin:20px 0">
    <strong style="font-size:13px;color:#555"><i class="fa fa-graduation-cap" style="margin-right:5px"></i>Moodle Account</strong>
    <div class="smgr-fields-grid" style="margin-top:12px">
        <?php
        smgr_field('Moodle Username', $moodle_user->username);
        smgr_field('Moodle Full Name', fullname($moodle_user));
        smgr_field('Moodle Email',     $moodle_user->email);
        smgr_field('Last Login',       $moodle_user->lastlogin > 0
            ? userdate($moodle_user->lastlogin, get_string('strftimedatetimeshort', 'langconfig')) : '—');
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB: PROGRAMS & FINANCE
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($active_tab === 'programs'): ?>
<div style="padding-top:8px">
<?php if (empty($registrations)): ?>
    <div class="smgr-empty">
        <i class="fa fa-graduation-cap"></i>
        No program registrations found for this student.
    </div>
<?php else: ?>
<?php foreach ($registrations as $reg):
    $st         = strtolower($reg->registration_status ?? '');
    $badge_cls  = $st === 'active' ? 'active' : ($st === 'completed' ? 'completed' : 'other');
    $pct        = $reg->effective_total > 0 ? min(100, $reg->computed_paid / $reg->effective_total * 100) : 0;
    $bar_color  = $pct >= 100 ? '#1a9940' : ($pct >= 50 ? '#0066cc' : '#e07b00');
    $cur        = s($reg->currency ?: '');
    $inst_arr   = (array)$reg->installments;
    $pay_arr    = (array)$reg->payments;
    $next_due   = smgr_next_installment($inst_arr);
    $slug       = 'reg_' . $reg->id;
?>
<div class="smgr-reg-card">
    <div class="smgr-reg-head">
        <div>
            <h4><?php echo s($reg->program_name ?: ($reg->registration_number ?: 'Program Registration')); ?></h4>
            <p>
                <?php if (!empty($reg->program_level)): ?><?php echo s($reg->program_level); ?> &nbsp;·&nbsp; <?php endif; ?>
                <?php if (!empty($reg->registration_number)): ?>#<?php echo s($reg->registration_number); ?><?php endif; ?>
                <?php if (!empty($reg->study_mode)): ?> &nbsp;·&nbsp; <?php echo s($reg->study_mode); ?><?php endif; ?>
            </p>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <span class="smgr-badge <?php echo $badge_cls; ?>"><?php echo s($reg->registration_status ?: 'Unknown'); ?></span>
            <?php if ($next_due): ?>
                <?php $nd_overdue = strtolower($next_due->status ?? '') === 'overdue'; ?>
                <span style="font-size:11px;color:<?php echo $nd_overdue ? '#c0392b' : '#888'; ?>;font-weight:700">
                    <i class="fa fa-bell"></i>
                    Next due: <?php echo smgr_fmt_date($next_due->due_date); ?>
                    &nbsp;·&nbsp; <?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format((float)$next_due->amount, 0); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Finance strip -->
    <div class="smgr-fin-strip">
        <div class="smgr-fin-cell">
            <div class="smgr-fin-lbl">Total Fees</div>
            <div class="smgr-fin-val navy"><?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format($reg->effective_total, 0); ?></div>
            <div style="font-size:11px;color:#aaa"><?php echo $reg->inst_total_count; ?> installment<?php echo $reg->inst_total_count !== 1 ? 's' : ''; ?></div>
        </div>
        <div class="smgr-fin-cell">
            <div class="smgr-fin-lbl">Paid</div>
            <div class="smgr-fin-val green"><?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format($reg->computed_paid, 0); ?></div>
            <?php if ($reg->inst_overdue_count > 0): ?>
            <div style="font-size:11px;color:#c0392b;font-weight:700"><?php echo $reg->inst_overdue_count; ?> overdue</div>
            <?php endif; ?>
        </div>
        <div class="smgr-fin-cell">
            <div class="smgr-fin-lbl">Balance</div>
            <div class="smgr-fin-val <?php echo $reg->computed_balance > 0 ? 'red' : 'green'; ?>">
                <?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format($reg->computed_balance, 0); ?>
            </div>
        </div>
        <div class="smgr-fin-cell">
            <div class="smgr-fin-lbl">Progress</div>
            <div style="font-size:17px;font-weight:800;color:#00305a"><?php echo round($pct); ?>%</div>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="smgr-prog-wrap">
        <div class="smgr-prog-track">
            <div class="smgr-prog-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $bar_color; ?>"></div>
        </div>
    </div>

    <div class="smgr-reg-body">

        <!-- Installments accordion -->
        <?php if ($inst_arr): ?>
        <div class="smgr-sec-head" onclick="smgrToggle('inst_<?php echo $slug; ?>')">
            <h5><i class="fa fa-list" style="margin-right:6px"></i>Installments (<?php echo count($inst_arr); ?>)</h5>
            <i class="fa fa-chevron-down smgr-chevron" id="chev_inst_<?php echo $slug; ?>"></i>
        </div>
        <div id="inst_<?php echo $slug; ?>" class="smgr-acc-body">
        <table class="smgr-tbl">
            <thead><tr>
                <th>#</th><th>Due Date</th><th>Amount</th><th>Status</th><th>Description</th>
            </tr></thead>
            <tbody>
            <?php $i_idx = 0; foreach ($inst_arr as $inst):
                $i_idx++;
                $i_st = strtolower($inst->status ?? '');
                $row_cls = $i_st === 'paid' ? 'row-paid' : ($i_st === 'overdue' ? 'row-overdue' : '');
            ?>
            <tr class="<?php echo $row_cls; ?>">
                <td><?php echo $i_idx; ?></td>
                <td><?php echo s(smgr_fmt_date($inst->due_date ?? null)); ?></td>
                <td><?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format((float)($inst->amount ?? 0), 0); ?></td>
                <td><span class="smgr-pill <?php echo $i_st; ?>"><?php echo s($inst->status ?? '—'); ?></span></td>
                <td><?php echo s($inst->description ?? '—'); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <!-- Payments accordion -->
        <?php if ($pay_arr): ?>
        <div class="smgr-sec-head" style="margin-top:12px" onclick="smgrToggle('pay_<?php echo $slug; ?>')">
            <h5><i class="fa fa-credit-card" style="margin-right:6px"></i>Payments (<?php echo count($pay_arr); ?>)</h5>
            <i class="fa fa-chevron-down smgr-chevron" id="chev_pay_<?php echo $slug; ?>"></i>
        </div>
        <div id="pay_<?php echo $slug; ?>" class="smgr-acc-body">
        <table class="smgr-tbl">
            <thead><tr>
                <th>#</th><th>Payment Date</th><th>Amount</th><th>Status</th><th>Method</th><th>Reference</th>
            </tr></thead>
            <tbody>
            <?php $p_idx = 0; foreach ($pay_arr as $pay):
                $p_idx++;
                $p_st = strtolower($pay->payment_status ?? '');
            ?>
            <tr>
                <td><?php echo $p_idx; ?></td>
                <td><?php echo s(smgr_fmt_date($pay->payment_date ?? null)); ?></td>
                <td><?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format((float)($pay->amount ?? 0), 0); ?></td>
                <td><span class="smgr-pill <?php echo $p_st; ?>"><?php echo s($pay->payment_status ?? '—'); ?></span></td>
                <td><?php echo s($pay->payment_method ?? '—'); ?></td>
                <td style="font-family:monospace;font-size:12px"><?php echo s($pay->payment_reference ?? '—'); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <?php if (!$inst_arr && !$pay_arr): ?>
            <div class="smgr-empty" style="padding:16px"><i class="fa fa-info-circle"></i> No installment or payment data.</div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB: CLASSES & GRADES
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($active_tab === 'classes'): ?>
<div style="padding-top:8px">
<?php if (empty($enrollments)): ?>
    <div class="smgr-empty">
        <i class="fa fa-calendar"></i>
        No class enrollments found for this student.
    </div>
<?php else: ?>
<div class="smgr-enroll-grid">
<?php foreach ($enrollments as $enroll):
    $enroll_grades = $grades_map[$enroll->id] ?? [];
    $c_status = strtolower($enroll->class_status ?? '');
    $c_status_color = $c_status === 'active' ? '#155724' : ($c_status === 'completed' ? '#004085' : '#555');
?>
<div class="smgr-enroll-card">
    <div class="smgr-enroll-card-head">
        <h5><?php echo s($enroll->class_name ?: ($enroll->class_short_name ?: 'Class')); ?></h5>
        <p>
            <?php if (!empty($enroll->unit_name)): ?><?php echo s($enroll->unit_name); ?> &nbsp;·&nbsp; <?php endif; ?>
            <?php if (!empty($enroll->program_level)): ?><?php echo s($enroll->program_level); ?><?php endif; ?>
        </p>
    </div>
    <div class="smgr-enroll-card-body">
        <div class="smgr-enroll-meta">
            <?php if (!empty($enroll->teacher_name)): ?>
                <span><i class="fa fa-user-o"></i> <?php echo s($enroll->teacher_name); ?></span>
            <?php endif; ?>
            <?php if (!empty($enroll->start_date)): ?>
                <span><i class="fa fa-calendar-o"></i> <?php echo s(smgr_fmt_date($enroll->start_date)); ?></span>
            <?php endif; ?>
            <?php if (!empty($enroll->end_date)): ?>
                <span><i class="fa fa-calendar-check-o"></i> <?php echo s(smgr_fmt_date($enroll->end_date)); ?></span>
            <?php endif; ?>
            <span><i class="fa fa-circle" style="color:<?php echo $c_status_color; ?>;font-size:8px"></i> <?php echo s($enroll->class_status ?? '—'); ?></span>
            <?php if (!empty($enroll->enrollment_status)): ?>
                <span><i class="fa fa-info-circle"></i> <?php echo s($enroll->enrollment_status); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($enroll_grades): ?>
        <div style="margin-top:8px">
            <?php foreach ($enroll_grades as $grade):
                $g_letter = strtoupper(trim($grade->grade_letter ?? $grade->grade ?? ''));
                $g_cls    = in_array($g_letter, ['P','M','D','F','R','RR']) ? $g_letter : 'other';
                $g_label  = !empty($grade->assignment_name) ? $grade->assignment_name : ($grade->grade_type ?? 'Grade');
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0">
                <span style="font-size:12px;color:#555"><?php echo s($g_label); ?></span>
                <span class="smgr-grade-badge <?php echo $g_cls; ?>">
                    <?php echo $g_letter ?: s($grade->grade_value ?? '—'); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div style="font-size:12px;color:#aab2c8;margin-top:8px;text-align:center">No grades recorded</div>
        <?php endif; ?>

        <?php if (!empty($enroll->moodle_class_id)): ?>
        <div style="margin-top:10px;text-align:right">
            <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo (int)$enroll->moodle_class_id; ?>"
               target="_blank" style="font-size:12px;color:#0066cc;text-decoration:none">
                <i class="fa fa-external-link"></i> Open in Moodle
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB: REQUESTS
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($active_tab === 'requests'): ?>
<div class="smgr-card" style="border-radius:0 0 12px 12px">
<?php if (empty($requests_list)): ?>
    <div class="smgr-empty">
        <i class="fa fa-file-text-o"></i>
        No requests submitted by this student.
    </div>
<?php else: ?>
    <table class="smgr-rtbl">
        <thead><tr>
            <th>Type</th>
            <th>Description</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Updated</th>
            <th>Admin Notes</th>
        </tr></thead>
        <tbody>
        <?php foreach ($requests_list as $rq):
            $rq_st  = str_replace([' ','-'], '_', strtolower($rq->status ?? ''));
            $rq_cls = in_array($rq_st, ['submitted','under_review','approved','rejected']) ? $rq_st : 'other';
        ?>
        <tr>
            <td style="font-weight:600;white-space:nowrap"><?php echo s($rq->request_type ?? '—'); ?></td>
            <td style="max-width:300px;font-size:12px;color:#444">
                <?php $desc = $rq->description ?? ''; echo s(mb_strimwidth($desc, 0, 120, '…')); ?>
                <?php if (!empty($rq->reason)): ?><br><em style="color:#777"><?php echo s($rq->reason); ?></em><?php endif; ?>
            </td>
            <td><span class="smgr-rq-status <?php echo $rq_cls; ?>"><?php echo s($rq->status ?? '—'); ?></span></td>
            <td style="font-size:12px;white-space:nowrap">
                <?php echo $rq->created_at ? userdate($rq->created_at, '%d %b %Y') : '—'; ?>
            </td>
            <td style="font-size:12px;white-space:nowrap">
                <?php echo (!empty($rq->updated_at) && $rq->updated_at > 0) ? userdate($rq->updated_at, '%d %b %Y') : '—'; ?>
            </td>
            <td style="font-size:12px;color:#555;max-width:200px">
                <?php echo s($rq->admin_notes ?? '—'); ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     TAB: STUDENT CARD
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php elseif ($active_tab === 'card'): ?>
<div class="smgr-card" style="border-radius:0 0 12px 12px;text-align:center;padding:40px">
    <i class="fa fa-id-card" style="font-size:48px;color:#d0d7e8;display:block;margin-bottom:16px"></i>
    <p style="font-size:15px;color:#555">Open the student card page for this student:</p>
    <a href="<?php echo $student_card_base; ?>?for_student_id=<?php echo $student->id; ?>"
       target="_blank" class="smgr-btn smgr-btn-primary" style="font-size:14px;padding:12px 28px">
        <i class="fa fa-external-link"></i> View Student Card (New Tab)
    </a>
    <?php if ($registrations): ?>
    <p style="font-size:13px;color:#888;margin-top:20px">Select registration:</p>
    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
        <?php foreach ($registrations as $reg): ?>
        <a href="<?php echo $student_card_base; ?>?for_student_id=<?php echo $student->id; ?>&reg_id=<?php echo $reg->id; ?>"
           target="_blank" class="smgr-btn smgr-btn-ghost">
            <?php echo s($reg->program_name ?: $reg->registration_number ?: 'Registration #' . $reg->id); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; // end tabs ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MANUAL SYNC PANEL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="smgr_sync_wrap" class="smgr-card" style="margin-top:24px">
    <div class="smgr-sync-panel" style="border:none;padding:0">
        <h4><i class="fa fa-refresh" style="color:#0066cc;margin-right:6px"></i>Manual Sync from Zoho</h4>
        <p style="font-size:13px;color:#666;margin-bottom:14px">
            Trigger a fresh sync for this student from Zoho CRM.
            The student's Zoho ID is pre-filled — adjust only if needed.
        </p>

        <div class="smgr-sync-row">
            <div>
                <label style="font-size:12px;color:#888;display:block;margin-bottom:4px">Zoho Student ID</label>
                <input type="text" id="smgr_zoho_id"
                       value="<?php echo s($student->zoho_student_id ?? ''); ?>"
                       placeholder="Zoho Student ID">
            </div>
            <div>
                <label style="font-size:12px;color:#888;display:block;margin-bottom:4px">Moodle User ID</label>
                <input type="text" id="smgr_moodle_uid"
                       value="<?php echo s($student->moodle_user_id ?? ''); ?>"
                       placeholder="Moodle UID" style="max-width:140px">
            </div>
            <div style="padding-top:20px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="checkbox" id="smgr_include_related" checked>
                    Include related records
                </label>
            </div>
            <div style="padding-top:18px">
                <button id="smgr_sync_btn" onclick="smgrDoSync()">
                    <i class="fa fa-refresh"></i> Sync Now
                </button>
            </div>
        </div>
        <div id="smgr_sync_result"></div>
        <p style="font-size:11px;color:#aaa;margin-top:10px">
            <i class="fa fa-info-circle"></i>
            Backend URL: <code><?php echo s($backend_url ?: 'not configured'); ?></code>
        </p>
    </div>
</div>

<?php endif; // $student ?>

<!-- Request Windows status (read-only — managed in Settings) -->
<div class="smgr-card" style="margin-top:24px">
    <p class="smgr-section-title"><i class="fa fa-clock-o"></i> Request Windows Status</p>
    <p style="font-size:13px;color:#666;margin-bottom:14px">
        These windows are configured in
        <a href="<?php echo (new moodle_url('/local/moodle_zoho_sync/ui/admin2/settings.php'))->out(); ?>">
            Settings → Request Windows
        </a>.
    </p>
    <table style="border-collapse:collapse;font-size:13px;width:auto">
        <thead><tr style="background:#f8f9fa">
            <th style="padding:8px 16px;font-size:11px;text-transform:uppercase;color:#666;border-bottom:2px solid #e0e0e0">Request Type</th>
            <th style="padding:8px 16px;font-size:11px;text-transform:uppercase;color:#666;border-bottom:2px solid #e0e0e0">Status</th>
        </tr></thead>
        <tbody>
        <?php foreach (['Enroll Next Semester', 'Class Drop'] as $wt): ?>
        <tr style="border-bottom:1px solid #f0f0f0">
            <td style="padding:10px 16px;font-weight:700"><?php echo s($wt); ?></td>
            <td style="padding:10px 16px"><?php echo smgr_win_status($wt); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
// ── Accordion helper ──────────────────────────────────────────────────────
function smgrToggle(id) {
    var el   = document.getElementById(id);
    var chev = document.getElementById('chev_' + id);
    if (!el) return;
    var open = el.style.maxHeight && el.style.maxHeight !== '0px';
    el.style.maxHeight   = open ? '0px' : el.scrollHeight + 'px';
    el.style.overflow    = 'hidden';
    el.style.transition  = 'max-height .35s ease';
    if (chev) chev.style.transform = open ? '' : 'rotate(180deg)';
}
// auto-open all accordion sections
document.querySelectorAll('.smgr-acc-body').forEach(function(el) {
    el.style.maxHeight = el.scrollHeight + 'px';
});

// ── Manual Sync ───────────────────────────────────────────────────────────
var BACKEND_URL   = <?php echo json_encode($backend_url); ?>;
var BACKEND_TOKEN = <?php echo json_encode($backend_token); ?>;

async function smgrDoSync() {
    var zohoId   = document.getElementById('smgr_zoho_id').value.trim();
    var moodleId = document.getElementById('smgr_moodle_uid').value.trim();
    var inclRel  = document.getElementById('smgr_include_related').checked;
    var resDiv   = document.getElementById('smgr_sync_result');
    var btn      = document.getElementById('smgr_sync_btn');

    if (!zohoId && !moodleId) {
        showSyncResult(resDiv, false, 'Please enter a Zoho Student ID or Moodle User ID.');
        return;
    }
    if (!BACKEND_URL) {
        showSyncResult(resDiv, false, 'Backend URL not configured in plugin settings.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing…';

    try {
        var payload = { include_related: inclRel };
        if (zohoId)   payload.zoho_student_id = zohoId;
        if (moodleId) payload.moodle_user_id  = parseInt(moodleId);

        var resp = await fetch(BACKEND_URL + '/api/v1/admin/sync-student', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + BACKEND_TOKEN
            },
            body: JSON.stringify(payload)
        });
        var data = await resp.json();
        if (resp.ok && data.success !== false) {
            showSyncResult(resDiv, true, data.message || 'Sync completed successfully. Reload the page to see updated data.');
        } else {
            showSyncResult(resDiv, false, data.error || data.message || 'Sync failed (HTTP ' + resp.status + ').');
        }
    } catch (e) {
        showSyncResult(resDiv, false, 'Network error: ' + e.message);
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-refresh"></i> Sync Now';
}

function showSyncResult(el, ok, msg) {
    el.className = ok ? 'ok' : 'err';
    el.style.display = 'block';
    el.innerHTML = (ok ? '✅ ' : '❌ ') + msg;
}
</script>

<?php
mzi2_nav_close();
echo $OUTPUT->footer();
?>
