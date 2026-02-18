<?php
/**
 * Student Classes Page
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
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/classes.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My Classes');
$PAGE->set_heading('My Classes');

$userid = $USER->id;

// Get student record
$student = $DB->get_record_sql("
    SELECT * FROM {local_mzi_students}
    WHERE moodle_user_id = ?
    LIMIT 1
", [$userid]);

// Get enrollments with class and grade details
$enrollments = [];
if ($student) {
    $enrollments = $DB->get_records_sql("
        SELECT e.*, 
               c.class_name, c.program_level, c.teacher_name, 
               c.start_date, c.end_date, c.class_status,
               (SELECT COUNT(*) FROM {local_mzi_grades} g 
                WHERE g.student_id = e.student_id AND g.class_id = e.class_id) as grade_count
        FROM {local_mzi_enrollments} e
        INNER JOIN {local_mzi_classes} c ON c.id = e.class_id
        WHERE e.student_id = ?
        ORDER BY c.start_date DESC, c.class_name
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
        <a class="nav-link active" href="classes.php">
            <i class="fa fa-calendar"></i> My Classes
        </a>
        <a class="nav-link" href="requests.php">
            <i class="fa fa-file-text"></i> My Requests
        </a>
        <a class="nav-link" href="student_card.php">
            <i class="fa fa-id-card"></i> Student Card
        </a>
    </nav>

    <?php if ($student && count($enrollments) > 0): ?>
        <h3><i class="fa fa-calendar"></i> My Class Enrollments</h3>
        
        <div class="row">
            <?php foreach ($enrollments as $enrollment): ?>
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?php echo s($enrollment->class_name); ?></h5>
                            <small><?php echo s($enrollment->program_level); ?></small>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%"><i class="fa fa-user"></i> Instructor:</th>
                                    <td><?php echo s($enrollment->teacher_name); ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-calendar-o"></i> Start Date:</th>
                                    <td><?php echo $enrollment->start_date ? userdate($enrollment->start_date, '%d %B %Y') : 'TBA'; ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-calendar-check-o"></i> End Date:</th>
                                    <td><?php echo $enrollment->end_date ? userdate($enrollment->end_date, '%d %B %Y') : 'TBA'; ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-calendar-plus-o"></i> Enrolled On:</th>
                                    <td><?php echo userdate($enrollment->enrollment_date, '%d %B %Y'); ?></td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-info-circle"></i> Class Status:</th>
                                    <td>
                                        <?php
                                        $cstatusclass = $enrollment->class_status == 'Active' ? 'success' : 
                                                       ($enrollment->class_status == 'Cancelled' ? 'danger' : 'secondary');
                                        ?>
                                        <span class="badge badge-<?php echo $cstatusclass; ?>">
                                            <?php echo s($enrollment->class_status); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-graduation-cap"></i> My Status:</th>
                                    <td>
                                        <?php
                                        $estatusclass = $enrollment->enrollment_status == 'Active' ? 'success' : 
                                                       ($enrollment->enrollment_status == 'Withdrawn' ? 'danger' : 'warning');
                                        ?>
                                        <span class="badge badge-<?php echo $estatusclass; ?>">
                                            <?php echo s($enrollment->enrollment_status); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><i class="fa fa-trophy"></i> Grades:</th>
                                    <td>
                                        <?php if ($enrollment->grade_count > 0): ?>
                                            <span class="badge badge-info">
                                                <?php echo $enrollment->grade_count; ?> grade(s) recorded
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No grades yet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                <i class="fa fa-id-badge"></i> Enrollment ID: <?php echo s($enrollment->zoho_enrollment_id); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php elseif ($student): ?>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            <strong>No class enrollments found.</strong>
            You haven't enrolled in any classes yet.
        </div>
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
