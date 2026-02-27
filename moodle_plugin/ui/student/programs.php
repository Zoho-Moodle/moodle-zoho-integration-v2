<?php
/**
 * Student My Program Page — Tab 2 of 4
 *
 * Shows each registration as a card: program info, financial summary,
 * installment schedule, and payment history.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/programs.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My Program');
$PAGE->set_heading('My Program');

$userid  = $USER->id;
$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$userid]
);

$registrations = [];
if ($student) {
    $registrations = $DB->get_records_sql(
        "SELECT r.*
           FROM {local_mzi_registrations} r
          WHERE r.student_id = ?
          ORDER BY r.registration_date DESC, r.id DESC",
        [$student->id]
    );
    foreach ($registrations as &$reg) {
        $reg->installments = $DB->get_records_sql(
            "SELECT * FROM {local_mzi_installments}
              WHERE registration_id = ?
              ORDER BY installment_number ASC",
            [$reg->id]
        );
        $reg->payments = $DB->get_records_sql(
            "SELECT * FROM {local_mzi_payments}
              WHERE registration_id = ?
              ORDER BY payment_date DESC, id DESC",
            [$reg->id]
        );
        // ── Installments ───────────────────────────────────────────────────
        $inst_all  = (array)($reg->installments ?? []);
        $today_str = date('Y-m-d');

        // Auto-upgrade pending → overdue in display if due_date < today.
        // Does NOT write to DB — display only.
        foreach ($inst_all as &$_inst) {
            $s = strtolower($_inst->status ?? '');
            if ($s === 'pending' && !empty($_inst->due_date) && $_inst->due_date < $today_str) {
                $_inst->status = 'Overdue';
            }
        }
        unset($_inst);

        // ── Total fees: sum of installments ONLY (no Zoho field fallback) ──
        $reg->effective_total = (float)array_sum(array_column(array_values($inst_all), 'amount'));

        // ── Paid amount: sum of non-voided/non-cancelled payments ──────────
        $reg->computed_paid = array_sum(array_column(
            array_filter((array)$reg->payments, fn($p) => !in_array(
                strtolower($p->payment_status ?? ''), ['voided', 'cancelled']
            )),
            'payment_amount'
        ));

        // ── Balance: simple subtraction ────────────────────────────────────
        $reg->computed_balance = max(0, $reg->effective_total - $reg->computed_paid);

        // ── Overdue summary (after auto-upgrade above) ─────────────────────
        $inst_overdue = array_filter($inst_all, fn($i) => strtolower($i->status ?? '') === 'overdue');
        $reg->inst_total_count    = count($inst_all);
        $reg->inst_overdue_count  = count($inst_overdue);
        $reg->inst_overdue_amount = array_sum(array_column(array_values($inst_overdue), 'amount'));
    }
    unset($reg);
}

// ── helpers ──────────────────────────────────────────────────────────────────

/** Render a registration info row (label + value). */
function sd_ri(?string $lbl, ?string $val): void {
    if ($val === null || $val === '') return;
    echo '<div class="ri-item">'
       . '<div class="ri-lbl">'.s($lbl).'</div>'
       . '<div class="ri-val">'.s($val).'</div>'
       . '</div>';
}

/** Format a date field: Unix timestamp or ISO YYYY-MM-DD → readable string. */
function sd_fmt_date($v): ?string {
    if ($v === null || $v === '') return null;
    $s = trim((string)$v);
    if ($s === '' || $s === '0') return null;
    // Unix timestamp
    if (is_numeric($s) && (int)$s > 0) return userdate((int)$s, '%d %b %Y');
    // ISO date YYYY-MM-DD (possibly with time YYYY-MM-DD HH:MM:SS or YYYY-MM-DDThh:mm:ss)
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
        return $ts ? date('d M Y', $ts) : $s;
    }
    return $s;
}

/** Next unpaid installment (Pending or Overdue with the earliest due_date). */
function sd_next_installment(array $installments): ?object {
    $upcoming = array_filter($installments, fn($i) => in_array(strtolower($i->status ?? ''), ['pending','overdue']));
    if (!$upcoming) return null;
    usort($upcoming, fn($a, $b) => strcmp($a->due_date ?? '', $b->due_date ?? ''));
    return $upcoming[0];
}

echo $OUTPUT->header();
?>
<style>
/* ── Wrapper & nav ─────────────────────────────────────────────────────────── */
.sd-wrap{max-width:1265px;margin:0 auto;padding:0 12px 48px}
.sd-nav{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:26px;padding:10px 0;border-bottom:2px solid #e0e0e0}
.sd-nav a{padding:8px 12px;border-radius:6px 6px 0 0;text-decoration:none;color:#555;font-weight:500;font-size:13px;transition:all .2s;border:1px solid transparent;border-bottom:none;background:#f8f9fa}
.sd-nav a:hover{background:#e9ecef;color:#333}
.sd-nav a.active{background:#fff;color:#00305a;border-color:#00305a;font-weight:700}
.sd-nav a i{margin-right:5px}

/* ── Registration card shell ───────────────────────────────────────────────── */
.sd-reg-card{background:#fff;border-radius:14px;box-shadow:0 2px 18px rgba(0,0,0,.09);margin-bottom:32px;overflow:hidden}

/* ── Card header ───────────────────────────────────────────────────────────── */
.sd-reg-head{background:linear-gradient(135deg,#00305a 0%,#001d38 100%);color:#fff;padding:22px 28px 18px;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px}
.sd-reg-head-left h3{margin:0 0 6px;font-size:20px;font-weight:800;letter-spacing:.01em;line-height:1.2}
.sd-reg-head-left .sd-reg-meta{margin:0;font-size:13px;opacity:.8;display:flex;flex-wrap:wrap;gap:0 14px}
.sd-reg-head-left .sd-reg-meta span{display:inline-flex;align-items:center;gap:4px}
.sd-reg-head-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px}
/* Status badge */
.sd-badge{padding:5px 16px;border-radius:20px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
.sd-badge.active  {background:rgba(56,239,125,.30);color:#b7ffd8;border:1px solid rgba(56,239,125,.4)}
.sd-badge.completed{background:rgba(79,172,254,.30);color:#c6e8ff;border:1px solid rgba(79,172,254,.4)}
.sd-badge.other   {background:rgba(255,255,255,.15);color:rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.25)}
/* Next-due chip inside header */
.sd-next-due{background:rgba(255,200,0,.18);border:1px solid rgba(255,200,0,.4);color:#ffe88a;border-radius:8px;padding:5px 12px;font-size:11px;font-weight:700;white-space:nowrap}
.sd-next-due.overdue{background:rgba(255,70,70,.22);border-color:rgba(255,70,70,.45);color:#ffb3b3}

/* ── Finance strip ─────────────────────────────────────────────────────────── */
.sd-finance-strip{display:flex;flex-wrap:wrap;background:#f4f6fb;border-bottom:1px solid #e3e8f0}
.sd-fin-cell{flex:1;min-width:130px;padding:16px 22px;border-right:1px solid #e3e8f0;position:relative}
.sd-fin-cell:last-child{border-right:none}
.sd-fin-lbl{font-size:10.5px;color:#7a8499;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px}
.sd-fin-val{font-size:22px;font-weight:800;color:#111;line-height:1}
.sd-fin-val.green{color:#1a7a3c}
.sd-fin-val.red  {color:#b00020}
.sd-fin-val.navy {color:#00305a}
.sd-fin-sub{font-size:11px;color:#aaa;margin-top:3px}

/* ── Progress bar ──────────────────────────────────────────────────────────── */
.sd-prog-wrap{padding:10px 22px 12px;background:#f4f6fb;border-bottom:1px solid #e3e8f0}
.sd-prog-labels{display:flex;justify-content:space-between;font-size:11px;color:#888;margin-bottom:5px}
.sd-prog-track{height:10px;background:#dce1ec;border-radius:5px;overflow:hidden}
.sd-prog-fill{height:100%;border-radius:5px;transition:width .7s ease}

/* ── Body ──────────────────────────────────────────────────────────────────── */
.sd-reg-body{padding:22px 28px}

/* ── Info grid ─────────────────────────────────────────────────────────────── */
.sd-info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px 24px;margin-bottom:22px;padding-bottom:20px;border-bottom:1px solid #eff1f5}
.ri-item .ri-lbl{font-size:10.5px;color:#8a93a8;font-weight:700;text-transform:uppercase;letter-spacing:.35px;margin-bottom:3px}
.ri-item .ri-val{font-size:14px;color:#1a1a2e;font-weight:500}

/* ── Section accordion ─────────────────────────────────────────────────────── */
.sd-acc{margin-bottom:16px}
.sd-acc-head{display:flex;justify-content:space-between;align-items:center;cursor:pointer;padding:11px 16px;background:#f0f3f8;border-radius:8px;user-select:none;transition:background .15s}
.sd-acc-head:hover{background:#e6eaf3}
.sd-acc-head h5{margin:0;font-size:13.5px;font-weight:700;color:#00305a;display:flex;align-items:center;gap:8px}
.sd-acc-head .acc-count{font-weight:400;font-size:12px;color:#9aa3bb}
.sd-acc-chevron{font-size:17px;color:#00305a;transition:transform .25s;line-height:1}
.sd-acc-body{overflow:hidden;transition:max-height .35s ease}

/* ── Tables ────────────────────────────────────────────────────────────────── */
.sd-tbl{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
.sd-tbl th{background:#f8f9fc;color:#5a6478;font-weight:700;text-align:left;padding:9px 13px;border-bottom:2px solid #e3e8f0;font-size:10.5px;text-transform:uppercase;letter-spacing:.4px}
.sd-tbl td{padding:9px 13px;border-bottom:1px solid #f1f3f8;color:#2a2d3a;vertical-align:middle}
.sd-tbl tr:last-child td{border-bottom:none}
.sd-tbl tbody tr:hover td{background:#f5f8ff}
/* Row-level status tinting for installments */
.sd-tbl tr.row-paid   td{background:#f0faf3}
.sd-tbl tr.row-overdue td{background:#fff8f5}
.sd-tbl tr.row-pending td{background:#fffdf0}
.sd-tbl tr.row-paid:hover   td{background:#e6f7eb}
.sd-tbl tr.row-overdue:hover td{background:#ffeee8}
.sd-tbl tr.row-pending:hover td{background:#fff9dc}

/* ── Status pills ──────────────────────────────────────────────────────────── */
.sd-pill{display:inline-block;padding:3px 9px;border-radius:10px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.sd-pill.paid     ,.sd-pill.confirmed {background:#d1f0da;color:#0f6628}
.sd-pill.pending               {background:#fff5cc;color:#7a5800}
.sd-pill.overdue               {background:#ffe0d6;color:#9b2200}
.sd-pill.voided,.sd-pill.cancelled   {background:#f3d6da;color:#7a1020}

/* ── Empty state ───────────────────────────────────────────────────────────── */
.sd-empty{text-align:center;padding:22px;color:#aab2c8;font-size:13px;border:2px dashed #e3e8f0;border-radius:8px;margin-top:10px}
.sd-empty i{display:block;font-size:26px;margin-bottom:8px;color:#d0d7e8}
</style>

<div class="sd-wrap">
    <nav class="sd-nav">
        <a href="profile.php"><i class="fa fa-user"></i> Profile</a>
        <a href="programs.php" class="active"><i class="fa fa-graduation-cap"></i> My Programs</a>
        <a href="classes.php"><i class="fa fa-calendar"></i> My Classes &amp; Grades</a>
        <a href="requests.php"><i class="fa fa-file-text"></i> My Requests</a>
        <a href="student_card.php"><i class="fa fa-id-card"></i> Student Card</a>
    </nav>

<?php if (!$student): ?>
    <div class="alert alert-warning"><strong>No student record found.</strong> Please contact the administration office.</div>
<?php elseif (empty($registrations)): ?>
    <div style="text-align:center;padding:56px 24px;color:#aab2c8">
        <i class="fa fa-graduation-cap" style="font-size:52px;display:block;margin-bottom:14px;color:#d4daea"></i>
        <strong style="font-size:15px;color:#777">No program registrations found.</strong><br>
        <span style="font-size:13px">Contact the administration if you expect to see a registration here.</span>
    </div>
<?php else: ?>

<?php foreach ($registrations as $reg):
    $st         = strtolower($reg->registration_status ?? '');
    $badge_cls  = $st === 'active' ? 'active' : ($st === 'completed' ? 'completed' : 'other');
    $pct        = $reg->effective_total > 0 ? min(100, $reg->computed_paid / $reg->effective_total * 100) : 0;
    $bar_color  = $pct >= 100 ? '#1a9940' : ($pct >= 50 ? '#0066cc' : '#e07b00');
    $cur        = s($reg->currency ?: '');
    $slug       = 'reg_' . $reg->id;
    $inst_arr   = (array)$reg->installments;
    $pay_arr    = (array)$reg->payments;
    $next_due   = sd_next_installment($inst_arr);
    $inst_open  = !empty($inst_arr);
    $pay_open   = !empty($pay_arr);
?>
<div class="sd-reg-card">

    <!-- ── Header ───────────────────────────────────────────────────────────── -->
    <div class="sd-reg-head">
        <div class="sd-reg-head-left">
            <h3><?php echo s($reg->program_name ?: ($reg->registration_number ?: 'Program Registration')); ?></h3>
            <p class="sd-reg-meta">
                <?php if (!empty($reg->program_level)): ?>
                    <span><i class="fa fa-layer-group" style="opacity:.65"></i><?php echo s($reg->program_level); ?></span>
                <?php endif; ?>
                <?php if (!empty($reg->registration_number)): ?>
                    <span><i class="fa fa-hashtag" style="opacity:.65"></i><?php echo s($reg->registration_number); ?></span>
                <?php elseif (!empty($reg->zoho_registration_id)): ?>
                    <span><i class="fa fa-hashtag" style="opacity:.65"></i><?php echo s($reg->zoho_registration_id); ?></span>
                <?php endif; ?>
                <?php if (!empty($reg->study_mode)): ?>
                    <span><i class="fa fa-clock-o" style="opacity:.65"></i><?php echo s($reg->study_mode); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="sd-reg-head-right">
            <span class="sd-badge <?php echo $badge_cls; ?>"><?php echo s($reg->registration_status ?: 'Unknown'); ?></span>
            <?php if ($next_due): ?>
                <?php $nd_cls = strtolower($next_due->status ?? '') === 'overdue' ? 'overdue' : ''; ?>
                <div class="sd-next-due <?php echo $nd_cls; ?>">
                    <i class="fa fa-bell" style="margin-right:4px"></i>Next due:
                    <?php echo s(sd_fmt_date($next_due->due_date) ?: '—'); ?>
                    &nbsp;·&nbsp;
                    <?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format((float)$next_due->amount, 0); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Finance strip ────────────────────────────────────────────────────── -->
    <div class="sd-finance-strip">
        <div class="sd-fin-cell">
            <div class="sd-fin-lbl">Total Fees</div>
            <div class="sd-fin-val navy"><?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format($reg->effective_total, 0); ?></div>
            <?php if ($reg->inst_total_count > 0): ?>
            <div class="sd-fin-sub"><?php echo $reg->inst_total_count; ?> installment<?php echo $reg->inst_total_count !== 1 ? 's' : ''; ?></div>
            <?php endif; ?>
        </div>
        <div class="sd-fin-cell">
            <div class="sd-fin-lbl">Amount Paid</div>
            <div class="sd-fin-val green"><?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format($reg->computed_paid, 0); ?></div>
            <?php $valid_pay_count = count(array_filter($pay_arr, fn($p) => !in_array(strtolower($p->payment_status ?? ''), ['voided','cancelled']))); ?>
            <?php if ($valid_pay_count > 0): ?>
            <div class="sd-fin-sub">
                <?php echo $valid_pay_count; ?> payment<?php echo $valid_pay_count !== 1 ? 's' : ''; ?>
                <?php if ($reg->inst_overdue_count > 0): ?>
                &nbsp;<span style="color:#c0392b;font-weight:700">(<?php echo $reg->inst_overdue_count; ?> installment<?php echo $reg->inst_overdue_count !== 1 ? 's' : ''; ?> overdue)</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="sd-fin-cell">
            <div class="sd-fin-lbl">Balance Due</div>
            <div class="sd-fin-val <?php echo $reg->computed_balance > 0 ? 'red' : 'green'; ?>">
                <?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format($reg->computed_balance, 0); ?>
            </div>
            <?php if ($reg->inst_overdue_amount > 0): ?>
            <div class="sd-fin-sub" style="color:#c0392b">
                <?php echo $cur ? $cur . ' ' : ''; ?><?php echo number_format($reg->inst_overdue_amount, 0); ?> overdue
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($reg->payment_plan)): ?>
        <div class="sd-fin-cell">
            <div class="sd-fin-lbl">Payment Plan</div>
            <div class="sd-fin-val" style="font-size:16px;font-weight:700"><?php echo s($reg->payment_plan); ?></div>
            <?php if (!empty($reg->number_of_installments)): ?>
            <div class="sd-fin-sub"><?php echo (int)$reg->number_of_installments; ?> installments</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Payment progress bar ─────────────────────────────────────────────── -->
    <div class="sd-prog-wrap">
        <div class="sd-prog-labels">
            <span>Payment progress</span>
            <span><strong><?php echo number_format($pct, 0); ?>%</strong> paid</span>
        </div>
        <div class="sd-prog-track">
            <div class="sd-prog-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $bar_color; ?>"></div>
        </div>
    </div>

    <!-- ── Body ─────────────────────────────────────────────────────────────── -->
    <div class="sd-reg-body">

        <!-- Registration details grid -->
        <div class="sd-info-grid">
            <?php
            sd_ri('Registration Date', sd_fmt_date($reg->registration_date ?? null));
            sd_ri('Expected Graduation', sd_fmt_date($reg->expected_graduation ?? null) ?: ($reg->expected_graduation ?? null));
            sd_ri('Study Mode', $reg->study_mode ?? null);
            sd_ri('Major', $student->major ?? null);
            sd_ri('Sub Major', $student->sub_major ?? null);
            sd_ri('Zoho Ref', $reg->zoho_registration_id ?? null);
            ?>
        </div>

        <!-- ── Installment Schedule accordion ────────────────────────────────── -->
        <div class="sd-acc">
            <div class="sd-acc-head" onclick="sdToggle('<?php echo $slug; ?>_inst',this)">
                <h5>
                    <i class="fa fa-calendar-check-o"></i>
                    Installment Schedule
                    <?php if (!empty($inst_arr)): ?>
                        <span class="acc-count">(<?php echo count($inst_arr); ?> installments)</span>
                    <?php endif; ?>
                </h5>
                <span class="sd-acc-chevron" style="<?php echo $inst_open ? '' : 'transform:rotate(-90deg)'; ?>">▾</span>
            </div>
            <div id="<?php echo $slug; ?>_inst" class="sd-acc-body" style="max-height:<?php echo $inst_open ? '3000px' : '0'; ?>">
                <?php if (!empty($inst_arr)): ?>
                <table class="sd-tbl">
                    <thead><tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th>Amount<?php echo $cur ? ' (' . $cur . ')' : ''; ?></th>
                        <th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($inst_arr as $inst):
                        // status already auto-upgraded to Overdue if past due (done in data-prep)
                        $ist = strtolower($inst->status ?? '');
                        $row_cls = $ist === 'paid' ? 'row-paid' : ($ist === 'overdue' ? 'row-overdue' : ($ist === 'pending' ? 'row-pending' : ''));
                    ?>
                        <tr class="<?php echo $row_cls; ?>">
                            <td><?php echo (int)$inst->installment_number; ?></td>
                            <td><?php echo s(sd_fmt_date($inst->due_date) ?: '—'); ?></td>
                            <td><strong><?php echo number_format((float)$inst->amount, 0); ?></strong></td>
                            <td><span class="sd-pill <?php echo $ist; ?>"><?php echo s($inst->status ?: '—'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="sd-empty"><i class="fa fa-calendar-o"></i>No installment schedule on record.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Payment History accordion ─────────────────────────────────────── -->
        <div class="sd-acc">
            <div class="sd-acc-head" onclick="sdToggle('<?php echo $slug; ?>_pay',this)">
                <h5>
                    <i class="fa fa-credit-card"></i>
                    Payment History
                    <?php if (!empty($pay_arr)): ?>
                        <span class="acc-count">(<?php echo count($pay_arr); ?> record<?php echo count($pay_arr) !== 1 ? 's' : ''; ?>)</span>
                    <?php endif; ?>
                </h5>
                <span class="sd-acc-chevron" style="<?php echo $pay_open ? '' : 'transform:rotate(-90deg)'; ?>">▾</span>
            </div>
            <div id="<?php echo $slug; ?>_pay" class="sd-acc-body" style="max-height:<?php echo $pay_open ? '3000px' : '0'; ?>">
                <?php if (!empty($pay_arr)): ?>
                <table class="sd-tbl">
                    <thead><tr>
                        <th>Date</th>
                        <th>Amount<?php echo $cur ? ' (' . $cur . ')' : ''; ?></th>
                        <th>Method</th>
                        <th>Bank</th>
                        <th>Reference</th>
                        <th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($pay_arr as $pay):
                        $pst = strtolower($pay->payment_status ?? '');
                    ?>
                        <tr>
                            <td><?php
                                $pdate = sd_fmt_date($pay->payment_date);
                                echo $pdate ? '<strong>' . s($pdate) . '</strong>' : '<span style="color:#bbb">—</span>';
                            ?></td>
                            <td><strong><?php echo number_format((float)$pay->payment_amount, 0); ?></strong></td>
                            <td><?php echo s($pay->payment_method ?: '—'); ?></td>
                            <td><?php echo s($pay->bank_name ?: '—'); ?></td>
                            <td style="font-family:monospace;font-size:12px">
                                <?php echo s($pay->voucher_number ?: $pay->receipt_number ?: $pay->payment_number ?: '—'); ?>
                            </td>
                            <td><span class="sd-pill <?php echo $pst; ?>"><?php echo s($pay->payment_status ?: '—'); ?></span></td>
                        </tr>
                        <?php if (!empty($pay->payment_notes)): ?>
                        <tr class="<?php echo $pst === 'voided' ? '' : ''; ?>">
                            <td colspan="6" style="padding-top:0;padding-bottom:8px;font-size:12px;color:#778;font-style:italic">
                                <i class="fa fa-comment-o" style="margin-right:4px"></i><?php echo s($pay->payment_notes); ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="sd-empty"><i class="fa fa-credit-card"></i>No payments recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.sd-reg-body -->
</div><!-- /.sd-reg-card -->
<?php endforeach; ?>

<?php endif; ?>
</div><!-- /.sd-wrap -->

<script>
function sdToggle(id, headEl) {
    var body    = document.getElementById(id);
    var chevron = headEl.querySelector('.sd-acc-chevron');
    var isOpen  = body.style.maxHeight && body.style.maxHeight !== '0px';
    if (isOpen) {
        body.style.maxHeight = '0px';
        chevron.style.transform = 'rotate(-90deg)';
    } else {
        body.style.maxHeight = '3000px';
        chevron.style.transform = 'rotate(0deg)';
    }
}
</script>
<?php echo $OUTPUT->footer(); ?>
