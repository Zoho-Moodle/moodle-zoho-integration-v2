<?php
/**
 * Student Requests Page
 * 
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/requests.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My Requests');
$PAGE->set_heading('My Requests');

$userid = $USER->id;

// Get student record
$student = $DB->get_record_sql("
    SELECT * FROM {local_mzi_students}
    WHERE moodle_user_id = ?
    LIMIT 1
", [$userid]);

// Get requests
$requests = [];
if ($student) {
    $requests = $DB->get_records_sql("
        SELECT * FROM {local_mzi_requests}
        WHERE student_id = ?
        ORDER BY created_at DESC
    ", [$student->id]);
}

echo $OUTPUT->header();
?>

<div class="student-dashboard">
    <nav class="nav nav-tabs mb-3">
        <a class="nav-link" href="profile.php">
            <i class="fa fa-user"></i> Profile
        </a>
        <a class="nav-link" href="programs.php">
            <i class="fa fa-graduation-cap"></i> My Programs
        </a>
        <a class="nav-link" href="classes.php">
            <i class="fa fa-calendar"></i> My Classes
        </a>
        <a class="nav-link active" href="requests.php">
            <i class="fa fa-file-text"></i> My Requests
        </a>
        <a class="nav-link" href="student_card.php">
            <i class="fa fa-id-card"></i> Student Card
        </a>
    </nav>

    <?php if ($student): ?>
        <div class="row mb-3">
            <div class="col-md-8">
                <h3><i class="fa fa-file-text"></i> My Requests</h3>
            </div>
            <div class="col-md-4 text-right">
                <div class="alert alert-info mb-0 p-2">
                    <small>
                        <i class="fa fa-info-circle"></i>
                        To submit a new request, please contact the administration office or use the Zoho CRM portal.
                    </small>
                </div>
            </div>
        </div>

        <?php if (count($requests) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Request ID</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo s($request->zoho_request_id); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?php echo s($request->request_type); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $desc = s($request->description);
                                    echo strlen($desc) > 80 ? substr($desc, 0, 80) . '...' : $desc;
                                    ?>
                                    <?php if (strlen($request->description) > 80): ?>
                                        <button class="btn btn-sm btn-link p-0" 
                                                data-toggle="modal" 
                                                data-target="#requestModal<?php echo $request->id; ?>">
                                            View Full
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusclass = 'secondary';
                                    switch ($request->status) {
                                        case 'Pending':
                                            $statusclass = 'warning';
                                            break;
                                        case 'Approved':
                                        case 'Completed':
                                            $statusclass = 'success';
                                            break;
                                        case 'Rejected':
                                        case 'Cancelled':
                                            $statusclass = 'danger';
                                            break;
                                        case 'In Progress':
                                            $statusclass = 'info';
                                            break;
                                    }
                                    ?>
                                    <span class="badge badge-<?php echo $statusclass; ?>">
                                        <?php echo s($request->status); ?>
                                    </span>
                                </td>
                                <td><?php echo userdate($request->created_at, '%d %b %Y'); ?></td>
                                <td><?php echo userdate($request->updated_at, '%d %b %Y'); ?></td>
                            </tr>
                            
                            <!-- Modal for full description -->
                            <?php if (strlen($request->description) > 80): ?>
                                <div class="modal fade" id="requestModal<?php echo $request->id; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Request Details</h5>
                                                <button type="button" class="close" data-dismiss="modal">
                                                    <span>&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <p><strong>Request ID:</strong> <?php echo s($request->zoho_request_id); ?></p>
                                                <p><strong>Type:</strong> <?php echo s($request->request_type); ?></p>
                                                <p><strong>Description:</strong></p>
                                                <p><?php echo nl2br(s($request->description)); ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <strong>No requests found.</strong>
                You haven't submitted any requests yet.
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>No student record found.</strong>
            Please contact the administration office.
        </div>
    <?php endif; ?>
</div>

<style>
.student-dashboard {
    max-width: 1200px;
    margin: 0 auto;
}
.student-dashboard .nav-tabs .nav-link {
    color: #495057;
}
.student-dashboard .nav-tabs .nav-link.active {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}
.student-dashboard .nav-tabs .nav-link:hover {
    background-color: #0056b3;
    color: white;
}
</style>

<?php
echo $OUTPUT->footer();
