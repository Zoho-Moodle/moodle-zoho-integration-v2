<?php
/**
 * Event Logs page for Moodle-Zoho Integration Plugin
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/moodle_zoho_sync/classes/event_logger.php');

use local_moodle_zoho_sync\event_logger;

// Require login and admin capabilities.
require_login();
admin_externalpage_setup('local_moodle_zoho_sync_logs');

$context = context_system::instance();
require_capability('local/moodle_zoho_sync:manage', $context);

// Page parameters.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);
$eventtype = optional_param('eventtype', '', PARAM_ALPHA);

// Page setup.
$PAGE->set_url('/local/moodle_zoho_sync/ui/admin/event_logs.php', 
    ['page' => $page, 'perpage' => $perpage, 'status' => $status, 'eventtype' => $eventtype]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('event_logs', 'local_moodle_zoho_sync'));
$PAGE->set_heading(get_string('event_logs', 'local_moodle_zoho_sync'));

// Get event logs from database.
global $DB;

$sql = "SELECT * FROM {local_mzi_event_log} WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND status = :status";
    $params['status'] = $status;
}

if ($eventtype) {
    $sql .= " AND event_type = :eventtype";
    $params['eventtype'] = $eventtype;
}

$sql .= " ORDER BY timecreated DESC";

$totalcount = $DB->count_records_sql("SELECT COUNT(*) FROM {local_mzi_event_log} WHERE 1=1" . 
    ($status ? " AND status = :status" : "") . 
    ($eventtype ? " AND event_type = :eventtype" : ""), $params);

$events = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Render page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('event_logs', 'local_moodle_zoho_sync'));

// Statistics summary.
$stats = event_logger::get_statistics();
echo html_writer::start_div('alert alert-info');
echo html_writer::tag('h4', get_string('statistics', 'local_moodle_zoho_sync'));
echo html_writer::tag('p', 
    "Total: {$stats['total']} | " .
    "Sent: {$stats['sent']} | " .
    "Failed: {$stats['failed']} | " .
    "Pending: {$stats['pending']}"
);
echo html_writer::end_div();

// Filters.
echo html_writer::start_div('filters mb-3');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring()]);
echo html_writer::start_div('row');

// Status filter.
echo html_writer::start_div('col-md-3');
echo html_writer::label('Status:', 'status');
echo html_writer::select(
    ['' => 'All', 'pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed', 'retrying' => 'Retrying'],
    'status',
    $status,
    false,
    ['class' => 'form-control', 'id' => 'status']
);
echo html_writer::end_div();

// Event type filter.
echo html_writer::start_div('col-md-3');
echo html_writer::label('Event Type:', 'eventtype');
echo html_writer::select(
    [
        '' => 'All',
        'user_created' => 'User Created',
        'user_updated' => 'User Updated',
        'enrollment_created' => 'Enrollment Created',
        'grade_updated' => 'Grade Updated'
    ],
    'eventtype',
    $eventtype,
    false,
    ['class' => 'form-control', 'id' => 'eventtype']
);
echo html_writer::end_div();

// Per page.
echo html_writer::start_div('col-md-2');
echo html_writer::label('Per Page:', 'perpage');
echo html_writer::select(
    [25 => '25', 50 => '50', 100 => '100', 200 => '200'],
    'perpage',
    $perpage,
    false,
    ['class' => 'form-control', 'id' => 'perpage']
);
echo html_writer::end_div();

// Submit button.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', '&nbsp;', ['style' => 'display:block;']);
echo html_writer::tag('button', 'Filter', ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_div();

// Reset button.
echo html_writer::start_div('col-md-2');
echo html_writer::tag('label', '&nbsp;', ['style' => 'display:block;']);
echo html_writer::link(
    new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'),
    'Reset',
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_tag('form');
echo html_writer::end_div(); // filters

// Events table.
if (empty($events)) {
    echo html_writer::div('No events found.', 'alert alert-warning');
} else {
    $table = new html_table();
    $table->head = [
        'Event ID',
        'Event Type',
        'Status',
        'Retry Count',
        'HTTP Status',
        'Created',
        'Modified',
        'Actions'
    ];
    $table->attributes['class'] = 'generaltable table table-striped';

    foreach ($events as $event) {
        // Status badge.
        $statusclass = '';
        switch ($event->status) {
            case 'sent':
                $statusclass = 'badge-success';
                break;
            case 'failed':
                $statusclass = 'badge-danger';
                break;
            case 'pending':
                $statusclass = 'badge-warning';
                break;
            case 'retrying':
                $statusclass = 'badge-info';
                break;
        }
        $statusbadge = html_writer::span($event->status, "badge $statusclass");

        // Event type badge.
        $typebadge = html_writer::span(str_replace('_', ' ', $event->event_type), 'badge badge-secondary');

        // Timestamps.
        $created = $event->timecreated ? userdate($event->timecreated, '%Y-%m-%d %H:%M:%S') : '-';
        $modified = $event->timemodified ? userdate($event->timemodified, '%Y-%m-%d %H:%M:%S') : '-';

        // Actions.
        $viewurl = new moodle_url('/local/moodle_zoho_sync/ui/admin/event_detail.php', ['id' => $event->id]);
        $actions = html_writer::link($viewurl, 'View Details', ['class' => 'btn btn-sm btn-info']);

        $table->data[] = [
            substr($event->event_id, 0, 8) . '...',
            $typebadge,
            $statusbadge,
            $event->retry_count,
            $event->http_status ?? '-',
            $created,
            $modified,
            $actions
        ];
    }

    echo html_writer::table($table);

    // Pagination.
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);
}

echo $OUTPUT->footer();
