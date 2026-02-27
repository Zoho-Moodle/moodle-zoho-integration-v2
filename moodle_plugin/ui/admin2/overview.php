<?php
/**
 * Admin Dashboard v2 — Overview
 *
 * Main entry point for the admin UI. Shows KPIs, activity chart,
 * recent failures, quick actions, and alert banners.
 *
 * @package   local_moodle_zoho_sync
 * @copyright 2026 ABC Horizon
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/nav.php');

admin_externalpage_setup('local_moodle_zoho_sync_overview');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// ── Handle POST actions ───────────────────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'cleanup' && confirm_sesskey()) {
    $cutoff = time() - (30 * DAYSECS);
    $deleted = $DB->delete_records_select(
        'local_mzi_event_log',
        "status = 'sent' AND timecreated < ?",
        [$cutoff]
    );
    redirect(
        new moodle_url('/local/moodle_zoho_sync/ui/admin2/overview.php'),
        "Cleaned up {$deleted} old sent events.",
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── KPI Queries ───────────────────────────────────────────────────────────
$now    = time();
$day    = $now - DAYSECS;
$week   = $now - (7 * DAYSECS);

// Event counts
$total_events    = (int) $DB->count_records('local_mzi_event_log');
$failed_events   = (int) $DB->count_records('local_mzi_event_log', ['status' => 'failed']);
$sent_events     = (int) $DB->count_records('local_mzi_event_log', ['status' => 'sent']);
$pending_events  = (int) $DB->count_records_select(
    'local_mzi_event_log',
    "status IN ('pending','retrying')"
);
$sent_24h        = (int) $DB->count_records_select(
    'local_mzi_event_log',
    "status = 'sent' AND timecreated >= ?",
    [$day]
);
$failed_24h      = (int) $DB->count_records_select(
    'local_mzi_event_log',
    "status = 'failed' AND timecreated >= ?",
    [$day]
);

// Student / request counts (graceful fallback if tables missing)
$total_students  = 0;
$pending_reqs    = 0;
try {
    $total_students = (int) $DB->count_records('local_mzi_students');
    $pending_reqs   = (int) $DB->count_records('local_mzi_requests', ['request_status' => 'Submitted']);
} catch (dml_exception $e) {
    // Tables may not exist yet
}

// Grade queue pending
$grade_pending = 0;
try {
    $grade_pending = (int) $DB->count_records_select(
        'local_mzi_grade_queue',
        "status NOT IN ('SYNCED','F_CREATED','RR_CREATED')"
    );
} catch (dml_exception $e) {
    // Table may not exist
}

// Success rate (sent / total, ignore if total=0)
$success_rate = $total_events > 0
    ? round(($sent_events / $total_events) * 100, 1)
    : 0;

// ── Activity Chart Data (last 7 days — pure PHP grouping) ─────────────────
$chart_labels  = [];
$chart_sent    = [];
$chart_failed  = [];

for ($i = 6; $i >= 0; $i--) {
    $day_start = mktime(0, 0, 0, date('n', $week + $i * DAYSECS),
                                  date('j', $week + $i * DAYSECS),
                                  date('Y', $week + $i * DAYSECS));
    $day_end   = $day_start + DAYSECS - 1;

    $chart_labels[] = date('D d', $day_start);   // e.g. "Mon 23"

    $chart_sent[]   = (int) $DB->count_records_select(
        'local_mzi_event_log',
        "status = 'sent' AND timecreated BETWEEN ? AND ?",
        [$day_start, $day_end]
    );
    $chart_failed[] = (int) $DB->count_records_select(
        'local_mzi_event_log',
        "status = 'failed' AND timecreated BETWEEN ? AND ?",
        [$day_start, $day_end]
    );
}

// ── Recent Failed Events ──────────────────────────────────────────────────
$recent_failures = $DB->get_records_select(
    'local_mzi_event_log',
    "status = 'failed'",
    [],
    'timecreated DESC',
    'id, event_id, event_type, timecreated, retry_count, last_error',
    0, 8
);

// ── Backend URL configured? ───────────────────────────────────────────────
$backend_url   = get_config('local_moodle_zoho_sync', 'backend_url');
$backend_configured = !empty($backend_url);

// ── Page Output ───────────────────────────────────────────────────────────
echo $OUTPUT->header();
mzi2_nav('overview');
mzi2_breadcrumb('Overview');
?>

<!-- ═══ Alert Banners ═══════════════════════════════════════════════════ -->
<?php if (!$backend_configured): ?>
<div class="mzi2-alert mzi2-alert-warning mb-3">
    <i class="fa fa-exclamation-triangle fa-lg"></i>
    <div>
        <strong>Backend URL not configured.</strong>
        Go to <a href="<?php echo (new moodle_url('/admin/settings.php', ['section' => 'local_moodle_zoho_sync']))->out(); ?>">Plugin Settings</a> to set the backend URL and API token.
    </div>
</div>
<?php endif; ?>

<?php if ($failed_events > 0): ?>
<div class="mzi2-alert mzi2-alert-danger mb-3" id="mzi2-failed-banner">
    <i class="fa fa-times-circle fa-lg"></i>
    <div style="flex:1">
        <strong><?php echo $failed_events; ?> failed event<?php echo $failed_events > 1 ? 's' : ''; ?> need attention.</strong>
        <span style="margin-left:8px">
            <?php echo $failed_24h; ?> occurred in the last 24 hours.
        </span>
    </div>
    <button class="mzi2-btn mzi2-btn-danger mzi2-btn-sm" onclick="mzi2RetryAll(this)">
        <i class="fa fa-repeat"></i> Retry All
    </button>
</div>
<?php endif; ?>

<!-- ═══ KPI Grid ══════════════════════════════════════════════════════════ -->
<div class="mzi2-kpi-grid">

    <div class="mzi2-kpi-card kpi-blue">
        <div class="mzi2-kpi-icon"><i class="fa fa-bolt"></i></div>
        <div class="mzi2-kpi-value"><?php echo number_format($total_events); ?></div>
        <div class="mzi2-kpi-label">Total Events</div>
        <div class="mzi2-kpi-sub"><?php echo $sent_24h; ?> sent in last 24h</div>
    </div>

    <div class="mzi2-kpi-card kpi-green">
        <div class="mzi2-kpi-icon"><i class="fa fa-check-circle"></i></div>
        <div class="mzi2-kpi-value"><?php echo number_format($sent_events); ?></div>
        <div class="mzi2-kpi-label">Sent</div>
        <div class="mzi2-kpi-sub"><?php echo $success_rate; ?>% success rate</div>
    </div>

    <div class="mzi2-kpi-card kpi-red">
        <div class="mzi2-kpi-icon"><i class="fa fa-times-circle"></i></div>
        <div class="mzi2-kpi-value"><?php echo number_format($failed_events); ?></div>
        <div class="mzi2-kpi-label">Failed</div>
        <div class="mzi2-kpi-sub"><?php echo $failed_24h; ?> in last 24h</div>
    </div>

    <div class="mzi2-kpi-card kpi-orange">
        <div class="mzi2-kpi-icon"><i class="fa fa-clock-o"></i></div>
        <div class="mzi2-kpi-value"><?php echo number_format($pending_events); ?></div>
        <div class="mzi2-kpi-label">Pending / Retrying</div>
        <div class="mzi2-kpi-sub">In queue</div>
    </div>

    <div class="mzi2-kpi-card kpi-teal">
        <div class="mzi2-kpi-icon"><i class="fa fa-users"></i></div>
        <div class="mzi2-kpi-value"><?php echo number_format($total_students); ?></div>
        <div class="mzi2-kpi-label">Students</div>
        <div class="mzi2-kpi-sub">In Moodle DB</div>
    </div>

    <div class="mzi2-kpi-card kpi-purple">
        <div class="mzi2-kpi-icon"><i class="fa fa-file-text-o"></i></div>
        <div class="mzi2-kpi-value"><?php echo number_format($pending_reqs); ?></div>
        <div class="mzi2-kpi-label">Pending Requests</div>
        <div class="mzi2-kpi-sub">Awaiting review</div>
    </div>

</div>

<!-- ═══ Success Rate Bar ══════════════════════════════════════════════════ -->
<div class="mzi2-card mb-4">
    <div class="mzi2-card-body" style="padding: 14px 22px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <span style="font-size:13px; font-weight:600; color:#495057; white-space:nowrap;">
                Overall Success Rate
            </span>
            <div style="flex:1; height:10px; background:#f1f3f5; border-radius:5px; overflow:hidden;">
                <div style="height:100%; width:<?php echo $success_rate; ?>%;
                            background: <?php echo $success_rate >= 90 ? '#28a745' : ($success_rate >= 70 ? '#fd7e14' : '#dc3545'); ?>;
                            border-radius:5px; transition: width 0.8s ease;">
                </div>
            </div>
            <span style="font-size:15px; font-weight:700; color:#1a2e4a; white-space:nowrap;">
                <?php echo $success_rate; ?>%
            </span>
            <?php if ($grade_pending > 0): ?>
                <span style="font-size:12px; color:#fd7e14;">
                    <i class="fa fa-graduation-cap"></i> <?php echo $grade_pending; ?> grade<?php echo $grade_pending > 1 ? 's' : ''; ?> in queue
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ═══ Main Grid (Chart + Actions) ══════════════════════════════════════ -->
<div style="display:grid; grid-template-columns: 1fr 320px; gap:20px; margin-bottom:24px;">

    <!-- Activity Chart -->
    <div class="mzi2-card">
        <div class="mzi2-card-header">
            <h3 class="mzi2-card-title">
                <i class="fa fa-bar-chart" style="color:#007bff"></i>
                7-Day Activity
            </h3>
            <span style="font-size:12px; color:#adb5bd;">Sent vs Failed</span>
        </div>
        <div class="mzi2-card-body">
            <canvas id="mzi2-activity-chart" height="160"></canvas>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mzi2-card">
        <div class="mzi2-card-header">
            <h3 class="mzi2-card-title">
                <i class="fa fa-flash" style="color:#fd7e14"></i>
                Quick Actions
            </h3>
        </div>
        <div class="mzi2-card-body" style="padding:16px;">
            <div style="display:flex; flex-direction:column; gap:10px;">

                <button class="mzi2-action-btn" onclick="mzi2RetryAll(this)">
                    <div class="mzi2-action-icon" style="background:#fce8e8; color:#dc3545;">
                        <i class="fa fa-repeat"></i>
                    </div>
                    Retry All Failed
                </button>

                <button class="mzi2-action-btn" onclick="mzi2CheckBackend()">
                    <div class="mzi2-action-icon" style="background:#e8f0fe; color:#007bff;">
                        <i class="fa fa-plug"></i>
                    </div>
                    Test Backend
                </button>

                <a class="mzi2-action-btn" href="<?php echo (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php'))->out(); ?>">
                    <div class="mzi2-action-icon" style="background:#e0f7f9; color:#17a2b8;">
                        <i class="fa fa-list-alt"></i>
                    </div>
                    View Event Logs
                </a>

                <a class="mzi2-action-btn" href="<?php echo (new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php'))->out(); ?>">
                    <div class="mzi2-action-icon" style="background:#f0ebfc; color:#6f42c1;">
                        <i class="fa fa-graduation-cap"></i>
                    </div>
                    Grade Queue
                </a>

                <button class="mzi2-action-btn" onclick="mzi2OpenSyncModal()">
                    <div class="mzi2-action-icon" style="background:#e6f4ea; color:#28a745;">
                        <i class="fa fa-cloud-download"></i>
                    </div>
                    Sync Student
                </button>

                <a class="mzi2-action-btn"
                   href="<?php echo (new moodle_url('/local/moodle_zoho_sync/ui/admin2/overview.php', ['action'=>'cleanup', 'sesskey'=>sesskey()]))->out(); ?>"
                   onclick="return confirm('Delete all sent events older than 30 days?')">
                    <div class="mzi2-action-icon" style="background:#fff8e6; color:#fd7e14;">
                        <i class="fa fa-trash-o"></i>
                    </div>
                    Cleanup Old Logs
                </a>

            </div>
        </div>
    </div>

</div>

<!-- ═══ Recent Failures ════════════════════════════════════════════════════ -->
<div class="mzi2-card mb-4">
    <div class="mzi2-card-header">
        <h3 class="mzi2-card-title">
            <i class="fa fa-exclamation-circle" style="color:#dc3545"></i>
            Recent Failures
        </h3>
        <?php if (!empty($recent_failures)): ?>
            <a href="<?php echo (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php', ['status'=>'failed']))->out(); ?>"
               style="font-size:12px; color:#007bff;">
                View all failed <i class="fa fa-arrow-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($recent_failures)): ?>
        <div class="mzi2-card-body" style="text-align:center; padding:40px; color:#6c757d;">
            <i class="fa fa-check-circle" style="font-size:36px; color:#28a745; display:block; margin-bottom:12px;"></i>
            <strong>No failed events</strong><br>
            <span style="font-size:13px;">Everything is running smoothly.</span>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="mzi2-table">
                <thead>
                    <tr>
                        <th>Event Type</th>
                        <th>Event ID</th>
                        <th>Time</th>
                        <th>Retries</th>
                        <th>Last Error</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_failures as $ev): ?>
                    <tr>
                        <td>
                            <code style="font-size:12px; background:#f8f9fa; padding:2px 7px; border-radius:4px;">
                                <?php echo s($ev->event_type); ?>
                            </code>
                        </td>
                        <td>
                            <span style="font-family:monospace; font-size:12px; color:#6c757d;"
                                  title="<?php echo s($ev->event_id); ?>">
                                <?php echo substr(s($ev->event_id), 0, 10); ?>…
                            </span>
                            <button class="mzi2-btn mzi2-btn-outline mzi2-btn-sm"
                                    style="padding:2px 7px; margin-left:4px;"
                                    onclick="navigator.clipboard.writeText('<?php echo s($ev->event_id); ?>'); this.innerHTML='<i class=\'fa fa-check\'></i>'">
                                <i class="fa fa-copy"></i>
                            </button>
                        </td>
                        <td style="white-space:nowrap; color:#6c757d; font-size:12px;">
                            <?php echo userdate($ev->timecreated, '%d %b, %H:%M'); ?>
                        </td>
                        <td>
                            <?php if ($ev->retry_count > 0): ?>
                                <span class="mzi2-badge mzi2-badge-failed"><?php echo (int)$ev->retry_count; ?>x</span>
                            <?php else: ?>
                                <span style="color:#adb5bd;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:280px;">
                            <?php if (!empty($ev->last_error)): ?>
                                <span style="font-size:12px; color:#721c24;"
                                      title="<?php echo s($ev->last_error); ?>">
                                    <?php echo s(mb_strimwidth($ev->last_error, 0, 70, '…')); ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#adb5bd;">No error message</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="mzi2-btn mzi2-btn-outline mzi2-btn-sm"
                                    onclick="mzi2RetrySingle('<?php echo s($ev->event_id); ?>', this)">
                                <i class="fa fa-repeat"></i> Retry
                            </button>
                            <a href="<?php echo (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php', ['search'=>$ev->event_id ?? '']))->out(); ?>"
                               class="mzi2-btn mzi2-btn-outline mzi2-btn-sm" title="View in Event Logs">
                                <i class="fa fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ═══ Manual Sync Student Modal ═════════════════════════════════════════ -->
<div id="mzi2-sync-modal"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5);
            z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:28px; width:460px; max-width:95vw;
                box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h4 style="margin:0; font-size:16px; color:#1a2e4a;">
                <i class="fa fa-cloud-download" style="color:#28a745; margin-right:8px;"></i>
                Sync Student from Zoho
            </h4>
            <button onclick="mzi2CloseSyncModal()"
                    style="background:none; border:none; font-size:20px; color:#adb5bd; cursor:pointer;">
                &times;
            </button>
        </div>

        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:12px; font-weight:600; color:#495057;
                          text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px;">
                Zoho Student Record ID
            </label>
            <input type="text" id="mzi2-sync-id"
                   placeholder="e.g. 5398830000001234567"
                   style="width:100%; padding:10px 14px; border:2px solid #dee2e6; border-radius:7px;
                          font-size:14px; box-sizing:border-box; outline:none;"
                   onfocus="this.style.borderColor='#007bff'"
                   onblur="this.style.borderColor='#dee2e6'"
                   onkeydown="if(event.key==='Enter') mzi2DoSync()">
        </div>

        <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:20px; cursor:pointer;">
            <input type="checkbox" id="mzi2-sync-related" checked style="width:15px;height:15px;">
            Include related records (registrations, payments, grades…)
        </label>

        <div id="mzi2-sync-result" style="display:none; margin-bottom:16px;"></div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="mzi2-btn mzi2-btn-outline" onclick="mzi2CloseSyncModal()">Cancel</button>
            <button class="mzi2-btn mzi2-btn-success" id="mzi2-sync-btn" onclick="mzi2DoSync()">
                <i class="fa fa-refresh"></i> Sync
            </button>
        </div>
    </div>
</div>

<!-- ═══ Chart.js Activity Chart ═══════════════════════════════════════════ -->
<script>
(function() {
    var labels  = <?php echo json_encode($chart_labels); ?>;
    var sent    = <?php echo json_encode($chart_sent); ?>;
    var failed  = <?php echo json_encode($chart_failed); ?>;
    var backendUrl = <?php echo json_encode(rtrim($backend_url ?: '', '/')); ?>;

    var ctx = document.getElementById('mzi2-activity-chart');
    if (ctx && window.Chart) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Sent',
                        data: sent,
                        backgroundColor: 'rgba(40, 167, 69, 0.75)',
                        borderColor: '#28a745',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Failed',
                        data: failed,
                        backgroundColor: 'rgba(220, 53, 69, 0.75)',
                        borderColor: '#dc3545',
                        borderWidth: 1,
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 } } },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, stepSize: 1 },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }
})();

// ── Retry All ──────────────────────────────────────────────────────────────
async function mzi2RetryAll(btn) {
    if (!confirm('Retry all failed events?')) return;
    btn.disabled = true;
    var origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Retrying…';

    try {
        var resp = await fetch(M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax/retry_failed.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'sesskey=' + M.cfg.sesskey
        });
        var data = await resp.json();
        if (data.success) {
            // Reload to update counts
            window.location.reload();
        } else {
            alert('Failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Request failed: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

// ── Retry Single ──────────────────────────────────────────────────────────
async function mzi2RetrySingle(eventId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

    try {
        var resp = await fetch(M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax/retry_single_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'sesskey=' + M.cfg.sesskey + '&event_id=' + encodeURIComponent(eventId)
        });
        var data = await resp.json();
        if (data.success) {
            btn.closest('tr').style.opacity = '0.4';
            btn.innerHTML = '<i class="fa fa-check" style="color:#28a745"></i> Sent';
        } else {
            btn.innerHTML = '<i class="fa fa-times" style="color:#dc3545"></i> ' + (data.message || 'Failed');
            btn.disabled = false;
        }
    } catch (e) {
        btn.innerHTML = '<i class="fa fa-repeat"></i> Retry';
        btn.disabled = false;
    }
}

// ── Sync Modal ────────────────────────────────────────────────────────────
function mzi2OpenSyncModal() {
    document.getElementById('mzi2-sync-modal').style.display = 'flex';
    setTimeout(() => document.getElementById('mzi2-sync-id').focus(), 100);
}

function mzi2CloseSyncModal() {
    document.getElementById('mzi2-sync-modal').style.display = 'none';
    document.getElementById('mzi2-sync-result').style.display = 'none';
    document.getElementById('mzi2-sync-id').value = '';
}

async function mzi2DoSync() {
    var zohoId  = document.getElementById('mzi2-sync-id').value.trim();
    if (!zohoId) {
        document.getElementById('mzi2-sync-id').focus();
        return;
    }

    var include = document.getElementById('mzi2-sync-related').checked;
    var btn     = document.getElementById('mzi2-sync-btn');
    var result  = document.getElementById('mzi2-sync-result');
    var backUrl = <?php echo json_encode(rtrim($backend_url ?: 'http://localhost:8001', '/')); ?>;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing…';
    result.style.display = 'none';

    try {
        var resp = await fetch(backUrl + '/api/v1/admin/sync-student', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ zoho_student_id: zohoId, include_related: include })
        });
        var data = await resp.json();

        var ok   = data.success === true;
        var bg   = ok ? '#e9f7ef' : '#fef0f0';
        var bdr  = ok ? '#c3e6cb' : '#f5c6cb';
        var ico  = ok ? '✅' : '❌';
        var html = '<div style="padding:14px;background:' + bg + ';border:1px solid ' + bdr + ';border-radius:7px;font-size:13px;">'
                 + ico + ' <strong>' + (data.message || data.error || 'Done') + '</strong>';

        if (data.results) {
            html += '<table style="margin-top:10px;width:100%;border-collapse:collapse;font-size:12px;">';
            html += '<tr style="background:rgba(0,0,0,0.04)"><th style="padding:5px 8px;text-align:left">Module</th>'
                  + '<th style="padding:5px;text-align:center">Total</th><th style="padding:5px;text-align:center">Synced</th>'
                  + '<th style="padding:5px;text-align:center">Errors</th></tr>';
            for (var m in data.results) {
                var r = data.results[m];
                html += '<tr style="border-top:1px solid rgba(0,0,0,0.07)">'
                      + '<td style="padding:5px 8px;font-weight:600;text-transform:capitalize">' + m + '</td>'
                      + '<td style="padding:5px;text-align:center">' + (r.total ?? '—') + '</td>'
                      + '<td style="padding:5px;text-align:center;color:#155724">' + (r.synced ?? '—') + '</td>'
                      + '<td style="padding:5px;text-align:center;color:#721c24">' + (r.errors ?? '—') + '</td></tr>';
            }
            html += '</table>';
        }
        html += '</div>';

        result.innerHTML = html;
        result.style.display = 'block';
    } catch (e) {
        result.innerHTML = '<div style="padding:14px;background:#fef0f0;border:1px solid #f5c6cb;border-radius:7px;font-size:13px;">❌ <strong>' + e.message + '</strong></div>';
        result.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Sync';
    }
}

// Close modal on overlay click
document.getElementById('mzi2-sync-modal').addEventListener('click', function(e) {
    if (e.target === this) mzi2CloseSyncModal();
});
</script>

<?php
// ── Load Chart.js if not already loaded ───────────────────────────────────
?>
<script>
if (!window.Chart) {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
    document.head.appendChild(s);
}
</script>

<?php
mzi2_nav_close();
echo $OUTPUT->footer();
