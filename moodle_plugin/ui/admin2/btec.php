<?php
/**
 * Admin Dashboard v2 — BTEC Templates
 *
 * @package   local_moodle_zoho_sync
 * @copyright 2026 ABC Horizon
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/nav.php');

admin_externalpage_setup('local_moodle_zoho_sync_btec_v2');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

$backend_url = get_config('local_moodle_zoho_sync', 'backend_url') ?: 'http://localhost:8001';
$api_token   = get_config('local_moodle_zoho_sync', 'api_token') ?: '';
$ssl_verify  = (bool) get_config('local_moodle_zoho_sync', 'ssl_verify');

// ── Params ────────────────────────────────────────────────────────────────
$page    = optional_param('page',   0,  PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$search  = optional_param('search', '',  PARAM_TEXT);
$action  = optional_param('action', '',  PARAM_ALPHA);
$del_id  = optional_param('delete', 0,   PARAM_INT);

// ── Actions ───────────────────────────────────────────────────────────────
global $DB;
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists(new xmldb_table('local_mzi_btec_templates'));

if ($table_exists && $del_id && confirm_sesskey()) {
    $DB->delete_records('local_mzi_btec_templates', ['id' => $del_id]);
    redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/btec.php'),
        'Template deleted.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Stats ─────────────────────────────────────────────────────────────────
$total_templates = 0;
$active_defns    = 0;
$last_sync       = null;
$grading_defns   = [];

if ($table_exists) {
    $total_templates = (int) $DB->count_records('local_mzi_btec_templates');
    $last_sync_row = $DB->get_record_sql("SELECT MAX(synced_at) AS ls FROM {local_mzi_btec_templates}");
    $last_sync = $last_sync_row->ls ?? null;

    $active_defns = (int) $DB->count_records_sql("
        SELECT COUNT(DISTINCT t.id)
        FROM {local_mzi_btec_templates} t
        INNER JOIN {grading_definitions} d ON t.definition_id = d.id
        WHERE d.status = 20
    ");

    // Full list with grading definition name
    $where  = '1=1';
    $params = [];
    if ($search !== '') {
        $like   = '%' . $DB->sql_like_escape($search) . '%';
        $where .= ' AND (t.unit_name LIKE :s1 OR t.zoho_unit_id LIKE :s2)';
        $params['s1'] = $like;
        $params['s2'] = $like;
    }

    $total_rows = (int) $DB->count_records_select('local_mzi_btec_templates', $where, $params);
    $templates  = $DB->get_records_sql("
        SELECT t.*, d.name AS defn_name, d.status AS defn_status
        FROM {local_mzi_btec_templates} t
        LEFT JOIN {grading_definitions} d ON t.definition_id = d.id
        WHERE $where
        ORDER BY t.synced_at DESC
        LIMIT $perpage OFFSET " . ($page * $perpage),
        $params
    );
} else {
    $total_rows = 0;
    $templates  = [];
}

$base_url = new moodle_url('/local/moodle_zoho_sync/ui/admin2/btec.php', ['search' => $search]);

// ── Render ────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
mzi2_nav_css();
mzi2_nav('btec');
mzi2_breadcrumb('BTEC Templates');
?>
<div class="mzi2-wrap" style="padding:20px;">
<h2 style="color:#1e293b;margin-bottom:20px;">BTEC Templates</h2>

<?php if (!$table_exists): ?>
<div class="alert alert-warning">
  Table <code>local_mzi_btec_templates</code> missing. Run <code>php admin/cli/upgrade.php --non-interactive</code>.
</div>
<?php else: ?>

<!-- KPI strip -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="mzi2-kpi-card" style="border-left:4px solid #3b82f6;">
      <div class="mzi2-kpi-label">Total Templates</div>
      <div class="mzi2-kpi-value" style="color:#3b82f6;"><?= $total_templates ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="mzi2-kpi-card" style="border-left:4px solid #10b981;">
      <div class="mzi2-kpi-label">Active Definitions</div>
      <div class="mzi2-kpi-value" style="color:#10b981;"><?= $active_defns ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="mzi2-kpi-card" style="border-left:4px solid #8b5cf6;">
      <div class="mzi2-kpi-label">Last Sync</div>
      <div class="mzi2-kpi-value" style="color:#8b5cf6;font-size:16px;">
        <?= $last_sync ? date('d M H:i', $last_sync) : 'Never' ?>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="mzi2-kpi-card" style="border-left:4px solid #f59e0b;">
      <div class="mzi2-kpi-label">Backend URL</div>
      <div style="font-size:12px;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?= s($backend_url) ?>
      </div>
    </div>
  </div>
</div>

<!-- Actions bar -->
<div class="d-flex gap-2 mb-3 align-items-center">
  <form method="get" action="" class="d-flex gap-2">
    <input type="text" name="search" class="form-control" value="<?= s($search) ?>" placeholder="Search unit name or Zoho unit ID…" style="min-width:280px;">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search): ?>
      <a href="<?= (new moodle_url('/local/moodle_zoho_sync/ui/admin2/btec.php'))->out() ?>"
         class="btn btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>
  <div class="ms-auto">
    <button id="sync-btn"
      onclick="mziSyncBtec(this)"
      data-url="<?= s(rtrim($backend_url, '/') . '/api/v1/btec/sync-templates') ?>"
      data-token="<?= s($api_token) ?>"
      data-ssl="<?= $ssl_verify ? '1' : '0' ?>"
      class="mzi2-btn" style="background:#8b5cf6;color:#fff;">
      ↻ Sync from Zoho
    </button>
  </div>
</div>

<div id="sync-result" class="mb-3" style="display:none;"></div>

<!-- Progress bar -->
<div id="sync-progress" style="display:none;margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;font-size:13px;color:#6b7280;margin-bottom:6px;">
    <span id="sync-progress-label">Connecting to Zoho…</span>
    <span id="sync-progress-pct">0%</span>
  </div>
  <div style="background:#e2e8f0;border-radius:999px;overflow:hidden;height:10px;">
    <div id="sync-progress-bar" style="height:10px;width:0%;background:linear-gradient(90deg,#8b5cf6,#6366f1);border-radius:999px;transition:width 0.4s ease;"></div>
  </div>
</div>

<!-- Table -->
<div style="overflow-x:auto;">
<table class="mzi2-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Unit Name</th>
      <th>Zoho Unit ID</th>
      <th>Definition</th>
      <th>Def. Status</th>
      <th>Synced At</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (empty($templates)): ?>
    <tr><td colspan="7" class="text-center text-muted py-4">
      No BTEC templates found.
      <?= !$search ? ' Click "Sync from Zoho" to import.' : '' ?>
    </td></tr>
  <?php else: ?>
    <?php foreach ($templates as $t):
        $def_active = ($t->defn_status ?? 0) == 20;
        $del_url = (new moodle_url('/local/moodle_zoho_sync/ui/admin2/btec.php',
            ['delete' => $t->id, 'sesskey' => sesskey()]))->out(false);
    ?>
    <tr>
      <td><?= $t->id ?></td>
      <td><?= s($t->unit_name) ?></td>
      <td><code style="font-size:11px;"><?= s($t->zoho_unit_id) ?></code></td>
      <td>
        <?php if ($t->defn_name): ?>
          <span title="Definition ID: <?= $t->definition_id ?>"><?= s($t->defn_name) ?></span>
        <?php else: ?>
          <span class="text-muted">— (ID: <?= $t->definition_id ?>)</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($t->defn_name): ?>
          <span class="mzi2-badge" style="<?= $def_active
            ? 'background:#10b98120;color:#10b981;border:1px solid #10b98140;'
            : 'background:#f59e0b20;color:#f59e0b;border:1px solid #f59e0b40;' ?>">
            <?= $def_active ? 'Active' : 'Draft' ?>
          </span>
        <?php else: ?>
          <span class="mzi2-badge" style="background:#ef444420;color:#ef4444;border:1px solid #ef444440;">No def.</span>
        <?php endif; ?>
      </td>
      <td style="font-size:12px;white-space:nowrap;"><?= $t->synced_at ? date('d M Y H:i', $t->synced_at) : '—' ?></td>
      <td>
        <a href="<?= $del_url ?>" class="mzi2-btn"
          style="background:#ef444420;color:#ef4444;border:1px solid #ef444440;padding:4px 10px;font-size:12px;"
          onclick="return confirm('Delete this template?')">✕</a>
      </td>
    </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>
</div>

<?php echo $OUTPUT->render(new paging_bar($total_rows, $page, $perpage, $base_url)); ?>

<?php endif; // table_exists ?>
</div><!-- .mzi2-wrap -->

<script>
// ── Progress bar helpers ──────────────────────────────────────────────────
var _mziProgressTimer = null;
var _mziProgressSteps = [
    [10,  'Connecting to backend…'],
    [25,  'Authenticating with Zoho…'],
    [40,  'Fetching BTEC units from Zoho…'],
    [60,  'Processing unit templates…'],
    [78,  'Saving to Moodle database…'],
    [90,  'Finalising…'],
];

function mziProgressStart() {
    var bar   = document.getElementById('sync-progress-bar');
    var lbl   = document.getElementById('sync-progress-label');
    var pct   = document.getElementById('sync-progress-pct');
    var wrap  = document.getElementById('sync-progress');
    wrap.style.display = 'block';
    bar.style.width = '0%';
    var step = 0;
    _mziProgressTimer = setInterval(function() {
        if (step >= _mziProgressSteps.length) { clearInterval(_mziProgressTimer); return; }
        bar.style.width  = _mziProgressSteps[step][0] + '%';
        lbl.textContent  = _mziProgressSteps[step][1];
        pct.textContent  = _mziProgressSteps[step][0] + '%';
        step++;
    }, 700);
}

function mziProgressFinish(success) {
    clearInterval(_mziProgressTimer);
    var bar  = document.getElementById('sync-progress-bar');
    var lbl  = document.getElementById('sync-progress-label');
    var pct  = document.getElementById('sync-progress-pct');
    bar.style.width = '100%';
    bar.style.background = success ? 'linear-gradient(90deg,#10b981,#059669)' : 'linear-gradient(90deg,#ef4444,#dc2626)';
    lbl.textContent = success ? 'Sync complete!' : 'Sync failed';
    pct.textContent = '100%';
    setTimeout(function() {
        document.getElementById('sync-progress').style.display = 'none';
        bar.style.background = 'linear-gradient(90deg,#8b5cf6,#6366f1)';
    }, 2000);
}
// ─────────────────────────────────────────────────────────────────────────

function mziSyncBtec(btn) {
    var url   = btn.dataset.url;
    var token = btn.dataset.token;
    var res   = document.getElementById('sync-result');

    btn.disabled     = true;
    btn.textContent  = '↻ Syncing…';
    res.style.display = 'none';
    mziProgressStart();

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': token ? 'Bearer ' + token : ''
        },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var ok = data.status === 'ok' || (typeof data.success === 'number' && data.success >= 0);
        mziProgressFinish(ok);
        res.style.display    = 'block';
        res.style.background = ok ? '#f0fdf4' : '#fff5f5';
        res.style.border     = '1px solid ' + (ok ? '#10b981' : '#ef4444');
        res.style.padding    = '12px 16px';
        res.style.borderRadius = '8px';
        var msg = data.message ||
            (typeof data.success !== 'undefined'
                ? '✓ Synced ' + data.success + ' templates' + (data.failed ? ', ' + data.failed + ' failed' : '') + '.'
                : JSON.stringify(data));
        res.innerHTML = '<strong>' + msg + '</strong>';
        btn.disabled = false;
        btn.textContent = '↻ Sync from Zoho';
        if (ok) setTimeout(function(){ location.reload(); }, 2500);
    })
    .catch(function(e) {
        mziProgressFinish(false);
        res.style.display    = 'block';
        res.style.background = '#fff5f5';
        res.style.border     = '1px solid #ef4444';
        res.style.padding    = '12px 16px';
        res.style.borderRadius = '8px';
        res.innerHTML = '<strong>Error: ' + e.message + '</strong>';
        btn.disabled = false;
        btn.textContent = '↻ Sync from Zoho';
    });
}
</script>

<?php
mzi2_nav_close();
echo $OUTPUT->footer();
