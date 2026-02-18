<?php
/**
 * Admin Dashboard for Moodle-Zoho Sync
 * 
 * Provides at-a-glance KPIs and quick actions for administrators
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/navigation.php');

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\config_manager;

require_login();
admin_externalpage_setup('local_moodle_zoho_sync_dashboard');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// Handle cleanup action
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($action === 'cleanup_old' && $confirm && confirm_sesskey()) {
    $retentiondays = config_manager::get('log_retention_days', 30);
    $deleted = event_logger::cleanup_old_logs($retentiondays);
    
    redirect(
        new moodle_url('/local/moodle_zoho_sync/ui/admin/dashboard.php'),
        get_string('cleanup_success', 'local_moodle_zoho_sync', $deleted),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Fetch KPIs
$logger = new event_logger();
$total_events = $logger->count_total_events();
$sent_events = $logger->count_events_by_status('sent');
$failed_events = $logger->count_events_by_status('failed');
$pending_events = $logger->count_events_by_status('pending');

// Calculate success rate
$success_rate = $total_events > 0 ? round(($sent_events / $total_events) * 100, 1) : 0;

// Get success rate color
$rate_color = $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'danger');

// Test backend connection
$backend_status = config_manager::test_backend_connection();
$is_online = $backend_status['success'] ?? false;

$PAGE->set_title(get_string('admin_dashboard', 'local_moodle_zoho_sync'));
$PAGE->set_heading(get_string('admin_dashboard', 'local_moodle_zoho_sync'));

echo $OUTPUT->header();

// Output navigation
mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('dashboard', 'Moodle-Zoho Integration', 'System Dashboard');
mzi_render_breadcrumb('Dashboard');
echo '<div class="mzi-content-wrapper">';
?>

<!-- Admin Dashboard Header -->
<div class="admin-dashboard-header mb-4">
    <h2><?php echo get_string('admin_dashboard_welcome', 'local_moodle_zoho_sync'); ?></h2>
    <p class="text-muted"><?php echo get_string('admin_dashboard_subtitle', 'local_moodle_zoho_sync'); ?></p>
</div>

<!-- KPI Cards Grid -->
<div class="row mb-4">
    <!-- Total Events Card -->
    <div class="col-md-3 mb-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <i class="fa fa-database fa-2x text-primary mb-2"></i>
                <h3 class="card-title mb-1"><?php echo $total_events; ?></h3>
                <p class="card-text text-muted small"><?php echo get_string('kpi_total_events', 'local_moodle_zoho_sync'); ?></p>
            </div>
        </div>
    </div>

    <!-- Sent Events Card -->
    <div class="col-md-3 mb-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="fa fa-check-circle fa-2x text-success mb-2"></i>
                <h3 class="card-title mb-1"><?php echo $sent_events; ?></h3>
                <p class="card-text text-muted small"><?php echo get_string('kpi_sent_events', 'local_moodle_zoho_sync'); ?></p>
            </div>
        </div>
    </div>

    <!-- Failed Events Card -->
    <div class="col-md-3 mb-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <i class="fa fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h3 class="card-title mb-1"><?php echo $failed_events; ?></h3>
                <p class="card-text text-muted small"><?php echo get_string('kpi_failed_events', 'local_moodle_zoho_sync'); ?></p>
            </div>
        </div>
    </div>

    <!-- Pending Events Card -->
    <div class="col-md-3 mb-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="fa fa-clock-o fa-2x text-warning mb-2"></i>
                <h3 class="card-title mb-1"><?php echo $pending_events; ?></h3>
                <p class="card-text text-muted small"><?php echo get_string('kpi_pending_events', 'local_moodle_zoho_sync'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Success Rate & Backend Status Row -->
<div class="row mb-4">
    <!-- Success Rate Card -->
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fa fa-line-chart"></i>
                    <?php echo get_string('success_rate', 'local_moodle_zoho_sync'); ?>
                </h5>
                <div class="progress mb-2" style="height: 30px;">
                    <div class="progress-bar bg-<?php echo $rate_color; ?>" role="progressbar" 
                         style="width: <?php echo $success_rate; ?>%;" 
                         aria-valuenow="<?php echo $success_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                        <strong><?php echo $success_rate; ?>%</strong>
                    </div>
                </div>
                <p class="mb-0 text-<?php echo $rate_color; ?>">
                    <?php 
                    if ($success_rate >= 90) {
                        echo '<i class="fa fa-thumbs-up"></i> ' . get_string('success_excellent', 'local_moodle_zoho_sync');
                    } elseif ($success_rate >= 70) {
                        echo '<i class="fa fa-info-circle"></i> ' . get_string('success_good', 'local_moodle_zoho_sync');
                    } else {
                        echo '<i class="fa fa-warning"></i> ' . get_string('success_needs_attention', 'local_moodle_zoho_sync');
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Backend Connection Status Card -->
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fa fa-server"></i>
                    <?php echo get_string('backend_status', 'local_moodle_zoho_sync'); ?>
                </h5>
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <?php if ($is_online): ?>
                            <span class="badge badge-success badge-lg">
                                <i class="fa fa-check"></i> <?php echo get_string('status_online', 'local_moodle_zoho_sync'); ?>
                            </span>
                            <p class="mb-0 mt-2 text-muted small">
                                <?php echo get_string('backend_healthy', 'local_moodle_zoho_sync'); ?>
                            </p>
                        <?php else: ?>
                            <span class="badge badge-danger badge-lg">
                                <i class="fa fa-times"></i> <?php echo get_string('status_offline', 'local_moodle_zoho_sync'); ?>
                            </span>
                            <p class="mb-0 mt-2 text-danger small">
                                <?php echo get_string('backend_unreachable', 'local_moodle_zoho_sync'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="testBackendConnection()">
                        <i class="fa fa-refresh"></i> <?php echo get_string('test_connection', 'local_moodle_zoho_sync'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Card -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">
            <i class="fa fa-bolt"></i> <?php echo get_string('quick_actions', 'local_moodle_zoho_sync'); ?>
        </h5>
        <div class="btn-group-vertical btn-group-lg" role="group" style="width: 100%;">
            <?php if ($failed_events > 0): ?>
                <button type="button" class="btn btn-warning mb-2" onclick="retryFailedEvents()">
                    <i class="fa fa-repeat"></i>
                    <?php echo get_string('retry_failed_events', 'local_moodle_zoho_sync', $failed_events); ?>
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary mb-2" disabled>
                    <i class="fa fa-check"></i>
                    <?php echo get_string('no_failed_events', 'local_moodle_zoho_sync'); ?>
                </button>
            <?php endif; ?>
            
            <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'); ?>" 
               class="btn btn-outline-primary mb-2">
                <i class="fa fa-list"></i>
                <?php echo get_string('view_event_logs', 'local_moodle_zoho_sync'); ?>
            </a>
            
            <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/grade_queue_monitor.php'); ?>" 
               class="btn btn-outline-primary mb-2">
                <i class="fa fa-bar-chart"></i>
                Grade Operations Monitor
            </a>
            
            <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/statistics.php'); ?>" 
               class="btn btn-outline-info mb-2">
                <i class="fa fa-line-chart"></i>
                <?php echo get_string('view_statistics', 'local_moodle_zoho_sync'); ?>
            </a>
            
            <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/health_check.php'); ?>" 
               class="btn btn-outline-success mb-2">
                <i class="fa fa-heartbeat"></i>
                <?php echo get_string('health_check', 'local_moodle_zoho_sync'); ?>
            </a>
            
            <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/btec_templates.php'); ?>" 
               class="btn btn-outline-info mb-2">
                <i class="fa fa-file-text"></i>
                BTEC Templates
            </a>
            
            <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/sync_management.php'); ?>" 
               class="btn btn-outline-primary mb-2">
                <i class="fa fa-cogs"></i>
                Sync Management
            </a>
            
            <button type="button" class="btn btn-outline-danger mb-2" onclick="cleanupOldLogs()">
                <i class="fa fa-trash"></i>
                <?php echo get_string('cleanup_old_logs', 'local_moodle_zoho_sync'); ?>
            </button>
            
            <a href="<?php echo new moodle_url('/admin/settings.php', ['section' => 'local_moodle_zoho_sync']); ?>" 
               class="btn btn-outline-secondary mb-2">
                <i class="fa fa-cog"></i>
                <?php echo get_string('plugin_settings', 'local_moodle_zoho_sync'); ?>
            </a>
            
            <a href="<?php echo new moodle_url('/admin/tasklogs.php'); ?>" 
               class="btn btn-outline-secondary">
                <i class="fa fa-clock-o"></i>
                <?php echo get_string('scheduled_task_logs', 'local_moodle_zoho_sync'); ?>
            </a>
        </div>
    </div>
</div>

<!-- Spinner for async actions -->
<div id="action-spinner" class="text-center" style="display: none;">
    <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
    <p class="mt-2"><?php echo get_string('processing', 'local_moodle_zoho_sync'); ?></p>
</div>

<style>
.badge-lg {
    font-size: 1rem;
    padding: 0.5rem 1rem;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.admin-dashboard-header h2 {
    font-weight: 300;
    color: #333;
}
</style>

<script>
function retryFailedEvents() {
    if (!confirm('<?php echo get_string('confirm_retry_all', 'local_moodle_zoho_sync'); ?>')) {
        return;
    }
    
    const spinner = document.getElementById('action-spinner');
    spinner.style.display = 'block';
    
    fetch(M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax/retry_failed.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            sesskey: M.cfg.sesskey
        })
    })
    .then(response => response.json())
    .then(data => {
        spinner.style.display = 'none';
        if (data.success) {
            alert('<?php echo get_string('retry_initiated', 'local_moodle_zoho_sync'); ?>');
            location.reload();
        } else {
            alert('<?php echo get_string('retry_failed', 'local_moodle_zoho_sync'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        spinner.style.display = 'none';
        alert('<?php echo get_string('error_occurred', 'local_moodle_zoho_sync'); ?>');
    });
}

function testBackendConnection() {
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?php echo get_string('testing', 'local_moodle_zoho_sync'); ?>';
    btn.disabled = true;
    
    fetch(M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/ajax/test_connection.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            sesskey: M.cfg.sesskey
        })
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        if (data.success) {
            alert('<?php echo get_string('connection_success', 'local_moodle_zoho_sync'); ?>');
        } else {
            alert('<?php echo get_string('connection_failed', 'local_moodle_zoho_sync'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
        alert('<?php echo get_string('error_occurred', 'local_moodle_zoho_sync'); ?>');
    });
}

function cleanupOldLogs() {
    const retentionDays = <?php echo config_manager::get('log_retention_days', 30); ?>;
    if (!confirm('<?php echo get_string('confirm_cleanup', 'local_moodle_zoho_sync'); ?>'.replace('{$a}', retentionDays))) {
        return;
    }
    
    const url = M.cfg.wwwroot + '/local/moodle_zoho_sync/ui/admin/dashboard.php?action=cleanup_old&confirm=1&sesskey=' + M.cfg.sesskey;
    window.location.href = url;
}
</script>

<?php
echo '</div>'; // Close mzi-content-wrapper
echo '</div>'; // Close mzi-page-container
echo $OUTPUT->footer();
