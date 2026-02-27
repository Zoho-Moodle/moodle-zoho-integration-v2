<?php
/**
 * Admin Dashboard v2 — Event Logs
 *
 * @package   local_moodle_zoho_sync
 * @copyright 2026 ABC Horizon
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/nav.php');

admin_externalpage_setup('local_moodle_zoho_sync_event_logs');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// ── GET params ────────────────────────────────────────────────────────────
$page      = optional_param('page',      0,    PARAM_INT);
$perpage   = optional_param('perpage',   50,   PARAM_INT);
$status    = optional_param('status',    '',   PARAM_ALPHA);
$evtype    = optional_param('evtype',    '',   PARAM_ALPHANUMEXT);
$search    = optional_param('search',    '',   PARAM_TEXT);
$since     = optional_param('since',     '7d', PARAM_ALPHA);  // 1h 24h 7d 30d all
$retry_id  = optional_param('retry',     0,    PARAM_INT);
$del_id    = optional_param('delete',    0,    PARAM_INT);

// ── Actions ───────────────────────────────────────────────────────────────
if ($retry_id && confirm_sesskey()) {
    $ev = $DB->get_record('local_mzi_event_log', ['id' => $retry_id], '*', MUST_EXIST);
    $ev->status        = 'retrying';
    $ev->next_retry_at = time();
    $ev->retry_count   = 0;
    $ev->timemodified  = time();
    $DB->update_record('local_mzi_event_log', $ev);
    redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php',
        ['status' => $status, 'evtype' => $evtype, 'since' => $since]),
        'Event queued for retry.', null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($del_id && confirm_sesskey()) {
    $DB->delete_records('local_mzi_event_log', ['id' => $del_id]);
    redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php',
        ['status' => $status, 'evtype' => $evtype, 'since' => $since]),
        'Event deleted.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Bulk actions ─────────────────────────────────────────────────────────
$bulk_action = optional_param('bulk_action', '', PARAM_ALPHA);
if ($bulk_action && confirm_sesskey()) {
    if ($bulk_action === 'cleanup_sent') {
        $cutoff = time() - (30 * DAYSECS);
        $deleted = $DB->delete_records_select('local_mzi_event_log', "status='sent' AND timecreated < ?", [$cutoff]);
        redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php'),
            "Cleaned up {$deleted} old sent events.", null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($bulk_action === 'retry_all_failed') {
        $DB->execute("UPDATE {local_mzi_event_log} SET status='retrying', retry_count=0, next_retry_at=?, timemodified=?
                      WHERE status='failed'", [time(), time()]);
        redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php'),
            'All failed events queued for retry.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ── Time filter ───────────────────────────────────────────────────────────
$since_map = ['1h' => 3600, '24h' => 86400, '7d' => 604800, '30d' => 2592000];
$since_ts  = isset($since_map[$since]) ? (time() - $since_map[$since]) : 0;

// ── Build query ───────────────────────────────────────────────────────────
$where  = '1=1';
$params = [];
if ($status) {
    $where   .= ' AND status = :status';
    $params['status'] = $status;
}
if ($evtype) {
    $where   .= ' AND event_type = :evtype';
    $params['evtype'] = $evtype;
}
if ($since_ts) {
    $where   .= ' AND timecreated >= :since_ts';
    $params['since_ts'] = $since_ts;
}
if ($search !== '') {
    $where   .= ' AND (related_id LIKE :s1 OR event_type LIKE :s2 OR event_data LIKE :s3)';
    $like     = '%' . $DB->sql_like_escape($search) . '%';
    $params['s1'] = $like;
    $params['s2'] = $like;
    $params['s3'] = $like;
}

$total = (int) $DB->count_records_select('local_mzi_event_log', $where, $params);
$events = $DB->get_records_select(
    'local_mzi_event_log', $where, $params,
    'timecreated DESC',
    '*',
    $page * $perpage,
    $perpage
);

// ── KPI counts (full table, no time filter for overview) ──────────────────
$kpi_total   = (int) $DB->count_records('local_mzi_event_log');
$kpi_sent    = (int) $DB->count_records('local_mzi_event_log', ['status' => 'sent']);
$kpi_failed  = (int) $DB->count_records('local_mzi_event_log', ['status' => 'failed']);
$kpi_pending = (int) $DB->count_records_select('local_mzi_event_log', "status IN ('pending','retrying')");

$base_url = new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php',
    ['status' => $status, 'evtype' => $evtype, 'since' => $since, 'search' => $search]);

// ── Distinct event types for filter ────────────────────────────────────────
$all_types = $DB->get_records_sql(
    "SELECT DISTINCT event_type FROM {local_mzi_event_log} ORDER BY event_type"
);

// ── Render ─────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
mzi2_nav_css();
mzi2_nav('events');
mzi2_breadcrumb('Event Logs');
?>
<div class="mzi2-wrap" style="padding:20px;">

<!-- KPI strip -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
      ['label'=>'Total Events',  'val'=>number_format($kpi_total),   'color'=>'#3b82f6', 'filter'=>''],
      ['label'=>'Sent',          'val'=>number_format($kpi_sent),    'color'=>'#10b981', 'filter'=>'sent'],
      ['label'=>'Failed',        'val'=>number_format($kpi_failed),  'color'=>'#ef4444', 'filter'=>'failed'],
      ['label'=>'Pending/Retry', 'val'=>number_format($kpi_pending), 'color'=>'#f59e0b', 'filter'=>'pending'],
  ];
  foreach ($kpis as $k):
      $href = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php', ['status' => $k['filter'], 'since' => $since]))->out(false);
  ?>
  <div class="col-6 col-md-3">
    <a href="<?= $href ?>" style="text-decoration:none;">
      <div class="mzi2-kpi-card" style="border-left:4px solid <?= $k['color'] ?>;">
        <div class="mzi2-kpi-label"><?= $k['label'] ?></div>
        <div class="mzi2-kpi-value" style="color:<?= $k['color'] ?>;"><?= $k['val'] ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters + actions bar -->
<form method="get" action="" class="card p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label mb-1">Status</label>
      <select name="status" class="form-select form-control">
        <option value="">All statuses</option>
        <?php foreach (['sent','failed','pending','retrying'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label mb-1">Event Type</label>
      <select name="evtype" class="form-select form-control">
        <option value="">All types</option>
        <?php foreach ($all_types as $t): ?>
          <option value="<?= s($t->event_type) ?>" <?= $evtype === $t->event_type ? 'selected' : '' ?>><?= s($t->event_type) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1">Time range</label>
      <select name="since" class="form-select form-control">
        <?php foreach (['1h'=>'Last 1 hour','24h'=>'Last 24 hours','7d'=>'Last 7 days','30d'=>'Last 30 days','all'=>'All time'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= $since === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label mb-1">Search</label>
      <input type="text" name="search" class="form-control" value="<?= s($search) ?>" placeholder="User ID, type, payload…">
    </div>
    <div class="col-md-1 d-flex gap-1">
      <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
  </div>
</form>

<!-- Bulk actions -->
<div class="d-flex gap-2 mb-3 align-items-center">
  <span class="text-muted"><?= number_format($total) ?> results</span>
  <div class="ms-auto d-flex gap-2">
    <form method="post" action="">
      <?= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'bulk_action','value'=>'retry_all_failed']) ?>
      <?= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]) ?>
      <button type="submit" class="mzi2-btn mzi2-btn-warning"
        onclick="return confirm('Retry all failed events?')">↺ Retry All Failed</button>
    </form>
    <form method="post" action="">
      <?= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'bulk_action','value'=>'cleanup_sent']) ?>
      <?= html_writer::empty_tag('input', ['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]) ?>
      <button type="submit" class="mzi2-btn" style="background:#6b7280;color:#fff;"
        onclick="return confirm('Delete sent events older than 30 days?')">🗑 Cleanup Old</button>
    </form>
  </div>
</div>

<!-- Events table -->
<div class="mzi2-table-wrap" style="overflow-x:auto;">
<table class="mzi2-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Time</th>
      <th>Type</th>
      <th>User ID</th>
      <th>Status</th>
      <th>Retries</th>
      <th>Next Retry</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($events)): ?>
    <tr><td colspan="8" class="text-center text-muted py-4">No events found.</td></tr>
  <?php else: ?>
    <?php foreach ($events as $ev):
        $status_colors = [
            'sent'     => '#10b981',
            'failed'   => '#ef4444',
            'pending'  => '#f59e0b',
            'retrying' => '#3b82f6',
        ];
        $sc = $status_colors[$ev->status] ?? '#6b7280';
        $retry_url = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php',
            ['retry' => $ev->id, 'sesskey' => sesskey(), 'status' => $status, 'evtype' => $evtype, 'since' => $since]))->out(false);
        $del_url = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php',
            ['delete' => $ev->id, 'sesskey' => sesskey(), 'status' => $status, 'evtype' => $evtype, 'since' => $since]))->out(false);
    ?>
    <tr style="cursor:default;">
      <td><?= $ev->id ?></td>
      <td style="white-space:nowrap;font-size:13px;"><?= date('d M H:i:s', $ev->timecreated) ?></td>
      <td><code style="font-size:12px;"><?= s($ev->event_type) ?></code></td>
      <td><?= $ev->related_id ? $ev->related_id : '<span class="text-muted">—</span>' ?></td>
      <td>
        <span class="mzi2-badge" style="background:<?= $sc ?>20;color:<?= $sc ?>;border:1px solid <?= $sc ?>40;">
          <?= ucfirst($ev->status) ?>
        </span>
      </td>
      <td><?= (int)($ev->retry_count ?? 0) ?></td>
      <td style="font-size:12px;">
        <?= (!empty($ev->next_retry_at) && $ev->next_retry_at > 0)
            ? date('d M H:i', $ev->next_retry_at)
            : '<span class="text-muted">—</span>' ?>
      </td>
      <td style="white-space:nowrap;">
        <?php if ($ev->status !== 'sent'): ?>
          <a href="<?= $retry_url ?>" class="mzi2-btn" style="background:#3b82f6;color:#fff;padding:4px 10px;font-size:12px;"
            onclick="return confirm('Retry this event?')">↺</a>
        <?php endif; ?>
        <a href="<?= $del_url ?>" class="mzi2-btn" style="background:#ef444420;color:#ef4444;border:1px solid #ef444440;padding:4px 10px;font-size:12px;"
          onclick="return confirm('Delete this event?')">✕</a>
        <button onclick="mziTogglePayload(<?= $ev->id ?>)"
          class="mzi2-btn" style="background:#f3f4f6;color:#374151;padding:4px 10px;font-size:12px;">⋯</button>
      </td>
    </tr>
    <!-- Payload detail row -->
    <tr id="payload-<?= $ev->id ?>" style="display:none;background:#f8fafc;">
      <td colspan="8" style="padding:12px 20px;">
        <?php if (!empty($ev->last_error)): ?>
          <div style="color:#ef4444;margin-bottom:8px;"><strong>Error:</strong> <?= s($ev->last_error) ?></div>
        <?php endif; ?>
        <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;overflow:auto;max-height:300px;white-space:pre-wrap;"><?php
            $payload = $ev->event_data ?? '';
            $decoded = json_decode($payload, true);
            echo $decoded ? s(json_encode($decoded, JSON_PRETTY_PRINT)) : s($payload);
        ?></pre>
      </td>
    </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<?php
$paging_bar = new paging_bar($total, $page, $perpage, $base_url);
echo $OUTPUT->render($paging_bar);
?>

</div><!-- .mzi2-wrap -->

<script>
function mziTogglePayload(id) {
    var row = document.getElementById('payload-' + id);
    row.style.display = (row.style.display === 'none') ? 'table-row' : 'none';
}
</script>

<?php
mzi2_nav_close();
echo $OUTPUT->footer();
