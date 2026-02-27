<?php
/**
 * My Classes & Grades  — Phase 1
 *
 * Expandable class cards showing:
 *  - Assignments (from mdl_assign for the linked Moodle course)
 *  - Released grades (from local_mzi_grades, btec_grade_name = P/M/D/R/RR/F)
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/classes.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My Classes & Grades');
$PAGE->set_heading('My Classes & Grades');

$userid  = $USER->id;
$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$userid]
);

// ── Logo URLs (shared with Grade Report modal) ───────────────────────────────
$gr_logo_abc     = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/ABC%20Horizon%20Logo%20Full%20Color.png';
$gr_logo_pearson = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/Pearson.png';
$gr_logo_btec    = $CFG->wwwroot . '/local/moodle_zoho_sync/pix/BTEC.png';
$gr_student_name = fullname($USER);

// ── Load enrollments joined with class info ──────────────────────────────────
$enrollments = [];
if ($student) {
    $enrollments = $DB->get_records_sql(
        "SELECT e.id              AS enr_id,
                e.class_id,
                c.zoho_class_id,
                c.class_name,
                c.class_short_name,
                c.teacher_name,
                c.start_date,
                c.end_date,
                c.class_status,
                c.moodle_class_id,
                c.unit_name,
                c.program_level,
                e.enrolled_program,
                cat.name             AS prog_category_name
           FROM {local_mzi_enrollments} e
           JOIN {local_mzi_classes}     c  ON c.id = e.class_id
           LEFT JOIN {course}           co ON co.id = c.moodle_class_id
           LEFT JOIN {course_categories} cat ON cat.id = co.category
          WHERE e.student_id = ?
          ORDER BY c.start_date DESC, c.class_name",
        [$student->id]
    );
}

// ── Bulk-load Moodle assignments for all linked courses ──────────────────────
$assignments_by_course = [];   // [moodle_course_id => [assign objects]]
$sub_by_assign         = [];   // [assign_id        => submission object]

$moodle_ids = [];
foreach ((array)$enrollments as $e) {
    if (!empty($e->moodle_class_id)) {
        $moodle_ids[] = (int)$e->moodle_class_id;
    }
}
$moodle_ids = array_unique($moodle_ids);

if ($moodle_ids) {
    $ph = implode(',', array_fill(0, count($moodle_ids), '?'));
    $assigns = $DB->get_records_sql(
        "SELECT a.id, a.course, a.name, a.intro,
                a.duedate, a.allowsubmissionsfromdate, a.cutoffdate,
                a.nosubmissions,
                cm.id AS cmid
           FROM {assign} a
           JOIN {course_modules} cm ON cm.instance = a.id
           JOIN {modules}        m  ON m.id = cm.module AND m.name = 'assign'
          WHERE a.course IN ($ph)
            AND cm.visible = 1
          ORDER BY a.duedate ASC",
        array_values($moodle_ids)
    );

    foreach ($assigns as $a) {
        $assignments_by_course[(int)$a->course][] = $a;
    }

    // Submission status (latest per assignment per user)
    if ($assigns) {
        $aph  = implode(',', array_fill(0, count($assigns), '?'));
        $subs = $DB->get_records_sql(
            "SELECT s.assignment, s.status, s.timemodified
               FROM {assign_submission} s
              WHERE s.assignment IN ($aph)
                AND s.userid = ?
                AND s.latest = 1",
            array_merge(array_keys($assigns), [$userid])
        );
        foreach ($subs as $s) {
            $sub_by_assign[(int)$s->assignment] = $s;
        }
    }
}

// ── Bulk-load Released grades per class ─────────────────────────────────────
// grade_status column may not exist yet on servers that haven't run the upgrade.
// Load all grades for this student and filter in PHP.
$grades_by_class = [];   // [class_id => [grade objects, ordered by attempt ASC]]
if ($student) {
    $all_grades = $DB->get_records_sql(
        "SELECT * FROM {local_mzi_grades}
          WHERE student_id = ?
          ORDER BY class_id ASC, attempt_number ASC, grade_date DESC",
        [$student->id]
    );
    foreach ($all_grades as $g) {
        // Show only Released grades (filter in PHP for schema compatibility)
        $status = property_exists($g, 'grade_status') ? trim($g->grade_status ?? '') : 'Released';
        if ($status !== 'Released') {
            continue;
        }
        if ($g->class_id) {
            $grades_by_class[(int)$g->class_id][] = $g;
        }
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function format_date_nice(?string $d): string {
    if (empty($d)) return '—';
    $ts = strtotime($d);
    return $ts ? date('M j, Y', $ts) : $d;
}

function class_status_badge(?string $status): string {
    $s = strtolower(trim($status ?? ''));
    $map = [
        'active'    => ['#d4edda', '#155724', 'Active'],
        'completed' => ['#d1ecf1', '#0c5460', 'Completed'],
        'cancelled' => ['#f8d7da', '#721c24', 'Cancelled'],
        'inactive'  => ['#e9ecef', '#495057', 'Inactive'],
    ];
    [$bg, $fg, $label] = $map[$s] ?? ['#e9ecef', '#495057', ucfirst($status ?: 'Unknown')];
    return sprintf(
        '<span style="display:inline-block;padding:3px 12px;border-radius:10px;font-size:11px;font-weight:700;'
        . 'background:%s;color:%s;text-transform:uppercase;letter-spacing:.04em">%s</span>',
        $bg, $fg, htmlspecialchars($label)
    );
}

/**
 * Extract the short grade letter (P/M/D/F/R/RR) from btec_grade_name.
 * btec_grade_name may be a full Zoho record name like:
 *   "Omar Tariq - 2526T2 IT U19 Network Security Design - F - 2026-02-23"
 * or already a short value like "P" or "Merit".
 */
function extract_btec_letter(string $raw): string {
    $raw = trim($raw);
    if (empty($raw)) return '';
    // If it looks like a Zoho record name with " - " separators, extract grade token
    if (strpos($raw, ' - ') !== false) {
        $parts = array_map('trim', explode(' - ', $raw));
        // Valid grade tokens (order matters: RR before R)
        $valid = ['RR', 'P', 'M', 'D', 'F', 'R'];
        foreach ($parts as $part) {
            if (in_array(strtoupper($part), $valid, true)) {
                return strtoupper($part);
            }
        }
    }
    // Short value: take up to 2 chars and uppercase
    $short = strtoupper(substr($raw, 0, 2));
    if ($short === 'RR') return 'RR';
    return strtoupper(substr($raw, 0, 1));
}

function grade_badge_cls(string $btec_name): array {
    $letter = extract_btec_letter($btec_name);
    if ($letter === 'D')  return ['#cce5ff', '#004085'];
    if ($letter === 'M')  return ['#d4edda', '#155724'];
    if ($letter === 'P')  return ['#fff3cd', '#856404'];
    if ($letter === 'RR') return ['#e8d5f5', '#5b2c8d'];
    if ($letter === 'R')  return ['#ffe5cc', '#7a3600'];
    if ($letter === 'F')  return ['#f8d7da', '#721c24'];
    return ['#e9ecef', '#495057'];
}

function assign_status_pill(?object $sub, object $assign): string {
    $now = time();
    if ($assign->nosubmissions) {
        return '<span class="as-pill as-pill-info">No submission required</span>';
    }
    // 'new' = Moodle created the record but student hasn't submitted yet — treat as not submitted
    if (!$sub || $sub->status === 'draft' || $sub->status === 'new') {
        // Check if open
        $open_from = (int)($assign->allowsubmissionsfromdate ?? 0);
        $due       = (int)($assign->duedate ?? 0);
        $cutoff    = (int)($assign->cutoffdate ?? 0);
        if ($open_from > 0 && $now < $open_from) {
            return '<span class="as-pill as-pill-pending">Not open yet</span>';
        }
        if ($cutoff > 0 && $now > $cutoff) {
            return '<span class="as-pill as-pill-late">Closed</span>';
        }
        if ($due > 0 && $now > $due) {
            return '<span class="as-pill as-pill-late">Overdue</span>';
        }
        return '<span class="as-pill as-pill-open">Open</span>';
    }
    if ($sub->status === 'submitted') return '<span class="as-pill as-pill-submitted">Submitted</span>';
    if ($sub->status === 'graded')    return '<span class="as-pill as-pill-graded">Graded</span>';
    return '<span class="as-pill as-pill-info">' . htmlspecialchars($sub->status) . '</span>';
}

echo $OUTPUT->header();
?>
<style>
/* ── layout ──────────────────────────────────────────────────────── */
.sd-wrap{max-width:1100px;margin:0 auto;padding:0 20px 80px}

/* ── nav ─────────────────────────────────────────────────────────── */
.sd-nav{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:28px;padding:10px 0;border-bottom:2px solid #e0e0e0}
.sd-nav a{padding:8px 11px;border-radius:6px 6px 0 0;text-decoration:none;color:#555;font-weight:500;font-size:13px;transition:all .2s;border:1px solid transparent;border-bottom:none;background:#f8f9fa}
.sd-nav a:hover{background:#e9ecef;color:#333}
.sd-nav a.active{background:#fff;color:#0066cc;border-color:#0066cc;font-weight:700}
.sd-nav a i{margin-right:5px}

/* ── section header ──────────────────────────────────────────────── */
.cg-section-title{font-size:11px;font-weight:700;color:#999;text-transform:uppercase;letter-spacing:.07em;margin:0 0 10px;padding-bottom:6px;border-bottom:1px solid #eee}

/* ── class card ──────────────────────────────────────────────────── */
.cg-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:12px;border-left:4px solid #1565C0;overflow:hidden;transition:box-shadow .2s}
.cg-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.11)}
.cg-card.status-completed{border-left-color:#0c5460}
.cg-card.status-cancelled{border-left-color:#c62828}
.cg-card.status-inactive {border-left-color:#aaa}

/* ── card header (clickable) ─────────────────────────────────────── */
.cg-header{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:start;padding:16px 20px 12px;cursor:pointer;user-select:none}
.cg-header:hover .cg-name{color:#0055aa}
.cg-name{font-size:15px;font-weight:800;color:#1a1a2e;margin:0 0 7px;line-height:1.3;transition:color .15s}
.cg-meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:2px}
.cg-meta-item{display:flex;align-items:center;gap:5px;font-size:12px;color:#666}
.cg-meta-item i{color:#aaa;width:13px;font-size:11px;text-align:center}
.cg-unit-pill{display:inline-block;padding:2px 9px;border-radius:8px;background:#eef2ff;color:#3949ab;font-size:11px;font-weight:600;margin-bottom:6px}
.cg-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px;padding-top:2px}
.cg-chevron{font-size:12px;color:#bbb;transition:transform .25s;margin-top:4px}
.cg-card.open .cg-chevron{transform:rotate(180deg)}

/* ── grade summary pills in header ──────────────────────────────── */
.cg-grade-pills{display:flex;flex-wrap:wrap;gap:5px;margin-top:4px}
.cgp{padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700}
.cgp-D{background:#cce5ff;color:#004085}
.cgp-M{background:#d4edda;color:#155724}
.cgp-P{background:#fff3cd;color:#856404}
.cgp-R{background:#ffe5cc;color:#7a3600}
.cgp-F{background:#f8d7da;color:#721c24}
.cgp-pending{background:#f0f0f0;color:#888;font-weight:500;font-style:italic}

/* ── expandable body ─────────────────────────────────────────────── */
.cg-body{display:none;padding:0 20px 22px;border-top:1px solid #f0f0f0}
.cg-card.open .cg-body{display:block}

/* ── unified assignment rows ────────────────────────────────────────────────── */
.au-table{width:100%;border-collapse:collapse;margin-top:12px}
.au-table th{font-size:10px;font-weight:700;color:#aaa;text-transform:uppercase;letter-spacing:.06em;padding:8px 12px;border-bottom:2px solid #eee;text-align:left;white-space:nowrap}
.au-table td{padding:12px 12px;border-bottom:1px solid #f4f4f4;vertical-align:middle;font-size:13px}
.au-table tr:last-child td{border-bottom:none}
.au-table tr:hover td{background:#f9fbff}
.au-name{font-weight:600;color:#1a1a2e;min-width:160px}
.au-due{color:#888;white-space:nowrap;font-size:12px}
.au-due.overdue{color:#c62828;font-weight:600}
.au-actions{white-space:nowrap;text-align:right}
.as-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:6px;background:#1565C0;color:#fff;font-size:12px;font-weight:600;text-decoration:none;transition:background .15s;white-space:nowrap}
.as-btn:hover{background:#0d47a1;color:#fff;text-decoration:none}
.as-btn.submitted{background:#2e7d32}
.as-btn.submitted:hover{background:#1b5e20}
.as-no-course{color:#aaa;font-size:13px;font-style:italic;padding:12px 4px}

/* ── submission/grade pills ──────────────────────────────────────── */
.as-pill{padding:3px 9px;border-radius:10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
.as-pill-open{background:#d4edda;color:#155724}
.as-pill-submitted{background:#cce5ff;color:#004085}
.as-pill-graded{background:#6f42c1;color:#fff}
.as-pill-late{background:#f8d7da;color:#721c24}
.as-pill-pending{background:#e9ecef;color:#555}
.as-pill-info{background:#e9ecef;color:#555}
/* ── grade badge ─────────────────────────────────────────────────── */
.gr-grade-badge{padding:3px 10px;border-radius:10px;font-size:12px;font-weight:700;display:inline-block}
.gr-btn-report{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:6px;background:#6f42c1;color:#fff;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:background .15s;margin-left:4px}
.gr-btn-report:hover{background:#4e2d8a}

/* ── acknowledged badge ──────────────────────────────────────────── */
.ack-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#2e7d32;font-weight:600}
.ack-badge i{font-size:12px}

/* ── empty state ─────────────────────────────────────────────────── */
.cg-empty{text-align:center;padding:60px 20px;color:#aaa}
.cg-empty i{font-size:52px;display:block;margin-bottom:14px;opacity:.3}

/* ── Grade Report Modal (Professional) ────────────────────────────── */
.gr-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:12px;backdrop-filter:blur(3px)}
.gr-overlay.open{display:flex}
.gr-modal{background:#fff;border-radius:4px;width:100%;max-width:780px;max-height:94vh;overflow-y:auto;box-shadow:0 24px 80px rgba(0,0,0,.30);font-family:Arial,Helvetica,sans-serif}
/* Header: white logos row + navy title strip */
.gr-hdr{background:#fff;border-radius:4px 4px 0 0;overflow:hidden}
.gr-hdr-logos{display:flex;align-items:center;justify-content:space-between;padding:14px 22px 12px;border-bottom:4px solid #00305a}
.gr-hdr-title{background:#00305a;color:#fff;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.15em;text-align:center;padding:7px 20px}
.gr-logo-abc{height:46px;object-fit:contain}
.gr-logo-right{display:flex;align-items:center;gap:14px}
.gr-logo-pearson{height:30px;object-fit:contain}
.gr-logo-btec{height:30px;object-fit:contain}
/* Info band */
.gr-infoband{background:#f0f4f8;border-bottom:2px solid #00305a;padding:10px 20px;display:grid;grid-template-columns:1fr 1fr;gap:6px 24px}
.gr-infoband-item{display:flex;gap:6px;align-items:baseline;font-size:12px}
.gr-infoband-key{font-weight:700;color:#00305a;white-space:nowrap;min-width:90px}
.gr-infoband-val{color:#333}
/* Close button area */
.gr-closebar{display:flex;justify-content:flex-end;padding:6px 14px 0;background:#fff}
.gr-modal-close{background:none;border:1px solid #ddd;border-radius:4px;cursor:pointer;font-size:13px;color:#666;padding:3px 10px;line-height:1.4;display:flex;align-items:center;gap:4px}
.gr-modal-close:hover{background:#f5f5f5;color:#333}
/* Assignment row */
.gr-assign-row{padding:12px 20px 6px;border-bottom:1px solid #e8e8e8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.gr-assign-title{font-size:15px;font-weight:800;color:#1a1a2e}
.gr-attempt-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.gr-attempt-first{background:#e3f2fd;color:#1565c0}
.gr-attempt-resub{background:#fff3e0;color:#e65100}
/* Grade hero block */
.gr-grade-hero{display:flex;align-items:center;gap:20px;padding:14px 20px;border-bottom:1px solid #eee;background:linear-gradient(135deg,#fafbfc 0%,#f0f4f8 100%)}
.gr-grade-bigbadge{min-width:80px;height:80px;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.18);flex-shrink:0}
.gr-grade-bigbadge .gr-big-letter{font-size:32px;font-weight:900;line-height:1;margin-bottom:2px}
.gr-grade-bigbadge .gr-big-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;opacity:.85}
.gr-grade-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;flex:1}
.gr-gmi{display:flex;flex-direction:column;gap:1px}
.gr-gmi-key{font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.06em;font-weight:600}
.gr-gmi-val{font-size:13px;font-weight:700;color:#222}
/* Modal body */
.gr-modal-body{padding:16px 20px 6px}
.gr-section{margin-bottom:18px}
.gr-section-label{font-size:10px;font-weight:800;color:#00305a;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #00305a;display:inline-block}
/* LO table */
.gr-lo-table{width:100%;border-collapse:collapse;font-size:12px}
.gr-lo-table thead{background:#00305a}
.gr-lo-table th{font-size:10px;color:#fff;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:6px 8px;text-align:left}
.gr-lo-table td{padding:7px 8px;border-bottom:1px solid #f0f0f0;vertical-align:top}
.gr-lo-table tr:nth-child(even) td{background:#f8f9fa}
.gr-lo-table tr:last-child td{border-bottom:none}
.gr-lo-code{font-weight:700;color:#00305a;white-space:nowrap}
.gr-lo-def{color:#444;max-width:240px}
.gr-lo-achieved{color:#155724;font-weight:800;text-align:center}
.gr-lo-fail{color:#721c24;font-weight:800;text-align:center}
.gr-lo-pending{color:#856404;font-weight:600;text-align:center}
.gr-lo-fb{color:#666;font-style:italic;font-size:11px}
/* Feedback */
.gr-feedback-box{background:#f8f9fa;border-left:4px solid #00305a;border-radius:0 6px 6px 0;padding:12px 16px;font-size:13px;color:#333;line-height:1.7;white-space:pre-wrap}
/* Declaration */
.gr-declaration{background:#fffde7;border:1px solid #f9a825;border-radius:6px;padding:14px 16px;font-size:11.5px;color:#333;line-height:1.7}
.gr-declaration strong{color:#5d4037;display:block;margin-bottom:6px;font-size:12px;text-transform:uppercase;letter-spacing:.05em}
.gr-declaration p{margin:0 0 10px}
.gr-declaration p:last-child{margin:0}
/* Declaration checkbox items */
.gr-decl-item{display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;padding-bottom:12px;border-bottom:1px dashed #f0c040}
.gr-decl-item:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none}
.gr-decl-chk{flex-shrink:0;width:17px;height:17px;margin-top:2px;cursor:pointer;accent-color:#00305a}
.gr-decl-item label{cursor:pointer;flex:1}
/* Acknowledge */
.gr-ack-box{background:#fce4ec;border:1px solid #f48fb1;border-radius:8px;padding:14px 16px;margin-top:14px}
.gr-ack-already{color:#2e7d32;font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px}
.gr-ack-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 22px;border-radius:6px;background:#c62828;color:#fff;font-size:13px;font-weight:700;border:none;cursor:pointer;transition:background .15s;letter-spacing:.02em}
.gr-ack-btn:hover{background:#b71c1c}
.gr-ack-btn:disabled{opacity:.6;cursor:not-allowed}
.gr-ack-prompt{font-size:11.5px;color:#555;margin-top:8px;line-height:1.5}
/* Footer action bar */
.gr-footer{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 16px;border-top:1px solid #eee;margin-top:8px;flex-wrap:wrap;gap:8px}
.gr-must-ack-notice{font-size:11.5px;color:#c62828;font-weight:600;display:flex;align-items:center;gap:5px}
.gr-word-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:6px;background:#1565c0;color:#fff;font-size:12px;font-weight:700;border:none;cursor:pointer;transition:background .15s}
.gr-word-btn:hover{background:#0d47a1}
.gr-close-footer-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:6px;background:#546e7a;color:#fff;font-size:12px;font-weight:700;border:none;cursor:pointer;transition:background .15s}
.gr-close-footer-btn:hover{background:#37474f}
.gr-close-footer-btn:disabled{opacity:.45;cursor:not-allowed}
</style>

<div class="sd-wrap">
  <nav class="sd-nav">
    <a href="profile.php"><i class="fa fa-user"></i> Profile</a>
    <a href="programs.php"><i class="fa fa-graduation-cap"></i> My Programs</a>
    <a href="classes.php" class="active"><i class="fa fa-calendar"></i> My Classes &amp; Grades</a>
    <a href="requests.php"><i class="fa fa-file-text"></i> My Requests</a>
    <a href="student_card.php"><i class="fa fa-id-card"></i> Student Card</a>
  </nav>

<?php if (!$student): ?>
  <div class="alert alert-warning">
    <i class="fa fa-exclamation-triangle"></i>
    <strong>No student record found.</strong> Please contact the administration office.
  </div>
<?php elseif (empty($enrollments)): ?>
  <div class="cg-empty">
    <i class="fa fa-calendar-o"></i>
    <p>No class enrollments found for your account.</p>
  </div>
<?php else: ?>
  <?php
    $active_list = array_filter((array)$enrollments, fn($e) => strtolower($e->class_status ?? '') === 'active');
    $other_list  = array_filter((array)$enrollments, fn($e) => strtolower($e->class_status ?? '') !== 'active');
  ?>

  <?php if ($active_list): ?>
    <p class="cg-section-title">Current Classes (<?php echo count($active_list); ?>)</p>
    <?php foreach ($active_list as $enr): echo render_class_card($enr, $assignments_by_course, $sub_by_assign, $grades_by_class); endforeach; ?>
  <?php endif; ?>

  <?php if ($other_list): ?>
    <p class="cg-section-title" style="margin-top:30px">Past &amp; Other Classes (<?php echo count($other_list); ?>)</p>
    <?php foreach ($other_list as $enr): echo render_class_card($enr, $assignments_by_course, $sub_by_assign, $grades_by_class); endforeach; ?>
  <?php endif; ?>

<?php endif; ?>
</div>

<!-- ── Grade Report Modal ──────────────────────────────────────────────── -->
<script>
var _grLogoAbc     = '<?php echo $gr_logo_abc; ?>';
var _grLogoPearson = '<?php echo $gr_logo_pearson; ?>';
var _grLogoBtec    = '<?php echo $gr_logo_btec; ?>';
</script>
<div id="gr-overlay" class="gr-overlay">
  <div class="gr-modal" id="gr-modal-wrap">

    <!-- Logo header -->
    <div class="gr-hdr">
      <div class="gr-hdr-logos">
        <img class="gr-logo-abc" src="<?php echo $gr_logo_abc; ?>" alt="ABC Horizon" onerror="this.style.display='none'">
        <div class="gr-logo-right">
          <img class="gr-logo-pearson" src="<?php echo $gr_logo_pearson; ?>" alt="Pearson" onerror="this.style.display='none'">
          <img class="gr-logo-btec"    src="<?php echo $gr_logo_btec; ?>"    alt="BTEC"   onerror="this.style.display='none'">
        </div>
      </div>
      <div class="gr-hdr-title">Assignment Result Record</div>
    </div>

    <!-- Programme / Unit / Assessor info band -->
    <div class="gr-infoband" id="gr-infoband"></div>

    <!-- Close button -->
    <div class="gr-closebar">
      <button class="gr-modal-close" id="gr-close-top-btn" onclick="closeGradeModal()">
        <i class="fa fa-times"></i> Close
      </button>
    </div>

    <!-- Assignment title + attempt badge -->
    <div class="gr-assign-row">
      <div class="gr-assign-title" id="gr-modal-title"></div>
      <div id="gr-modal-badges"></div>
    </div>

    <!-- Grade hero: big badge + meta dates -->
    <div class="gr-grade-hero" id="gr-grade-hero"></div>

    <div class="gr-modal-body">

      <!-- Assessment Criteria -->
      <div id="gr-lo-section" class="gr-section" style="display:none">
        <div class="gr-section-label">Assessment Criteria</div>
        <table class="gr-lo-table">
          <thead><tr>
            <th>Criteria</th>
            <th>Description</th>
            <th style="text-align:center;min-width:72px">Achieved</th>
            <th>Assessor Comment</th>
          </tr></thead>
          <tbody id="gr-lo-tbody"></tbody>
        </table>
      </div>

      <!-- Assessor Feedback -->
      <div id="gr-fb-section" class="gr-section" style="display:none">
        <div class="gr-section-label">Assessor Feedback</div>
        <div class="gr-feedback-box" id="gr-fb-text"></div>
      </div>

      <!-- Student Declaration + Consent -->
      <div class="gr-section">
        <div class="gr-section-label">Student Declaration &amp; Consent</div>
        <div class="gr-declaration">
          <div class="gr-decl-item">
            <input type="checkbox" id="gr-chk-decl" class="gr-decl-chk">
            <label for="gr-chk-decl">
              <strong>Student Declaration</strong>
              <p>I certify that the evidence submitted for this assignment is my own. I have clearly referenced any sources, and any artificial intelligence (AI) tools used in the work. I understand that false declaration is a form of malpractice.</p>
            </label>
          </div>
          <div class="gr-decl-item">
            <input type="checkbox" id="gr-chk-consent" class="gr-decl-chk">
            <label for="gr-chk-consent">
              <strong>Student Consent</strong>
              <p>By signing this declaration, you understand that your student assessed work (which may include video recording of your image and/or voice recording and/or photographic images of you and any other individuals who may be featured in your work) may be used by Pearson for the following purposes: standardisation of assessors and verifiers; quality assurance purposes; investigation of malpractice; and to support Pearson&rsquo;s research and development activities.</p>
            </label>
          </div>
        </div>
      </div>

      <!-- Acknowledge section (F/R/RR grades) -->
      <div id="gr-ack-section" style="display:none" class="gr-ack-box">
        <div id="gr-ack-inner"></div>
      </div>

    </div><!-- /.gr-modal-body -->

    <!-- Footer: actions -->
    <div class="gr-footer">
      <div id="gr-must-ack-notice" class="gr-must-ack-notice" style="display:none">
        <i class="fa fa-lock"></i> Please acknowledge the grade to close this report.
      </div>
      <div style="display:flex;gap:10px;margin-left:auto">
        <button class="gr-word-btn" onclick="_doWordDownload()">
          <i class="fa fa-file-word-o"></i> Download Word
        </button>
        <button class="gr-close-footer-btn" id="gr-close-footer-btn" onclick="closeGradeModal()">
          <i class="fa fa-times-circle"></i> Close
        </button>
      </div>
    </div>

  </div><!-- /.gr-modal -->
</div><!-- /#gr-overlay -->

<script>
// ── Card toggle ──────────────────────────────────────────────────────────────
document.querySelectorAll('.cg-header').forEach(function(hdr) {
    hdr.addEventListener('click', function() {
        var card = this.closest('.cg-card');
        card.classList.toggle('open');
    });
});
</script>

<?php
echo $OUTPUT->footer();

// ─────────────────────────────────────────────────────────────────────────────
// Card renderer
// ─────────────────────────────────────────────────────────────────────────────
function render_class_card(
    object $enr,
    array  $assignments_by_course,
    array  $sub_by_assign,
    array  $grades_by_class
): string {
    global $CFG, $USER;

    $status_slug  = 'status-' . strtolower(trim($enr->class_status ?? 'unknown'));
    $name         = s($enr->class_name   ?? 'Unnamed Class');
    $teacher      = s($enr->teacher_name ?? '—');
    $start        = s(format_date_nice($enr->start_date));
    $end          = s(format_date_nice($enr->end_date));
    $badge        = class_status_badge($enr->class_status);
    $moodle_cid   = (int)($enr->moodle_class_id ?? 0);
    $class_db_id  = (int)($enr->class_id ?? 0);

    // ── Grade summary pills for header ────────────────────────────────────────
    $class_grades = $grades_by_class[$class_db_id] ?? [];
    $grade_pills_html = '';
    if ($class_grades) {
        $counts = ['D' => 0, 'M' => 0, 'P' => 0, 'R' => 0, 'F' => 0];
        foreach ($class_grades as $g) {
            $first = extract_btec_letter($g->btec_grade_name ?? '');
            $first = ($first === 'RR') ? 'R' : substr($first, 0, 1); // merge RR into R bucket for header pills
            if (isset($counts[$first])) $counts[$first]++;
        }
        $pills = '';
        foreach ($counts as $k => $v) {
            if ($v > 0) {
                $label = ($v > 1) ? "{$k} ×{$v}" : $k;
                $pills .= "<span class=\"cgp cgp-{$k}\">{$label}</span>";
            }
        }
        if ($pills) {
            $grade_pills_html = "<div class=\"cg-grade-pills\">{$pills}</div>";
        }
    } elseif ($moodle_cid) {
        $grade_pills_html = '<div class="cg-grade-pills"><span class="cgp cgp-pending">No grades yet</span></div>';
    }

    // ── Unit pill ─────────────────────────────────────────────────────────────
    $unit_html = '';
    if (!empty($enr->unit_name)) {
        $unit_html = '<div class="cg-unit-pill">' . s($enr->unit_name) . '</div>';
    }

    // ── Build grade lookup by assignment name ────────────────────────────────
    // key = lowercased assignment_name → latest grade object for this class
    $grade_by_name = [];
    foreach ($class_grades as $g) {
        $key = strtolower(trim($g->assignment_name ?? ''));
        if ($key === '') continue;
        // keep the one with highest attempt_number (last one wins since ordered ASC)
        $grade_by_name[$key] = $g;
    }

    // ── Unified assignments table ─────────────────────────────────────────────
    $now = time();
    if (!$moodle_cid) {
        $body_html = '<p class="as-no-course"><i class="fa fa-info-circle"></i> No Moodle course linked to this class yet.</p>';
    } else {
        $course_assigns = $assignments_by_course[$moodle_cid] ?? [];

        // Auto-pair: match orphan grades to ungraded assignments positionally (by order)
        if ($course_assigns) {
            $moodle_assign_names_lc = array_map(fn($a) => strtolower(trim($a->name)), $course_assigns);
            $orphan_grades_pre = array_values(array_filter($class_grades, fn($g) =>
                !in_array(strtolower(trim($g->assignment_name ?? '')), $moodle_assign_names_lc, true)
            ));
            $ungraded_keys = array_values(array_filter($moodle_assign_names_lc, fn($n) => !isset($grade_by_name[$n])));
            // Pair each orphan grade with the next ungraded assignment in order
            foreach ($orphan_grades_pre as $i => $og) {
                if (isset($ungraded_keys[$i])) {
                    $grade_by_name[$ungraded_keys[$i]] = $og;
                }
            }
        }
        if (!$course_assigns && !$class_grades) {
            $body_html = '<p class="as-no-course"><i class="fa fa-inbox"></i> No assignments found for this class.</p>';
        } else {
            // Track which grades were matched
            $matched_grade_ids = [];

            $rows = '';
            foreach ($course_assigns as $a) {
                $sub        = $sub_by_assign[(int)$a->id] ?? null;
                $sub_pill   = assign_status_pill($sub, $a);
                $due        = (int)$a->duedate;
                $due_str    = $due ? date('M j, Y', $due) : '—';
                $due_cls    = ($due > 0 && $due < $now && !($sub && in_array($sub->status, ['submitted','graded']))) ? ' overdue' : '';
                $assign_url = $CFG->wwwroot . '/mod/assign/view.php?id=' . (int)$a->cmid;
                $btn_class  = ($sub && $sub->status === 'submitted') ? 'as-btn submitted' : 'as-btn';
                $btn_text   = ($sub && in_array($sub->status, ['submitted','graded'])) ? 'View Submission' : 'Go to Assignment';
                $btn_icon   = ($sub && $sub->status === 'graded') ? 'fa-check-circle' : 'fa-external-link';

                // Match grade by assignment name
                $key   = strtolower(trim($a->name));
                $grade = $grade_by_name[$key] ?? null;
                if ($grade) $matched_grade_ids[$grade->id] = true;

                $grade_cell   = '<span style="color:#ccc">—</span>';
                $report_cell  = '';
                if ($grade) {
                    $btec   = trim($grade->btec_grade_name ?? '');
                    $letter = extract_btec_letter($btec);
                    [$gbg, $gfg] = grade_badge_cls($btec);
                    if ($letter) {
                        $grade_cell  = "<span class=\"gr-grade-badge\" style=\"background:{$gbg};color:{$gfg}\">{$letter}</span>";
                    }
                    $g_json = htmlspecialchars(json_encode([
                        'id'         => (int)$grade->id,
                        'letter'     => $letter,
                        'bg'         => $gbg,
                        'fg'         => $gfg,
                        'assignment' => trim((string)($grade->assignment_name ?? '')),
                        'unit'       => trim((string)($grade->unit_name ?? '')),
                        'date'       => (string)($grade->grade_date ?? ''),
                        'attempt'    => (int)($grade->attempt_number ?? 1),
                        'submission' => (string)($grade->submission_date ?? ''),
                        'deadline'   => $due ?: 0,
                        'feedback'   => html_entity_decode((string)($grade->feedback ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'los'        => json_decode($grade->learning_outcomes ?? '[]', true) ?: [],
                        'acked'      => (int)($grade->feedback_acknowledged ?? 0),
                        'acked_at'   => (int)($grade->feedback_acknowledged_at ?? 0),
                        'teacher'    => trim((string)($enr->teacher_name ?? '—')),
                        'class_name' => trim((string)($enr->class_name ?? '')),
                        'prog_level' => trim((string)($enr->program_level ?? '')),
                        'prog_name'  => trim((string)($enr->enrolled_program ?? $enr->prog_category_name ?? '')),
                        'unit_name'  => trim((string)($enr->unit_name ?? '')),
                        'student'    => fullname($USER),
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    $report_cell = "<button class=\"gr-btn-report\" data-grade=\"{$g_json}\" onclick=\"openGradeReport(this)\"><i class=\"fa fa-file-text-o\"></i> View Report</button>";
                }

                $rows .= "<tr>
                  <td class=\"au-name\">" . s($a->name) . "</td>
                  <td class=\"au-due{$due_cls}\"><i class=\"fa fa-clock-o\" style=\"margin-right:4px\"></i>{$due_str}</td>
                  <td>{$sub_pill}</td>
                  <td>{$grade_cell}</td>
                  <td class=\"au-actions\">
                    <a href=\"{$assign_url}\" target=\"_blank\" class=\"{$btn_class}\"><i class=\"fa {$btn_icon}\"></i> {$btn_text}</a>
                    {$report_cell}
                  </td>
                </tr>";
            }

            // Orphan grades (Zoho grades with no matching Moodle assignment)
            foreach ($class_grades as $g) {
                if (isset($matched_grade_ids[$g->id])) continue;
                $btec   = trim($g->btec_grade_name ?? '');
                $letter = extract_btec_letter($btec);
                [$gbg, $gfg] = grade_badge_cls($btec);
                $grade_cell  = $letter ? "<span class=\"gr-grade-badge\" style=\"background:{$gbg};color:{$gfg}\">{$letter}</span>" : '<span style="color:#ccc">—</span>';
                $g_json2 = htmlspecialchars(json_encode([
                    'id'         => (int)$g->id,
                    'letter'     => $letter,
                    'bg'         => $gbg,
                    'fg'         => $gfg,
                    'assignment' => trim((string)($g->assignment_name ?? '')),
                    'unit'       => trim((string)($g->unit_name ?? '')),
                    'date'       => (string)($g->grade_date ?? ''),
                    'attempt'    => (int)($g->attempt_number ?? 1),
                    'submission' => (string)($g->submission_date ?? ''),
                    'deadline'   => 0,
                    'feedback'   => html_entity_decode((string)($g->feedback ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'los'        => json_decode($g->learning_outcomes ?? '[]', true) ?: [],
                    'acked'      => (int)($g->feedback_acknowledged ?? 0),
                    'acked_at'   => (int)($g->feedback_acknowledged_at ?? 0),
                    'teacher'    => trim((string)($enr->teacher_name ?? '—')),
                    'class_name' => trim((string)($enr->class_name ?? '')),
                    'prog_level' => trim((string)($enr->program_level ?? '')),
                    'prog_name'  => trim((string)($enr->enrolled_program ?? $enr->prog_category_name ?? '')),
                    'unit_name'  => trim((string)($enr->unit_name ?? '')),
                    'student'    => fullname($USER),
                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                $report_cell = "<button class=\"gr-btn-report\" data-grade=\"{$g_json2}\" onclick=\"openGradeReport(this)\"><i class=\"fa fa-file-text-o\"></i> View Report</button>";
                $aname = s($g->assignment_name ?? $g->unit_name ?? '—');
                $rows .= "<tr>
                  <td class=\"au-name\">{$aname}</td>
                  <td class=\"au-due\">—</td>
                  <td><span class=\"as-pill as-pill-info\">Recorded</span></td>
                  <td>{$grade_cell}</td>
                  <td class=\"au-actions\">{$report_cell}</td>
                </tr>";
            }

            $body_html = '<table class="au-table">
              <thead><tr>
                <th>Assignment</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Grade</th>
                <th style="text-align:right">Actions</th>
              </tr></thead>
              <tbody>' . $rows . '</tbody>
            </table>';
        }
    }

    ob_start(); ?>
    <div class="cg-card <?php echo $status_slug; ?>">
      <!-- Header (click to expand) -->
      <div class="cg-header">
        <div>
          <div class="cg-name"><?php echo $name; ?></div>
          <?php echo $unit_html; ?>
          <div class="cg-meta">
            <div class="cg-meta-item"><i class="fa fa-user-o"></i><span><?php echo $teacher; ?></span></div>
            <div class="cg-meta-item"><i class="fa fa-calendar"></i><span><?php echo $start; ?></span></div>
            <div class="cg-meta-item"><i class="fa fa-calendar-check-o"></i><span><?php echo $end; ?></span></div>
          </div>
          <?php echo $grade_pills_html; ?>
        </div>
        <div class="cg-right">
          <?php echo $badge; ?>
          <i class="fa fa-chevron-down cg-chevron"></i>
        </div>
      </div>

      <!-- Expandable body: single unified assignments+grades table -->
      <div class="cg-body">
        <?php echo $body_html; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

?>
<script>
// ── Grade Report Modal — Full Redesign ─────────────────────────────────────
var _ackUrl  = M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax/ack_grade.php';
var _mustAck = false;   // When true, Close buttons are disabled until acknowledged
var _grData  = null;    // Current grade data object

// Attempt labels
var _GRADE_LABELS = {
    'D':'Distinction', 'M':'Merit', 'P':'Pass',
    'R':'Referral', 'RR':'Re-Referral', 'F':'Fail'
};

// Parse class name like "(2526T2 IT) U19 Network Security Design"
function _parseClassName(cn) {
    var programme = '', unitNo = '', unitTitle = '';
    if (!cn) return {programme:'', unitNo:'', unitTitle:''};
    var m = cn.match(/\(([^)]+)\)\s*(U\d+)\s*(.*)/i);
    if (m) {
        var inner = m[1].trim().split(/\s+/);
        programme  = inner[inner.length - 1] || m[1];
        unitNo     = m[2];
        unitTitle  = m[3].trim();
    } else {
        var m2 = cn.match(/(U\d+)\s+(.*)/i);
        if (m2) { unitNo = m2[1]; unitTitle = m2[2].trim(); }
    }
    return {programme: programme, unitNo: unitNo, unitTitle: unitTitle};
}

function openGradeReport(btn) {
    var g;
    try { g = JSON.parse(btn.dataset.grade); } catch(e) { console.error('JSON parse error:', e, btn.dataset.grade); return; }
    console.log('🔍 Grade Report data:', g);
    console.log('🔍 LOs:', g.los, 'length:', g.los ? g.los.length : 'undefined');
    _grData = g;

    // Reset declaration checkboxes for each fresh open
    var _chkD = document.getElementById('gr-chk-decl');
    var _chkC = document.getElementById('gr-chk-consent');
    if (_chkD) _chkD.checked = false;
    if (_chkC) _chkC.checked = false;

    // ── Info band ────────────────────────────────────────────────────────────
    var parsed   = _parseClassName(g.class_name || '');
    var progDisp = g.prog_name || g.prog_level || parsed.programme || '—';
    var unitDisp = (parsed.unitNo ? parsed.unitNo + ' \u2013 ' : '') + (g.unit_name || parsed.unitTitle || g.unit || '—');
    var infoband = document.getElementById('gr-infoband');
    infoband.innerHTML =
        '<div class="gr-infoband-item"><span class="gr-infoband-key">Programme:</span><span class="gr-infoband-val">'+_esc(progDisp)+'</span></div>' +
        '<div class="gr-infoband-item"><span class="gr-infoband-key">Centre:</span><span class="gr-infoband-val">ABC Horizon</span></div>' +
        '<div class="gr-infoband-item"><span class="gr-infoband-key">Unit:</span><span class="gr-infoband-val">'+_esc(unitDisp)+'</span></div>' +
        '<div class="gr-infoband-item"><span class="gr-infoband-key">Assessor:</span><span class="gr-infoband-val">'+_esc(g.teacher || '—')+'</span></div>' +
        '<div class="gr-infoband-item"><span class="gr-infoband-key">Student:</span><span class="gr-infoband-val">'+_esc(g.student || '—')+'</span></div>';

    // ── Assignment title ─────────────────────────────────────────────────────
    var title = g.assignment || g.unit || (g.letter ? 'Grade \u2014 ' + g.letter : 'Grade Report');
    document.getElementById('gr-modal-title').textContent = title;

    // ── Attempt badge ────────────────────────────────────────────────────────
    var bd = document.getElementById('gr-modal-badges');
    bd.innerHTML = '';
    if (g.attempt <= 1) {
        bd.innerHTML = '<span class="gr-attempt-badge gr-attempt-first"><i class="fa fa-star-o"></i> 1st Submission</span>';
    } else {
        bd.innerHTML = '<span class="gr-attempt-badge gr-attempt-resub"><i class="fa fa-refresh"></i> Resubmission</span>';
    }
    if (g.acked) {
        bd.innerHTML += ' <span class="ack-badge"><i class="fa fa-check-circle"></i> Acknowledged</span>';
    }

    // ── Grade hero block ─────────────────────────────────────────────────────
    var hero   = document.getElementById('gr-grade-hero');
    var grLabel = _GRADE_LABELS[g.letter] || g.letter || '—';
    var bigBadge = g.letter
        ? '<div class="gr-grade-bigbadge" style="background:'+g.bg+';color:'+g.fg+'">'
            + '<div class="gr-big-letter">'+_esc(g.letter)+'</div>'
            + '<div class="gr-big-label">'+_esc(grLabel)+'</div>'
          + '</div>'
        : '';
    var grMeta =
        '<div class="gr-grade-meta">' +
        (g.deadline ? '<div class="gr-gmi"><span class="gr-gmi-key">Deadline</span><span class="gr-gmi-val">'+_fmtDate(new Date(g.deadline*1000).toISOString())+'</span></div>' : '') +
        (g.submission ? '<div class="gr-gmi"><span class="gr-gmi-key">Date Submitted</span><span class="gr-gmi-val">'+_fmtDate(g.submission)+'</span></div>' : '') +
        (g.date ? '<div class="gr-gmi"><span class="gr-gmi-key">Grade Date</span><span class="gr-gmi-val">'+_fmtDate(g.date)+'</span></div>' : '') +
        '</div>';
    hero.innerHTML = bigBadge + grMeta;

    // ── Learning Outcomes ────────────────────────────────────────────────────
    var loSec  = document.getElementById('gr-lo-section');
    var loBody = document.getElementById('gr-lo-tbody');
    loBody.innerHTML = '';
    if (g.los && g.los.length > 0) {
        loSec.style.display = '';
        g.los.forEach(function(lo) {
            var rawScore = (lo.LO_Score || lo.Criteria_Achieved || lo.score || lo.achieved || '').toString().trim();
            var sl       = rawScore.toLowerCase();
            var numScore = parseFloat(rawScore);
            var isPass, isFail;
            if (!isNaN(numScore)) {
                isPass = numScore >= 1;
                isFail = numScore <= 0;
            } else {
                isPass = ['yes','achieved','pass','p','m','d','merit','distinction'].indexOf(sl) !== -1;
                isFail = ['no','not achieved','fail','f','r','rr','referral'].indexOf(sl) !== -1;
            }
            var cls      = isPass ? 'gr-lo-achieved' : isFail ? 'gr-lo-fail' : 'gr-lo-pending';
            var dispScore= isPass ? '\u2714 YES' : isFail ? '\u2718 NO' : rawScore || '\u2014';
            var fb       = lo.LO_Feedback || lo.Assessment_Comments || lo.feedback || lo.comments || '';
            var code     = lo.LO_Code     || lo.Criteria || lo.code || '';
            var def      = lo.LO_Definition || lo.Description || lo.definition || '';
            loBody.innerHTML += '<tr>'
                + '<td class="gr-lo-code">'  + _esc(code) + '</td>'
                + '<td class="gr-lo-def">'   + _esc(def)  + '</td>'
                + '<td class="' + cls + '">' + _esc(dispScore) + '</td>'
                + '<td class="gr-lo-fb">'    + _esc(fb)   + '</td>'
                + '</tr>';
        });
    } else {
        loSec.style.display = 'none';
    }

    // ── Assessor Feedback ────────────────────────────────────────────────────
    var fbSec  = document.getElementById('gr-fb-section');
    var fbText = document.getElementById('gr-fb-text');
    if (g.feedback && g.feedback.trim()) {
        fbSec.style.display  = '';
        fbText.textContent   = g.feedback;
    } else {
        fbSec.style.display = 'none';
    }

    // ── Acknowledge section & Close gate ────────────────────────────────────
    var ackSec    = document.getElementById('gr-ack-section');
    var ackInner  = document.getElementById('gr-ack-inner');
    var notice    = document.getElementById('gr-must-ack-notice');
    var closeTopBtn = document.getElementById('gr-close-top-btn');
    var closeFtrBtn = document.getElementById('gr-close-footer-btn');

    var _normLetter = (g.letter || '').toUpperCase().trim();
    if (_normLetter) {   // Acknowledge required for ALL graded results
        ackSec.style.display = '';
        if (g.acked) {
            _mustAck = false;
            var d = g.acked_at
                ? new Date(g.acked_at * 1000).toLocaleDateString('en-GB',
                    {day:'numeric', month:'short', year:'numeric'})
                : '';
            ackInner.innerHTML = '<div class="gr-ack-already">'
                + '<i class="fa fa-check-circle"></i> Acknowledged on ' + d + '</div>';
            notice.style.display = 'none';
            if (closeTopBtn) closeTopBtn.disabled = false;
            if (closeFtrBtn) closeFtrBtn.disabled = false;
        } else {
            _mustAck = true;
            ackInner.innerHTML =
                '<strong style="display:block;margin-bottom:8px;color:#c62828;font-size:12px;">'
                + '<i class="fa fa-exclamation-circle"></i> Action Required</strong>'
                + '<button class="gr-ack-btn" id="gr-ack-btn" onclick="_doAck('+g.id+',this)">'
                + '<i class="fa fa-check"></i> I Acknowledge This Grade &amp; Feedback</button>'
                + '<div class="gr-ack-prompt">By acknowledging, you confirm you have read your assessor\'s feedback and understand the result. You must acknowledge before closing this report.</div>';
            notice.style.display = 'flex';
            if (closeTopBtn) closeTopBtn.disabled = true;
            if (closeFtrBtn) closeFtrBtn.disabled = true;
        }
    } else {
        ackSec.style.display = 'none';
        _mustAck = false;
        notice.style.display = 'none';
        if (closeTopBtn) closeTopBtn.disabled = false;
        if (closeFtrBtn) closeFtrBtn.disabled = false;
    }

    document.getElementById('gr-overlay').classList.add('open');
    // Auto-scroll to acknowledge section for grades requiring action
    if (_mustAck) {
        setTimeout(function(){
            var modal = document.querySelector('.gr-modal');
            if (modal) modal.scrollTo({top: modal.scrollHeight, behavior: 'smooth'});
        }, 280);
    }
}

function closeGradeModal() {
    if (_mustAck) return;  // Gate: must acknowledge first
    document.getElementById('gr-overlay').classList.remove('open');
    _grData = null;
    // Reset checkboxes for next open
    var chkD = document.getElementById('gr-chk-decl');
    var chkC = document.getElementById('gr-chk-consent');
    if (chkD) chkD.checked = false;
    if (chkC) chkC.checked = false;
}

function _doAck(gradeId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
    fetch(_ackUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({sesskey: M.cfg.sesskey, grade_id: gradeId})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            document.getElementById('gr-ack-inner').innerHTML =
                '<div class="gr-ack-already"><i class="fa fa-check-circle"></i>'
                + ' Acknowledged on ' + res.ack_date + '</div>';
            _mustAck = false;
            var notice    = document.getElementById('gr-must-ack-notice');
            var closeTopBtn = document.getElementById('gr-close-top-btn');
            var closeFtrBtn = document.getElementById('gr-close-footer-btn');
            if (notice)    notice.style.display = 'none';
            if (closeTopBtn) closeTopBtn.disabled = false;
            if (closeFtrBtn) closeFtrBtn.disabled = false;
            // Update embedded data so re-opening shows correct state
            document.querySelectorAll('[data-grade]').forEach(function(el) {
                try {
                    var d = JSON.parse(el.dataset.grade);
                    if (d.id == gradeId) { d.acked = 1; el.dataset.grade = JSON.stringify(d); }
                } catch(e) {}
            });
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check"></i> I Acknowledge This Grade &amp; Feedback';
            alert(res.error || 'Something went wrong. Please try again.');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-check"></i> Retry';
    });
}

async function _doWordDownload() {
    if (!_grData) return;
    var chkDecl    = document.getElementById('gr-chk-decl');
    var chkConsent = document.getElementById('gr-chk-consent');
    if (!chkDecl || !chkConsent || !chkDecl.checked || !chkConsent.checked) {
        alert('Please tick both the Student Declaration and Student Consent checkboxes before downloading.');
        return;
    }
    var g            = _grData;
    var parsed       = _parseClassName(g.class_name || '');
    var progDisp     = g.prog_name || g.prog_level || parsed.programme || '\u2014';
    var unitDisp     = (parsed.unitNo ? parsed.unitNo + ' \u2013 ' : '') + (g.unit_name || parsed.unitTitle || g.unit || '\u2014');
    var grLabel      = _GRADE_LABELS[g.letter] || g.letter || '\u2014';
    var attemptLabel = g.attempt <= 1 ? '1st Submission' : 'Resubmission';

    // Show loading on button
    var wordBtn = document.querySelector('.gr-word-btn');
    if (wordBtn) { wordBtn.disabled = true; wordBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Preparing...'; }

    // Fetch logos on same-origin Moodle server → embed as base64 data URIs
    async function toDataURL(url) {
        try {
            var r    = await fetch(url);
            var blob = await r.blob();
            return await new Promise(function(res) {
                var fr = new FileReader();
                fr.onload = function() { res(fr.result); };
                fr.readAsDataURL(blob);
            });
        } catch(e) { return ''; }
    }
    var logoAbcSrc     = await toDataURL(_grLogoAbc);
    var logoPearsonSrc = await toDataURL(_grLogoPearson);
    var logoBtecSrc    = await toDataURL(_grLogoBtec);
    if (wordBtn) { wordBtn.disabled = false; wordBtn.innerHTML = '<i class="fa fa-file-word-o"></i> Download Word'; }

    // Build LO rows
    var loRows = '';
    if (g.los && g.los.length > 0) {
        g.los.forEach(function(lo) {
            var rawScore   = (lo.LO_Score||lo.Criteria_Achieved||lo.score||lo.achieved||'').toString().trim();
            var numScore   = parseFloat(rawScore);
            var isPass     = !isNaN(numScore) ? numScore >= 1 : ['yes','achieved','pass'].indexOf(rawScore.toLowerCase()) !== -1;
            var isFail     = !isNaN(numScore) ? numScore <= 0 : ['no','not achieved','fail'].indexOf(rawScore.toLowerCase()) !== -1;
            var dispScore  = isPass ? 'YES' : isFail ? 'NO' : rawScore || '\u2014';
            var scoreColor = isPass ? '#155724' : isFail ? '#7b0000' : '#856404';
            var code = lo.LO_Code      || lo.Criteria    || lo.code       || '';
            var def  = lo.LO_Definition|| lo.Description || lo.definition || '';
            var fb   = lo.LO_Feedback  || lo.Assessment_Comments || lo.feedback || '';
            loRows += '<tr style="font-size:9pt">'
                + '<td style="font-weight:700;border:1pt solid #bbb;padding:4pt 5pt;white-space:nowrap;vertical-align:top">'+_escHtml(code)+'</td>'
                + '<td style="border:1pt solid #bbb;padding:4pt 5pt;vertical-align:top">'+_escHtml(def)+'</td>'
                + '<td style="font-weight:800;color:'+scoreColor+';border:1pt solid #bbb;padding:4pt 5pt;text-align:center;vertical-align:top;white-space:nowrap">'+_escHtml(dispScore)+'</td>'
                + '<td style="color:#555;font-style:italic;border:1pt solid #bbb;padding:4pt 5pt;vertical-align:top">'+_escHtml(fb)+'</td>'
                + '</tr>';
        });
    }

    var deadlineStr  = g.deadline   ? _fmtDate(new Date(g.deadline*1000).toISOString()) : '\u2014';
    var submittedStr = g.submission ? _fmtDate(g.submission) : '\u2014';
    var gradeDateStr = g.date       ? _fmtDate(g.date)       : '\u2014';

    function logoTag(src, w, h, alt, fallbackText) {
        if (src) return '<img src="'+src+'" width="'+w+'" height="'+h+'" alt="'+_escHtml(alt)+'" style="width:'+w+'px;height:'+h+'px;display:inline-block;vertical-align:middle">';
        return '<span style="font-weight:900;font-size:10pt;color:#00305a">'+_escHtml(fallbackText)+'</span>';
    }

    // ── Clean HTML for Word (open with Word or any browser)
    var wDoc =
        '<html xmlns:o="urn:schemas-microsoft-com:office:office"\n'
        +'      xmlns:w="urn:schemas-microsoft-com:office:word"\n'
        +'      xmlns="http://www.w3.org/TR/REC-html40">\n'
        +'<head>\n'
        +'<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">\n'
        +'<title>Assignment Result Record</title>\n'
        +'<style>\n'
        +'  @page { size: 21.0cm 29.7cm; margin: 2.5cm; }\n'
        +'  body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #222; margin: 0; padding: 8pt; }\n'
        +'  table { border-collapse: collapse; width: 100%; }\n'
        +'  .lbl { font-size: 8pt; color: #666; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; display: block; margin-bottom: 2pt; }\n'
        +'  .val { font-size: 11pt; font-weight: bold; color: #1a1a2e; display: block; }\n'
        +'  .shdr { display: block; font-size: 9pt; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; color: #fff; background: #00305a; padding: 4pt 7pt; margin: 10pt 0 0; }\n'
        +'  .decl-title { display: block; font-weight: 900; font-size: 9pt; text-transform: uppercase; letter-spacing: .05em; color: #5d4037; margin-bottom: 4pt; }\n'
        +'  .decl-box { background: #fffde7; border: 1pt solid #f9a825; padding: 9pt 12pt; font-size: 10pt; line-height: 1.7; margin-top: 10pt; }\n'
        +'  th { background: #00305a; color: #fff; padding: 5pt 6pt; text-align: left; font-size: 9pt; }\n'
        +'  td.info { padding: 5pt 8pt; }\n'
        +'  p { margin: 0 0 6pt; }\n'
        +'</style>\n'
        +'</head><body>\n'
        +'<div>\n'

        // ── Logo header bar
        // ── Row 1: logos only
        +'<table style="width:100%;border-collapse:collapse;margin-bottom:0"><tr>\n'
        +'  <td style="width:80pt;padding:8pt 0 6pt;vertical-align:middle">'
        +   logoTag(logoAbcSrc,'75','75','ABC Horizon','ABC Horizon')+'</td>\n'
        +'  <td style="text-align:right;vertical-align:middle;padding:8pt 0 6pt">'
        +   logoTag(logoPearsonSrc,'95','36','Pearson','')+'&nbsp;&nbsp;'
        +   logoTag(logoBtecSrc,'72','36','BTEC','Pearson BTEC')+'</td>\n'
        +'</tr></table>\n'
        // ── Row 2: title bar (full-width navy)
        +'<div style="background:#00305a;color:#fff;text-align:center;font-size:13pt;font-weight:900;text-transform:uppercase;letter-spacing:.15em;padding:7pt 10pt;margin-bottom:10pt">Assignment Result Record</div>\n'

        // ── Programme / Unit / Assessor / Student
        +'<table style="margin-bottom:8pt">\n'
        +'<tr>\n'
        +'  <td class="info" style="width:50%;border:1pt solid #ccc"><span class="lbl">Programme</span><span class="val">'+_escHtml(progDisp)+'</span></td>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Centre / Provider</span><span class="val">ABC Horizon</span></td>\n'
        +'</tr><tr>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Unit</span><span class="val">'+_escHtml(unitDisp)+'</span></td>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Assessor</span><span class="val">'+_escHtml(g.teacher||'\u2014')+'</span></td>\n'
        +'</tr><tr>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Student Name</span><span class="val">'+_escHtml(g.student||'\u2014')+'</span></td>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Submission Type</span><span class="val">'+_escHtml(attemptLabel)+'</span></td>\n'
        +'</tr>\n</table>\n'

        // ── Assignment title + Grade badge
        +'<table style="margin-bottom:8pt"><tr>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Assignment Title</span>'
        +   '<span class="val" style="font-size:13pt">'+_escHtml(g.assignment||g.unit||'\u2014')+'</span></td>\n'
        +'  <td style="width:15%;border:2pt solid #555;padding:8pt;text-align:center;vertical-align:middle;background:'+g.bg+';color:'+g.fg+'">'
        +   '<b style="font-size:22pt;display:block;line-height:1.1">'+_escHtml(g.letter||'\u2014')+'</b>'
        +   '<span style="font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:.08em;display:block">'+_escHtml(grLabel)+'</span>'
        +   '</td>\n'
        +'</tr></table>\n'

        // ── Deadline / Submitted / Grade Date
        +'<table style="margin-bottom:10pt"><tr>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Deadline</span><span class="val">'+_escHtml(deadlineStr)+'</span></td>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Date Submitted</span><span class="val">'+_escHtml(submittedStr)+'</span></td>\n'
        +'  <td class="info" style="border:1pt solid #ccc"><span class="lbl">Grade Date</span><span class="val">'+_escHtml(gradeDateStr)+'</span></td>\n'
        +'</tr></table>\n';

    // ── Assessment Criteria
    if (loRows) {
        wDoc += '<span class="shdr">Assessment Criteria</span>\n'
            +'<table style="margin-top:2pt;margin-bottom:10pt"><thead><tr>\n'
            +'  <th style="width:11%">Criteria</th>\n'
            +'  <th style="width:38%">Description</th>\n'
            +'  <th style="width:11%;text-align:center">Achieved</th>\n'
            +'  <th>Assessor Comment</th>\n'
            +'</tr></thead><tbody>'+loRows+'</tbody></table>\n';
    }

    // ── Assessor Feedback
    if (g.feedback && g.feedback.trim()) {
        wDoc += '<span class="shdr">Assessor Feedback</span>\n'
            +'<div style="background:#f5f5f5;border-left:3pt solid #00305a;padding:8pt 12pt;font-size:10.5pt;line-height:1.7;white-space:pre-wrap;margin-top:2pt">'+_escHtml(g.feedback)+'</div>\n';
    }

    // ── Declaration + Consent + Signature
    wDoc += '<div class="decl-box">'
        +'<span class="decl-title">Student Declaration</span>'
        +'<p>I certify that the evidence submitted for this assignment is my own. I have clearly referenced any sources, and any artificial intelligence (AI) tools used in the work. I understand that false declaration is a form of malpractice.</p>'
        +'<span class="decl-title" style="margin-top:8pt">Student Consent</span>'
        +'<p style="margin:0">By signing this declaration, you understand that your student assessed work (which may include video recording of your image and/or voice recording and/or photographic images of you and any other individuals who may be featured in your work) may be used by Pearson for the following purposes: standardisation of assessors and verifiers; quality assurance purposes; investigation of malpractice; and to support Pearson\u2019s research and development activities.</p>'
        +'</div>\n'
        +'<table style="margin-top:16pt"><tr>\n'
        +'  <td style="width:50%;padding:4pt 12pt 0 0"><span class="lbl">Student Signature</span>'
        +   '<div style="border-bottom:1pt solid #555;height:26pt;margin-top:4pt"></div></td>\n'
        +'  <td style="padding:4pt 0 0 0"><span class="lbl">Date</span>'
        +   '<div style="border-bottom:1pt solid #555;height:26pt;margin-top:4pt"></div></td>\n'
        +'</tr></table>\n'
        +'</div>\n'   // Section1
        +'</body></html>';

    var blob = new Blob([wDoc], {type: 'application/msword'});
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    var fname = ((g.assignment||g.unit||'Grade_Report').replace(/[^a-zA-Z0-9_\- ]/g,'_') + '_Report.doc');
    a.href = url; a.download = fname; a.style.display = 'none';
    document.body.appendChild(a); a.click();
    setTimeout(function() { URL.revokeObjectURL(url); a.remove(); }, 3000);
}

function _fmtDate(s) {
    if (!s) return '\u2014';
    var d = new Date(s);
    return isNaN(d) ? s : d.toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'});
}

function _esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function _escHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !_mustAck) closeGradeModal();
});
</script>
