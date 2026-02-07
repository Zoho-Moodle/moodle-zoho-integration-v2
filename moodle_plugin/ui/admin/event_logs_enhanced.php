<?php
/**
 * Enhanced Event Logs page with Filters and Better UX
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_moodle_zoho_sync\event_logger;

require_login();
admin_externalpage_setup('local_moodle_zoho_sync_logs');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:viewlogs', $context);

// Page parameters for filters and pagination
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$filters = [
    'event_type' => optional_param('event_type', '', PARAM_ALPHA),
    'status' => optional_param('status', '', PARAM_ALPHA),
    'date_from' => optional_param('date_from', '', PARAM_TEXT),
    'date_to' => optional_param('date_to', '', PARAM_TEXT)
];

$PAGE->set_title(get_string('event_logs', 'local_moodle_zoho_sync'));
$PAGE->set_heading(get_string('event_logs', 'local_moodle_zoho_sync'));

// Get filtered and paginated events
$result = event_logger::get_events_paginated($filters, $page, $perpage);
$events = $result['events'];
$totalcount = $result['total'];

echo $OUTPUT->header();
?>

<style>
.status-badge {
    padding: 5px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}
.status-sent { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.status-failed { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
.status-retrying { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

.event-row {
    border-bottom: 1px solid #dee2e6;
    padding: 12px;
    transition: background 0.2s;
}
.event-row:hover {
    background: #f8f9fa;
}
.event-details {
    display: none;
    background: #f1f3f5;
    padding: 15px;
    margin-top: 10px;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}
.event-details pre {
    background: #fff;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
    max-height: 300px;
    overflow: auto;
}
.filter-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.copy-btn {
    cursor: pointer;
    padding: 2px 8px;
    font-size: 0.85rem;
}
</style>

<!-- Filters Card -->
<div class="filter-card">
    <h5><i class="fa fa-filter"></i> <?php echo get_string('filters', 'local_moodle_zoho_sync'); ?></h5>
    <form method="get" action="<?php echo $PAGE->url->out_omit_querystring(); ?>" class="form-inline">
        <div class="row" style="width: 100%;">
            <!-- Event Type Filter -->
            <div class="col-md-3 mb-2">
                <label class="mr-2"><?php echo get_string('event_type', 'local_moodle_zoho_sync'); ?>:</label>
                <select name="event_type" class="form-control form-control-sm">
                    <option value=""><?php echo get_string('all', 'local_moodle_zoho_sync'); ?></option>
                    <option value="user_created" <?php echo $filters['event_type'] == 'user_created' ? 'selected' : ''; ?>>
                        <?php echo get_string('user_created', 'local_moodle_zoho_sync'); ?>
                    </option>
                    <option value="user_updated" <?php echo $filters['event_type'] == 'user_updated' ? 'selected' : ''; ?>>
                        <?php echo get_string('user_updated', 'local_moodle_zoho_sync'); ?>
                    </option>
                    <option value="enrollment_created" <?php echo $filters['event_type'] == 'enrollment_created' ? 'selected' : ''; ?>>
                        <?php echo get_string('enrollment_created', 'local_moodle_zoho_sync'); ?>
                    </option>
                    <option value="grade_updated" <?php echo $filters['event_type'] == 'grade_updated' ? 'selected' : ''; ?>>
                        <?php echo get_string('grade_updated', 'local_moodle_zoho_sync'); ?>
                    </option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="col-md-3 mb-2">
                <label class="mr-2"><?php echo get_string('status', 'local_moodle_zoho_sync'); ?>:</label>
                <select name="status" class="form-control form-control-sm">
                    <option value=""><?php echo get_string('all', 'local_moodle_zoho_sync'); ?></option>
                    <option value="sent" <?php echo $filters['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="failed" <?php echo $filters['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="pending" <?php echo $filters['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="retrying" <?php echo $filters['status'] == 'retrying' ? 'selected' : ''; ?>>Retrying</option>
                </select>
            </div>

            <!-- Date From -->
            <div class="col-md-2 mb-2">
                <label class="mr-2"><?php echo get_string('from', 'local_moodle_zoho_sync'); ?>:</label>
                <input type="date" name="date_from" class="form-control form-control-sm" 
                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
            </div>

            <!-- Date To -->
            <div class="col-md-2 mb-2">
                <label class="mr-2"><?php echo get_string('to', 'local_moodle_zoho_sync'); ?>:</label>
                <input type="date" name="date_to" class="form-control form-control-sm" 
                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
            </div>

            <!-- Buttons -->
            <div class="col-md-2 mb-2">
                <button type="submit" class="btn btn-primary btn-sm mr-1">
                    <i class="fa fa-search"></i> <?php echo get_string('apply', 'local_moodle_zoho_sync'); ?>
                </button>
                <a href="<?php echo $PAGE->url->out_omit_querystring(); ?>" class="btn btn-secondary btn-sm">
                    <i class="fa fa-times"></i> <?php echo get_string('clear', 'local_moodle_zoho_sync'); ?>
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Results Summary -->
<div class="alert alert-info">
    <strong><?php echo get_string('total_results', 'local_moodle_zoho_sync'); ?>:</strong> <?php echo $totalcount; ?> events
    <?php if ($page > 0 || $totalcount > $perpage): ?>
        (<?php echo get_string('showing', 'local_moodle_zoho_sync'); ?> <?php echo ($page * $perpage + 1); ?>-<?php echo min(($page + 1) * $perpage, $totalcount); ?>)
    <?php endif; ?>
</div>

<!-- Events List -->
<?php if (empty($events)): ?>
    <div class="alert alert-warning">
        <i class="fa fa-info-circle"></i> <?php echo get_string('no_events_found', 'local_moodle_zoho_sync'); ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <?php foreach ($events as $event): ?>
                <div class="event-row" id="event-<?php echo $event->id; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <strong><?php echo htmlspecialchars($event->event_type); ?></strong>
                                <span class="status-badge status-<?php echo $event->status; ?> ml-2">
                                    <?php 
                                    $icons = [
                                        'sent' => 'fa-check-circle',
                                        'failed' => 'fa-times-circle',
                                        'pending' => 'fa-clock-o',
                                        'retrying' => 'fa-refresh'
                                    ];
                                    echo '<i class="fa ' . ($icons[$event->status] ?? 'fa-circle') . '"></i> ';
                                    echo ucfirst($event->status);
                                    ?>
                                </span>
                                <?php if ($event->retry_count > 0): ?>
                                    <span class="badge badge-warning ml-2">
                                        <i class="fa fa-repeat"></i> <?php echo $event->retry_count; ?> retries
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small">
                                <i class="fa fa-calendar"></i> <?php echo userdate($event->timecreated, '%d %b %Y, %H:%M:%S'); ?>
                                | <i class="fa fa-user"></i> User ID: <?php echo $event->relateduserid; ?>
                                | <i class="fa fa-tag"></i> Event ID: 
                                <code class="event-id-display"><?php echo htmlspecialchars(substr($event->event_id, 0, 8)); ?>...</code>
                                <button class="btn btn-sm btn-outline-secondary copy-btn" 
                                        onclick="copyEventId('<?php echo htmlspecialchars($event->event_id, ENT_QUOTES); ?>')" 
                                        title="<?php echo get_string('copy_event_id', 'local_moodle_zoho_sync'); ?>">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="toggleDetails(<?php echo $event->id; ?>)">
                            <i class="fa fa-chevron-down"></i> <?php echo get_string('details', 'local_moodle_zoho_sync'); ?>
                        </button>
                    </div>
                    
                    <!-- Expandable Details -->
                    <div class="event-details" id="details-<?php echo $event->id; ?>">
                        <h6><i class="fa fa-info-circle"></i> <?php echo get_string('event_details', 'local_moodle_zoho_sync'); ?></h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo get_string('event_id', 'local_moodle_zoho_sync'); ?>:</strong><br>
                                   <code><?php echo htmlspecialchars($event->event_id); ?></code></p>
                                <p><strong><?php echo get_string('status', 'local_moodle_zoho_sync'); ?>:</strong> 
                                   <?php echo ucfirst($event->status); ?></p>
                                <p><strong><?php echo get_string('retry_count', 'local_moodle_zoho_sync'); ?>:</strong> 
                                   <?php echo $event->retry_count; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo get_string('created', 'local_moodle_zoho_sync'); ?>:</strong> 
                                   <?php echo userdate($event->timecreated); ?></p>
                                <?php if ($event->timeprocessed): ?>
                                    <p><strong><?php echo get_string('processed', 'local_moodle_zoho_sync'); ?>:</strong> 
                                       <?php echo userdate($event->timeprocessed); ?></p>
                                <?php endif; ?>
                                <?php if ($event->next_retry_at): ?>
                                    <p><strong><?php echo get_string('next_retry', 'local_moodle_zoho_sync'); ?>:</strong> 
                                       <?php echo userdate($event->next_retry_at); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($event->last_error): ?>
                            <h6 class="text-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo get_string('error_details', 'local_moodle_zoho_sync'); ?></h6>
                            <pre><?php echo htmlspecialchars($event->last_error); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalcount > $perpage): ?>
        <div class="mt-3">
            <?php
            $baseurl = new moodle_url($PAGE->url, array_merge($filters, ['perpage' => $perpage]));
            echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl);
            ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function toggleDetails(eventId) {
    const details = document.getElementById('details-' + eventId);
    const btn = event.target.closest('button');
    const icon = btn.querySelector('i');
    
    if (details.style.display === 'none' || details.style.display === '') {
        details.style.display = 'block';
        icon.className = 'fa fa-chevron-up';
    } else {
        details.style.display = 'none';
        icon.className = 'fa fa-chevron-down';
    }
}

function copyEventId(eventId) {
    navigator.clipboard.writeText(eventId).then(() => {
        const btn = event.target.closest('button');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check"></i>';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    });
}
</script>

<?php
echo $OUTPUT->footer();
