<?php
/**
 * Admin Dashboard v2 — Settings & Request Windows
 *
 * @package   local_moodle_zoho_sync
 * @copyright 2026 ABC Horizon
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/nav.php');

admin_externalpage_setup('local_moodle_zoho_sync_settings_v2');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

global $DB;

// ── Request-window helpers (shared with requests.php / submit_request.php) ─
// Each request type has up to 4 annual windows.
// Config keys (all under 'local_moodle_zoho_sync'):
//   rw_{slug}_{n}_date   — start date as 'YYYY-MM-DD'  (n = 1..4)
//   rw_{slug}_{n}_weeks  — duration: 1, 2, 3, or 4
// where slug = preg_replace('/[^a-z0-9]/', '_', strtolower($type))
//
// A window is OPEN when now >= start  AND  now < start + weeks*7days.

define('RW_TYPES', ['Enroll Next Semester', 'Class Drop']);
define('RW_WEEKS_OPTIONS', [1, 2, 3, 4]);
define('RW_NUM_WINDOWS',   4);

function rw_slug(string $type): string {
    return preg_replace('/[^a-z0-9]/', '_', strtolower($type));
}

/** Load all 4 windows for a type from Moodle config. Returns array of ['date'=>'YYYY-MM-DD','weeks'=>int]. */
function rw_load_windows(string $type): array {
    $slug = rw_slug($type);
    $out  = [];
    for ($n = 1; $n <= RW_NUM_WINDOWS; $n++) {
        $out[] = [
            'date'  => get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_date")  ?: '',
            'weeks' => (int)(get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_weeks") ?: 0),
        ];
    }
    return $out;
}

/** Returns true if the current time falls inside any configured window for $type. */
function rw_is_open(string $type): bool {
    $now = time();
    foreach (rw_load_windows($type) as $w) {
        if (!$w['date'] || !$w['weeks']) continue;
        $start = strtotime($w['date']);
        if ($start === false) continue;
        $end = $start + $w['weeks'] * 7 * 86400;
        if ($now >= $start && $now < $end) return true;
    }
    return false;
}

/** Returns the next open window's start timestamp, or null. */
function rw_next_open(string $type): ?int {
    $now  = time();
    $next = null;
    foreach (rw_load_windows($type) as $w) {
        if (!$w['date'] || !$w['weeks']) continue;
        $start = strtotime($w['date']);
        if ($start === false || $start <= $now) continue;
        if ($next === null || $start < $next) $next = $start;
    }
    return $next;
}

// ── POST handler — save request windows ──────────────────────────────────
if (optional_param('action', '', PARAM_ALPHANUMEXT) === 'save_windows' && confirm_sesskey()) {
    foreach (RW_TYPES as $wtype) {
        $slug = rw_slug($wtype);
        for ($n = 1; $n <= RW_NUM_WINDOWS; $n++) {
            $date  = optional_param("rw_{$slug}_{$n}_date",  '', PARAM_TEXT);
            $weeks = optional_param("rw_{$slug}_{$n}_weeks", 0,  PARAM_INT);
            // Sanitize
            $date  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
            $weeks = in_array((int)$weeks, RW_WEEKS_OPTIONS) ? (int)$weeks : 0;
            set_config("rw_{$slug}_{$n}_date",  $date,  'local_moodle_zoho_sync');
            set_config("rw_{$slug}_{$n}_weeks", $weeks, 'local_moodle_zoho_sync');
        }
    }
    redirect(new moodle_url('/local/moodle_zoho_sync/ui/admin2/settings.php'),
        'Request windows saved.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Plugin config values
$cfg_keys = [
    'backend_url'         => 'Backend URL',
    'api_token'           => 'API Token',
    'ssl_verify'          => 'SSL Verify',
    'max_retry_attempts'  => 'Max Retry Attempts',
    'retry_delay_minutes' => 'Retry Delay (min)',
    'log_retention_days'  => 'Log Retention (days)',
    'grade_sync_enabled'  => 'Grade Sync Enabled',
    'webhook_secret'      => 'Webhook Secret',
];
$cfg_values = [];
foreach ($cfg_keys as $k => $_) {
    $cfg_values[$k] = get_config('local_moodle_zoho_sync', $k);
}

// ── Render ────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
mzi2_nav_css();
mzi2_nav('settings');
mzi2_breadcrumb('Settings');
?>
<div class="mzi2-wrap" style="padding:20px;">

<!-- ══════════════════════════════════════════════════════════════════════
     Section 1: Request Windows
     ══════════════════════════════════════════════════════════════════════ -->
<style>
.rw-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:20px}
.rw-card-title{font-weight:700;font-size:15px;color:#1e293b;margin-bottom:14px;display:flex;align-items:center;gap:10px}
.rw-badge-open{background:#10b98120;color:#10b981;border:1px solid #10b98140;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.rw-badge-closed{background:#94a3b820;color:#94a3b8;border:1px solid #94a3b840;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.rw-windows-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
.rw-win-row{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px}
.rw-win-label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px}
.rw-win-row.active-win{border-color:#10b981;background:#f0fdf4}
.rw-win-row.upcoming-win{border-color:#3b82f6;background:#eff6ff}
</style>

<div class="mzi2-section mb-4">
  <h4 style="color:#1e293b;margin-bottom:6px;">Request Windows</h4>
  <p style="font-size:13px;color:#64748b;margin-bottom:18px">
    Set up to <strong>4 annual opening dates</strong> for each request type.
    Each window opens on the specified date and stays open for the selected number of weeks.
    Outside these windows, students cannot submit that request type.
  </p>

  <form method="post" action="">
    <input type="hidden" name="action"  value="save_windows">
    <input type="hidden" name="sesskey" value="<?= sesskey() ?>">

    <?php foreach (RW_TYPES as $wtype):
        $slug    = rw_slug($wtype);
        $is_open = rw_is_open($wtype);
        $wins    = rw_load_windows($wtype);
        $now     = time();
    ?>
    <div class="rw-card">
      <div class="rw-card-title">
        <?= s($wtype) ?>
        <span class="<?= $is_open ? 'rw-badge-open' : 'rw-badge-closed' ?>">
          <?= $is_open ? 'OPEN NOW' : 'CLOSED' ?>
        </span>
      </div>
      <div class="rw-windows-grid">
        <?php for ($n = 1; $n <= RW_NUM_WINDOWS; $n++):
            $w      = $wins[$n - 1];
            $w_date = $w['date'];   // 'YYYY-MM-DD' or ''
            $w_wks  = $w['weeks'];  // 0-4
            // Determine if this specific window is currently active or upcoming
            $win_class = '';
            if ($w_date && $w_wks) {
                $st = strtotime($w_date);
                $en = $st + $w_wks * 7 * 86400;
                if ($now >= $st && $now < $en)  $win_class = 'active-win';
                elseif ($st > $now)             $win_class = 'upcoming-win';
            }
        ?>
        <div class="rw-win-row <?= $win_class ?>">
          <div class="rw-win-label">Window <?= $n ?></div>
          <div class="mb-2">
            <label style="font-size:12px;color:#475569;display:block;margin-bottom:3px">Start Date</label>
            <input type="date" class="form-control form-control-sm"
                   name="rw_<?= $slug ?>_<?= $n ?>_date"
                   value="<?= s($w_date) ?>">
          </div>
          <div>
            <label style="font-size:12px;color:#475569;display:block;margin-bottom:3px">Duration (weeks open)</label>
            <select class="form-select form-select-sm" name="rw_<?= $slug ?>_<?= $n ?>_weeks">
              <option value="0" <?= $w_wks===0?'selected':'' ?>>— not set —</option>
              <?php foreach (RW_WEEKS_OPTIONS as $wopt): ?>
              <option value="<?= $wopt ?>" <?= $w_wks===$wopt?'selected':'' ?>>
                <?= $wopt ?> week<?= $wopt>1?'s':'' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($win_class === 'active-win'): ?>
            <div style="margin-top:7px;font-size:11px;color:#10b981;font-weight:700">
              <i class="fa fa-circle"></i> Open now · closes <?= date('M j', strtotime($w_date) + $w_wks*7*86400) ?>
            </div>
          <?php elseif ($win_class === 'upcoming-win'): ?>
            <div style="margin-top:7px;font-size:11px;color:#3b82f6;font-weight:700">
              <i class="fa fa-clock-o"></i> Opens <?= date('M j', strtotime($w_date)) ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endfor; ?>
      </div><!-- .rw-windows-grid -->
    </div>
    <?php endforeach; ?>

    <div class="mt-2">
      <button type="submit" class="mzi2-btn" style="background:#3b82f6;color:#fff;padding:10px 24px;">
        💾 Save Windows
      </button>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     Section 2: Plugin Configuration
     ══════════════════════════════════════════════════════════════════════ -->
<div class="mzi2-section mb-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 style="color:#1e293b;margin:0;">Plugin Configuration</h4>
    <a href="<?= (new moodle_url('/admin/settings.php', ['section' => 'local_moodle_zoho_sync']))->out() ?>"
       class="mzi2-btn" style="background:#f1f5f9;color:#3b82f6;border:1px solid #bfdbfe;padding:7px 16px;font-size:13px;">
      ⚙ Edit in Moodle Settings
    </a>
  </div>

  <div style="overflow-x:auto;">
  <table class="mzi2-table">
    <thead>
      <tr>
        <th style="width:240px;">Setting</th>
        <th>Current Value</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($cfg_keys as $k => $label):
        $val = $cfg_values[$k];
        // Mask tokens / secrets
        $display = $val;
        if (in_array($k, ['api_token', 'webhook_secret']) && $val) {
            $display = substr($val, 0, 6) . str_repeat('*', max(0, strlen($val) - 6));
        }
        $is_bool = in_array($k, ['ssl_verify', 'grade_sync_enabled']);
    ?>
    <tr>
      <td><code><?= s($label) ?></code></td>
      <td>
        <?php if ($is_bool): ?>
          <span class="mzi2-badge" style="<?= $val
            ? 'background:#10b98120;color:#10b981;border:1px solid #10b98140;'
            : 'background:#ef444420;color:#ef4444;border:1px solid #ef444440;' ?>">
            <?= $val ? 'Enabled' : 'Disabled' ?>
          </span>
        <?php elseif ($val !== false && $val !== ''): ?>
          <code style="font-size:13px;"><?= s($display) ?></code>
        <?php else: ?>
          <span class="text-muted" style="font-size:12px;">— not set —</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     Section 3: Quick Links
     ══════════════════════════════════════════════════════════════════════ -->
<div class="mzi2-section">
  <h4 style="color:#1e293b;margin-bottom:14px;">Quick Links</h4>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= (new moodle_url('/admin/settings.php', ['section' => 'local_moodle_zoho_sync']))->out() ?>"
       class="mzi2-btn" style="background:#f1f5f9;color:#1e293b;border:1px solid #e2e8f0;">
      ⚙ Moodle Plugin Settings
    </a>
    <a href="<?= (new moodle_url('/admin/scheduledtasks.php'))->out() ?>"
       class="mzi2-btn" style="background:#f1f5f9;color:#1e293b;border:1px solid #e2e8f0;">
      ⏱ Scheduled Tasks
    </a>
    <a href="<?= (new moodle_url('/local/moodle_zoho_sync/ui/admin2/health.php'))->out() ?>"
       class="mzi2-btn" style="background:#f1f5f9;color:#1e293b;border:1px solid #e2e8f0;">
      ♥ Health Check
    </a>
    <a href="<?= (new moodle_url('/local/moodle_zoho_sync/ui/admin2/event_logs.php'))->out() ?>"
       class="mzi2-btn" style="background:#f1f5f9;color:#1e293b;border:1px solid #e2e8f0;">
      📋 Event Logs
    </a>
    <a href="<?= (new moodle_url('/admin/purgecaches.php'))->out() ?>"
       class="mzi2-btn" style="background:#fef3c720;color:#d97706;border:1px solid #fcd34d;">
      🧹 Purge Caches
    </a>
  </div>
</div>

</div><!-- .mzi2-wrap -->

<?php
mzi2_nav_close();
echo $OUTPUT->footer();
