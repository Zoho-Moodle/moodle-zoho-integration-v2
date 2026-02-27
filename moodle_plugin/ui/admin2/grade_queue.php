<?php
/**
 * Admin Dashboard v2 — Grade Queue Monitor
 *
 * @package   local_moodle_zoho_sync
 * @copyright 2026 ABC Horizon
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/nav.php');

admin_externalpage_setup('local_moodle_zoho_sync_grade_v2');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// ── Params ────────────────────────────────────────────────────────────────
$page      = optional_param('page',      0,        PARAM_INT);
$perpage   = optional_param('perpage',   50,       PARAM_INT);
$status    = optional_param('status',    '',       PARAM_ALPHANUMEXT);
$search    = optional_param('search',    '',       PARAM_TEXT);
$since     = optional_param('since',     '7d',     PARAM_ALPHA);
$action    = optional_param('action',    '',       PARAM_ALPHA);
$rec_id    = optional_param('id',        0,        PARAM_INT);

// ── Actions ───────────────────────────────────────────────────────────────
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists(new xmldb_table('local_mzi_grade_queue'));

if ($table_exists) {
    if ($action === 'retry' && $rec_id && confirm_sesskey()) {
        $rec = $DB->get_record('local_mzi_grade_queue', ['id' => $rec_id], '*', MUST_EXIST);
        $rec->status        = 'PENDING';
        $rec->error_message = null;
        $rec->retry_count   = 0;
        $rec->timemodified  = time();
        $DB->update_record('local_mzi_grade_queue', $rec);
        redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php'),
            'Record queued for retry.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'retry_all_failed' && confirm_sesskey()) {
        $DB->execute("UPDATE {local_mzi_grade_queue} SET status='PENDING', retry_count=0, error_message=NULL, timemodified=? WHERE status='FAILED'", [time()]);
        redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php'),
            'All failed records queued for retry.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'export' && confirm_sesskey()) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="grade_queue_' . date('Y-m-d_His') . '.csv"');
        $recs = $DB->get_records('local_mzi_grade_queue', null, 'timemodified DESC', '*', 0, 5000);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Composite Key', 'Grade ID', 'Student ID', 'Status', 'Zoho Record ID', 'Error', 'Retries', 'Created', 'Modified']);
        foreach ($recs as $r) {
            fputcsv($out, [
                $r->id, $r->composite_key, $r->grade_id, $r->student_id,
                $r->status, $r->zoho_record_id ?? '', $r->error_message ?? '',
                $r->retry_count, date('Y-m-d H:i:s', $r->timecreated), date('Y-m-d H:i:s', $r->timemodified),
            ]);
        }
        fclose($out);
        exit;
    }
}

// ── KPI counts ────────────────────────────────────────────────────────────
$kpi = ['total' => 0, 'synced' => 0, 'failed' => 0, 'pending' => 0, 'observer' => 0, 'scheduled' => 0];
if ($table_exists) {
    $kpi['total']     = (int) $DB->count_records('local_mzi_grade_queue');
    $kpi['synced']    = (int) $DB->count_records('local_mzi_grade_queue', ['status' => 'SYNCED']);
    $kpi['failed']    = (int) $DB->count_records('local_mzi_grade_queue', ['status' => 'FAILED']);
    $kpi['pending']   = (int) $DB->count_records('local_mzi_grade_queue', ['status' => 'PENDING']);
}

// ── Time filter ───────────────────────────────────────────────────────────
$since_map = ['1h' => 3600, '24h' => 86400, '7d' => 604800, '30d' => 2592000];
$since_ts  = isset($since_map[$since]) ? (time() - $since_map[$since]) : 0;

// ── Build query ───────────────────────────────────────────────────────────
$records = [];
$total = 0;
if ($table_exists) {
    $where  = '1=1';
    $params = [];
    if ($status) {
        $where .= ' AND status = :status';
        $params['status'] = $status;
    }
    if ($since_ts) {
        $where .= ' AND timemodified >= :since_ts';
        $params['since_ts'] = $since_ts;
    }
    if ($search !== '') {
        $like = '%' . $DB->sql_like_escape($search) . '%';
        $where .= ' AND (composite_key LIKE :s1 OR student_id LIKE :s2 OR zoho_record_id LIKE :s3)';
        $params['s1'] = $like; $params['s2'] = $like; $params['s3'] = $like;
    }
    $total   = (int) $DB->count_records_select('local_mzi_grade_queue', $where, $params);
    $records = $DB->get_records_select('local_mzi_grade_queue', $where, $params, 'timemodified DESC', '*', $page * $perpage, $perpage);
}

$base_url = new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php',
    ['status' => $status, 'since' => $since, 'search' => $search]);

// ── Render ────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
mzi2_nav_css();
mzi2_nav('grades');
mzi2_breadcrumb('Grade Queue');
?>
<div class="mzi2-wrap" style="padding:20px;">

<?php if (!$table_exists): ?>
  <div class="alert alert-warning">
    Table <code>local_mzi_grade_queue</code> does not exist. Run <code>php admin/cli/upgrade.php --non-interactive</code>.
  </div>
<?php else: ?>

<!-- KPI strip -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
      ['Total',   $kpi['total'],   '#3b82f6', ''],
      ['Synced',  $kpi['synced'],  '#10b981', 'SYNCED'],
      ['Failed',  $kpi['failed'],  '#ef4444', 'FAILED'],
      ['Pending', $kpi['pending'], '#f59e0b', 'PENDING'],
  ];
  foreach ($kpis as [$l, $v, $c, $f]):
      $href = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php', ['status' => $f, 'since' => $since]))->out(false);
  ?>
  <div class="col-6 col-md-3">
    <a href="<?= $href ?>" style="text-decoration:none;">
      <div class="mzi2-kpi-card" style="border-left:4px solid <?= $c ?>;">
        <div class="mzi2-kpi-label"><?= $l ?></div>
        <div class="mzi2-kpi-value" style="color:<?= $c ?>;"><?= number_format($v) ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters row -->
<form method="get" action="" class="card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label mb-1">Status</label>
      <select name="status" class="form-select form-control">
        <option value="">All</option>
        <?php foreach (['SYNCED','FAILED','PENDING'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1">Time window</label>
      <select name="since" class="form-select form-control">
        <?php foreach (['1h'=>'1h','24h'=>'24h','7d'=>'7d','30d'=>'30d','all'=>'All time'] as $k=>$l): ?>
          <option value="<?= $k ?>" <?= $since === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label mb-1">Search (key / student ID / zoho ID)</label>
      <input type="text" name="search" class="form-control" value="<?= s($search) ?>">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary">Filter</button>
    </div>
  </div>
</form>

<!-- Actions bar -->
<div class="d-flex gap-2 mb-3 align-items-center">
  <span class="text-muted"><?= number_format($total) ?> results</span>
  <div class="ms-auto d-flex gap-2">
    <?php if ($kpi['failed'] > 0): ?>
    <form method="post" action="">
      <input type="hidden" name="action"  value="retry_all_failed">
      <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
      <button type="submit" class="mzi2-btn mzi2-btn-warning"
        onclick="return confirm('Retry all <?= $kpi['failed'] ?> failed records?')">↺ Retry All Failed</button>
    </form>
    <?php endif; ?>
    <a href="<?= (new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php', ['action' => 'export', 'sesskey' => sesskey()]))->out(false) ?>"
       class="mzi2-btn" style="background:#f3f4f6;color:#374151;">↓ Export CSV</a>
  </div>
</div>

<!-- Table -->
<div style="overflow-x:auto;">
<table class="mzi2-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Composite Key</th>
      <th>Student ID</th>
      <th>Zoho Record ID</th>
      <th>Status</th>
      <th>Retries</th>
      <th>Modified</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($records)): ?>
    <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
  <?php else: ?>
    <?php foreach ($records as $r):
        $sc = ['SYNCED'=>'#10b981','FAILED'=>'#ef4444','PENDING'=>'#f59e0b'][$r->status] ?? '#6b7280';
        $retry_url = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/grade_queue.php',
            ['action'=>'retry','id'=>$r->id,'sesskey'=>sesskey()]))->out(false);
    ?>
    <tr>
      <td><?= $r->id ?></td>
      <td><code style="font-size:11px;"><?= s($r->composite_key) ?></code></td>
      <td><?= s($r->student_id) ?></td>
      <td><?= $r->zoho_record_id ? s($r->zoho_record_id) : '<span class="text-muted">—</span>' ?></td>
      <td>
        <span class="mzi2-badge" style="background:<?= $sc ?>20;color:<?= $sc ?>;border:1px solid <?= $sc ?>40;">
          <?= $r->status ?>
        </span>
      </td>
      <td><?= (int)($r->retry_count ?? 0) ?></td>
      <td style="font-size:12px;white-space:nowrap;"><?= date('d M H:i', $r->timemodified) ?></td>
      <td>
        <?php if ($r->status !== 'SYNCED'): ?>
          <a href="<?= $retry_url ?>" class="mzi2-btn" style="background:#3b82f6;color:#fff;padding:4px 10px;font-size:12px;"
            onclick="return confirm('Retry?')">↺</a>
        <?php endif; ?>
        <?php if (!empty($r->error_message)): ?>
          <button onclick="mziToggleErr(<?= $r->id ?>)"
            class="mzi2-btn" style="background:#ef444420;color:#ef4444;border:1px solid #ef444440;padding:4px 10px;font-size:12px;">⚠</button>
        <?php endif; ?>
      </td>
    </tr>
    <?php if (!empty($r->error_message)): ?>
    <tr id="err-<?= $r->id ?>" style="display:none;background:#fff5f5;">
      <td colspan="8" style="padding:10px 20px;">
        <strong style="color:#ef4444;">Error:</strong>
        <pre style="margin:4px 0 0;font-size:12px;color:#374151;white-space:pre-wrap;"><?= s($r->error_message) ?></pre>
      </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?php echo $OUTPUT->render(new paging_bar($total, $page, $perpage, $base_url)); ?>

<?php endif; // table_exists ?>
</div><!-- .mzi2-wrap -->

<script>
function mziToggleErr(id) {
    var r = document.getElementById('err-' + id);
    r.style.display = (r.style.display === 'none') ? 'table-row' : 'none';
}
</script>

<?php
mzi2_nav_close();
echo $OUTPUT->footer();
