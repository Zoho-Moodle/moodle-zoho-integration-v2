<?php
/**
 * Admin Dashboard v2 — System Health
 *
 * @package   local_moodle_zoho_sync
 * @copyright 2026 ABC Horizon
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/nav.php');

admin_externalpage_setup('local_moodle_zoho_sync_health_v2');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// ── Backend connectivity check ────────────────────────────────────────────
$backend_url  = get_config('local_moodle_zoho_sync', 'backend_url') ?: 'http://localhost:8001';
$api_token    = get_config('local_moodle_zoho_sync', 'api_token') ?: '';
$ssl_verify   = (bool) get_config('local_moodle_zoho_sync', 'ssl_verify');

$backend_ok      = false;
$backend_msg     = '';
$backend_latency = null;

$t0 = microtime(true);
try {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => rtrim($backend_url, '/') . '/health',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => $ssl_verify,
        CURLOPT_SSL_VERIFYHOST => $ssl_verify ? 2 : 0,
        CURLOPT_HTTPHEADER     => $api_token
            ? ['Authorization: Bearer ' . $api_token]
            : [],
    ]);
    $response = curl_exec($curl);
    $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($curl);
    curl_close($curl);

    $backend_latency = round((microtime(true) - $t0) * 1000);

    if ($curl_err) {
        $backend_msg = "Connection error: {$curl_err}";
    } elseif ($http_code >= 200 && $http_code < 400) {
        $backend_ok  = true;
        $backend_msg = "HTTP {$http_code} — {$backend_latency}ms";
    } else {
        $backend_msg = "HTTP {$http_code} — {$backend_latency}ms";
    }
} catch (Exception $e) {
    $backend_msg = $e->getMessage();
}

// ── DB tables check ───────────────────────────────────────────────────────
$dbman = $DB->get_manager();
$required_tables = [
    'local_mzi_event_log',
    'local_mzi_students',
    'local_mzi_registrations',
    'local_mzi_installments',
    'local_mzi_payments',
    'local_mzi_enrollments',
    'local_mzi_grades',
    'local_mzi_grade_queue',
    'local_mzi_requests',
    'local_mzi_btec_templates',
    'local_mzi_request_windows',
    'local_mzi_config',
];
$table_status = [];
$tables_ok = true;
foreach ($required_tables as $t) {
    $exists = $dbman->table_exists(new xmldb_table($t));
    $table_status[$t] = $exists;
    if (!$exists) $tables_ok = false;
}

// ── Event stats (last 24h) ────────────────────────────────────────────────
$day        = time() - DAYSECS;
$ev_total   = (int) $DB->count_records_select('local_mzi_event_log', 'timecreated >= ?', [$day]);
$ev_sent    = (int) $DB->count_records_select('local_mzi_event_log', "status='sent' AND timecreated >= ?", [$day]);
$ev_failed  = (int) $DB->count_records_select('local_mzi_event_log', "status='failed' AND timecreated >= ?", [$day]);
$ev_pending = (int) $DB->count_records_select('local_mzi_event_log', "status IN ('pending','retrying') AND timecreated >= ?", [$day]);
$fail_rate  = $ev_total > 0 ? round(($ev_failed / $ev_total) * 100, 1) : 0;
$events_ok  = $fail_rate < 10;

// All-time totals
$ev_all_failed = (int) $DB->count_records('local_mzi_event_log', ['status' => 'failed']);
$max_retries   = (int) (get_config('local_moodle_zoho_sync', 'max_retry_attempts') ?: 3);

// ── Scheduled tasks status ────────────────────────────────────────────────
$tasks_info = [];
$task_classes = [
    'local_moodle_zoho_sync\task\retry_failed_webhooks',
    'local_moodle_zoho_sync\task\cleanup_old_logs',
    'local_moodle_zoho_sync\task\health_monitor',
    'local_moodle_zoho_sync\task\sync_missing_grades',
];
foreach ($task_classes as $classname) {
    $rec = $DB->get_record('task_scheduled', ['classname' => $classname]);
    if ($rec) {
        $tasks_info[] = [
            'class'    => basename(str_replace('\\', '/', $classname)),
            'last_run' => $rec->lastruntime ? date('d M H:i', $rec->lastruntime) : 'Never',
            'next_run' => $rec->nextruntime ? date('d M H:i', $rec->nextruntime) : '?',
            'disabled' => (bool) $rec->disabled,
        ];
    }
}

// ── Render ────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
mzi2_nav_css();
mzi2_nav('health');
mzi2_breadcrumb('Health');
?>
<div class="mzi2-wrap" style="padding:20px;">

<div class="d-flex align-items-center gap-3 mb-4">
  <h2 style="margin:0;">System Health</h2>
  <form method="get" action="">
    <button type="submit" class="mzi2-btn" style="background:#f3f4f6;color:#374151;">↺ Refresh</button>
  </form>
  <span class="text-muted" style="font-size:13px;">Checked at <?= date('H:i:s') ?></span>
</div>

<!-- Health matrix -->
<div class="row g-3 mb-4">

  <!-- Backend API -->
  <?php $col = $backend_ok ? '#10b981' : '#ef4444'; ?>
  <div class="col-md-4">
    <div class="card h-100" style="border-left:4px solid <?= $col ?>;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span style="font-size:20px;"><?= $backend_ok ? '✅' : '❌' ?></span>
          <h5 class="mb-0">Backend API</h5>
        </div>
        <p class="mb-1" style="color:<?= $col ?>;font-weight:600;">
          <?= $backend_ok ? 'Connected' : 'Unreachable' ?>
        </p>
        <small class="text-muted"><?= s($backend_msg) ?></small><br>
        <small class="text-muted"><?= s($backend_url) ?></small>
      </div>
    </div>
  </div>

  <!-- Database tables -->
  <?php $col = $tables_ok ? '#10b981' : '#ef4444'; ?>
  <div class="col-md-4">
    <div class="card h-100" style="border-left:4px solid <?= $col ?>;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span style="font-size:20px;"><?= $tables_ok ? '✅' : '❌' ?></span>
          <h5 class="mb-0">Database Tables</h5>
        </div>
        <p class="mb-2" style="color:<?= $col ?>;font-weight:600;">
          <?= array_sum($table_status) ?>/<?= count($required_tables) ?> tables present
        </p>
        <?php foreach ($table_status as $t => $exists): ?>
          <div style="font-size:11px;display:flex;justify-content:space-between;padding:1px 0;">
            <code style="color:#374151;"><?= $t ?></code>
            <span style="color:<?= $exists ? '#10b981' : '#ef4444' ?>;"><?= $exists ? '✓' : '✗ MISSING' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Event processing (24h) -->
  <?php $col = $events_ok ? '#10b981' : '#ef4444'; ?>
  <div class="col-md-4">
    <div class="card h-100" style="border-left:4px solid <?= $col ?>;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span style="font-size:20px;"><?= $events_ok ? '✅' : '⚠️' ?></span>
          <h5 class="mb-0">Events (Last 24h)</h5>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
          <?php foreach ([
              ['Total',   $ev_total,   '#6b7280'],
              ['Sent',    $ev_sent,    '#10b981'],
              ['Failed',  $ev_failed,  '#ef4444'],
              ['Pending', $ev_pending, '#f59e0b'],
          ] as [$l, $v, $c]): ?>
          <div style="background:#f8fafc;border-radius:6px;padding:6px 10px;">
            <div style="font-size:10px;color:#6b7280;"><?= $l ?></div>
            <div style="font-size:18px;font-weight:700;color:<?= $c ?>;"><?= $v ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="mb-0" style="color:<?= $col ?>;font-weight:600;">
          Failure rate: <?= $fail_rate ?>%
          <?= $fail_rate >= 10 ? ' — High!' : ' — OK' ?>
        </p>
        <?php if ($ev_all_failed > 0): ?>
          <small class="text-muted"><?= $ev_all_failed ?> total failed (all time)</small>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- .row -->

<!-- Quick actions -->
<?php if (!$backend_ok || !$tables_ok || !$events_ok): ?>
<div class="alert" style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin-bottom:24px;">
  <strong>⚠️ Issues detected.</strong>
  <?php if (!$backend_ok): ?>
    <span> Backend unreachable — check <a href="<?= (new moodle_url('/local/moodle_zoho_sync/ui/admin2/settings.php'))->out() ?>">Settings</a> for backend URL.</span>
  <?php endif; ?>
  <?php if (!$tables_ok): ?>
    <span> Missing DB tables — run <code>php admin/cli/upgrade.php --non-interactive</code>.</span>
  <?php endif; ?>
  <?php if (!$events_ok): ?>
    <span> High failure rate — <a href="<?= (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php', ['status' => 'failed']))->out() ?>">view failed events</a>.</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Scheduled Tasks -->
<div class="card mb-4">
  <div class="card-header"><strong>Scheduled Tasks</strong></div>
  <?php if (empty($tasks_info)): ?>
    <div class="card-body text-muted">No task information available.</div>
  <?php else: ?>
  <table class="mzi2-table">
    <thead>
      <tr><th>Task</th><th>Last Run</th><th>Next Run</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($tasks_info as $t): ?>
      <tr>
        <td><code><?= s($t['class']) ?></code></td>
        <td><?= $t['last_run'] ?></td>
        <td><?= $t['next_run'] ?></td>
        <td>
          <?php if ($t['disabled']): ?>
            <span class="mzi2-badge" style="background:#ef444420;color:#ef4444;border:1px solid #ef444440;">Disabled</span>
          <?php else: ?>
            <span class="mzi2-badge" style="background:#10b98120;color:#10b981;border:1px solid #10b98140;">Enabled</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Grade queue stats -->
<?php
$gq_ok = $dbman->table_exists(new xmldb_table('local_mzi_grade_queue'));
if ($gq_ok):
    $gq_total   = (int) $DB->count_records('local_mzi_grade_queue');
    $gq_synced  = (int) $DB->count_records('local_mzi_grade_queue', ['status' => 'SYNCED']);
    $gq_failed  = (int) $DB->count_records('local_mzi_grade_queue', ['status' => 'FAILED']);
    $gq_pending = $gq_total - $gq_synced - $gq_failed;
?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <strong>Grade Queue</strong>
    <a href="<?= (new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php'))->out() ?>"
       class="mzi2-btn" style="background:#3b82f6;color:#fff;padding:4px 12px;font-size:13px;">View All</a>
  </div>
  <div class="card-body">
    <div class="d-flex gap-4">
      <?php foreach ([
          ['Total',     $gq_total,   '#6b7280'],
          ['Synced',    $gq_synced,  '#10b981'],
          ['Failed',    $gq_failed,  '#ef4444'],
          ['Unsynced',  $gq_pending, '#f59e0b'],
      ] as [$l, $v, $c]): ?>
      <div style="text-align:center;">
        <div style="font-size:24px;font-weight:700;color:<?= $c ?>;"><?= $v ?></div>
        <div style="font-size:12px;color:#6b7280;"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

</div><!-- .mzi2-wrap -->

<?php
mzi2_nav_close();
echo $OUTPUT->footer();
