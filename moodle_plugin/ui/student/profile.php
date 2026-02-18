<?php
/**
 * Student Profile Page
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
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/profile.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Student Profile');
$PAGE->set_heading('Student Profile');

// Get current user's Moodle ID
$userid = $USER->id;

// Get student record from integration tables
$student = $DB->get_record_sql("
    SELECT * FROM {local_mzi_students}
    WHERE moodle_user_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
", [$userid]);

echo $OUTPUT->header();

?>

<div class="student-dashboard">
    <nav class="nav nav-tabs mb-3">
        <a class="nav-link active" href="profile.php">
            <i class="fa fa-user"></i> Profile
        </a>
        <a class="nav-link" href="programs.php">
            <i class="fa fa-graduation-cap"></i> My Programs
        </a>
        <a class="nav-link" href="classes.php">
            <i class="fa fa-calendar"></i> My Classes
        </a>
        <a class="nav-link" href="requests.php">
            <i class="fa fa-file-text"></i> My Requests
        </a>
        <a class="nav-link" href="student_card.php">
            <i class="fa fa-id-card"></i> Student Card
        </a>
    </nav>

    <?php if ($student): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fa fa-user"></i> Student Profile</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Student ID:</th>
                                <td><strong><?php echo s($student->student_id); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Full Name:</th>
                                <td><?php echo s($student->first_name . ' ' . $student->last_name); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><a href="mailto:<?php echo s($student->email); ?>"><?php echo s($student->email); ?></a></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo s($student->phone_number); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Nationality:</th>
                                <td><?php echo s($student->nationality); ?></td>
                            </tr>
                            <tr>
                                <th>Date of Birth:</th>
                                <td><?php echo $student->date_of_birth ? userdate($student->date_of_birth, '%d %B %Y') : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <?php
                                    $statusclass = $student->status == 'Active' ? 'success' : 
                                                  ($student->status == 'Deleted' ? 'danger' : 'warning');
                                    ?>
                                    <span class="badge badge-<?php echo $statusclass; ?>">
                                        <?php echo s($student->status); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td><?php echo userdate($student->updated_at, '%d %B %Y, %H:%M'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Photo -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fa fa-camera"></i> Student Photo
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($student->photo_url)): ?>
                        <img src="<?php echo $CFG->wwwroot . s($student->photo_url); ?>" alt="Student Photo" class="img-thumbnail" style="max-width: 200px;">
                    <?php else: ?>
                        <div class="text-muted">
                            <i class="fa fa-user" style="font-size: 120px;"></i>
                            <p>No photo available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Address Information -->
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fa fa-map-marker"></i> Address Information
                </div>
                <div class="card-body">
                    <?php if (!empty($student->address)): ?>
                        <p><?php echo s($student->address); ?></p>
                    <?php else: ?>
                        <p class="text-muted">No address information available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>No student record found.</strong>
            Your account is not yet linked to a student profile in our system. 
            Please contact the administration office for assistance.
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
