<?php
/**
 * Student Programs Page
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
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/programs.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('My Programs');
$PAGE->set_heading('My Programs');

$userid = $USER->id;

// Get student record
$student = $DB->get_record_sql("
    SELECT * FROM {local_mzi_students}
    WHERE moodle_user_id = ?
    LIMIT 1
", [$userid]);

// Get registrations and payments
$registrations = [];
if ($student) {
    $registrations = $DB->get_records_sql("
        SELECT r.*, 
               COALESCE(SUM(p.payment_amount), 0) as paid_amount,
               (r.total_fees - COALESCE(SUM(p.payment_amount), 0)) as balance
        FROM {local_mzi_registrations} r
        LEFT JOIN {local_mzi_payments} p ON p.registration_id = r.id 
            AND p.payment_status != 'Voided'
        WHERE r.student_id = ?
        GROUP BY r.id
        ORDER BY r.registration_date DESC
    ", [$student->id]);
}

echo $OUTPUT->header();
?>

<div class="student-dashboard">
    <nav class="nav nav-tabs mb-3">
        <a class="nav-link" href="profile.php">
            <i class="fa fa-user"></i> Profile
        </a>
        <a class="nav-link active" href="programs.php">
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

    <?php if ($student && count($registrations) > 0): ?>
        <h3><i class="fa fa-graduation-cap"></i> My Program Registrations</h3>
        
        <?php foreach ($registrations as $reg): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-0"><?php echo s($reg->program_name); ?></h4>
                            <small class="text-muted">Registration ID: <?php echo s($reg->zoho_registration_id); ?></small>
                        </div>
                        <div class="col-md-4 text-right">
                            <?php
                            $statusclass = $reg->registration_status == 'Active' ? 'success' : 
                                          ($reg->registration_status == 'Cancelled' ? 'danger' : 'warning');
                            ?>
                            <span class="badge badge-<?php echo $statusclass; ?> badge-lg">
                                <?php echo s($reg->registration_status); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Registration Details</h5>
                            <table class="table table-sm">
                                <tr>
                                    <th width="50%">Registration Date:</th>
                                    <td><?php echo userdate($reg->registration_date, '%d %B %Y'); ?></td>
                                </tr>
                                <tr>
                                    <th>Total Fees:</th>
                                    <td><strong>SAR <?php echo number_format($reg->total_fees, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Amount Paid:</th>
                                    <td class="text-success"><strong>SAR <?php echo number_format($reg->paid_amount, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Balance Due:</th>
                                    <td class="<?php echo $reg->balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong>SAR <?php echo number_format($reg->balance, 2); ?></strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Payment Progress</h5>
                            <div class="progress" style="height: 30px;">
                                <?php 
                                $percentage = $reg->total_fees > 0 ? ($reg->paid_amount / $reg->total_fees * 100) : 0;
                                $progressclass = $percentage >= 100 ? 'success' : 
                                                ($percentage >= 50 ? 'info' : 'warning');
                                ?>
                                <div class="progress-bar bg-<?php echo $progressclass; ?>" 
                                     role="progressbar" 
                                     style="width: <?php echo min($percentage, 100); ?>%"
                                     aria-valuenow="<?php echo $percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?php echo number_format($percentage, 1); ?>%
                                </div>
                            </div>
                            <p class="mt-2 text-muted">
                                <small>
                                    <?php if ($reg->balance > 0): ?>
                                        <i class="fa fa-exclamation-circle"></i> Payment incomplete
                                    <?php else: ?>
                                        <i class="fa fa-check-circle"></i> Fully paid
                                    <?php endif; ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
    <?php elseif ($student): ?>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            <strong>No program registrations found.</strong>
            You haven't registered for any programs yet.
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
.badge-lg {
    font-size: 1rem;
    padding: 0.5rem 1rem;
}
</style>

<?php
echo $OUTPUT->footer();
