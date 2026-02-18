<?php
/**
 * Student Card Generator
 * 
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$action = optional_param('action', 'view', PARAM_ALPHA);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/student/student_card.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Student Card');
$PAGE->set_heading('Student Card');

$userid = $USER->id;

// Get student record
$student = $DB->get_record_sql("
    SELECT s.*, 
           (SELECT program_name FROM {local_mzi_registrations} 
            WHERE student_id = s.id AND registration_status = 'Active' 
            ORDER BY registration_date DESC LIMIT 1) as current_program
    FROM {local_mzi_students} s
    WHERE s.moodle_user_id = ?
    LIMIT 1
", [$userid]);

if ($action == 'generate' && $student) {
    // Generate PDF Student Card
    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L', 'A6'); // Landscape A6 for card size
    
    // Card Header
    $pdf->SetFillColor(0, 123, 255);
    $pdf->Rect(0, 0, 148, 30, 'F');
    
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetXY(10, 8);
    $pdf->Cell(128, 10, 'ABC Horizon College', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(10, 18);
    $pdf->Cell(128, 8, 'Student Identification Card', 0, 1, 'C');
    
    // Photo placeholder
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Rect(15, 35, 30, 35, 'F');
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(15, 50);
    $pdf->Cell(30, 10, 'PHOTO', 0, 0, 'C');
    
    // Student Information
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY(50, 35);
    $pdf->Cell(80, 6, strtoupper($student->first_name . ' ' . $student->last_name), 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(50, 42);
    $pdf->Cell(30, 5, 'Student ID:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(50, 5, $student->student_id, 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(50, 48);
    $pdf->Cell(30, 5, 'Program:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(50, 5, $student->current_program ?: 'N/A', 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(50, 54);
    $pdf->Cell(30, 5, 'Nationality:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(50, 5, $student->nationality, 0, 1);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(50, 60);
    $pdf->Cell(30, 5, 'Email:', 0, 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(50, 5, $student->email, 0, 1);
    
    // Footer
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Rect(0, 75, 148, 30, 'F');
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetXY(10, 80);
    $pdf->Cell(128, 4, 'Valid for academic year 2025-2026', 0, 1, 'C');
    $pdf->SetXY(10, 84);
    $pdf->Cell(128, 4, 'This card remains property of ABC Horizon College', 0, 1, 'C');
    $pdf->SetXY(10, 88);
    $pdf->Cell(128, 4, 'If found, please return to administration office', 0, 1, 'C');
    
    $filename = 'student_card_' . $student->student_id . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
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
        <a class="nav-link" href="requests.php">
            <i class="fa fa-file-text"></i> My Requests
        </a>
        <a class="nav-link active" href="student_card.php">
            <i class="fa fa-id-card"></i> Student Card
        </a>
    </nav>

    <?php if ($student): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3><i class="fa fa-id-card"></i> Student Identification Card</h3>
            </div>
            <div class="card-body text-center">
                <div class="card-preview mb-4" style="max-width: 400px; margin: 0 auto;">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">ABC Horizon College</h5>
                            <small>Student Identification Card</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-4">
                                    <div class="photo-placeholder bg-light text-muted d-flex align-items-center justify-content-center" 
                                         style="height: 120px; border: 1px solid #ddd;">
                                        <i class="fa fa-user fa-3x"></i>
                                    </div>
                                </div>
                                <div class="col-8 text-left">
                                    <h5 class="mb-2"><?php echo s($student->first_name . ' ' . $student->last_name); ?></h5>
                                    <p class="mb-1"><strong>ID:</strong> <?php echo s($student->student_id); ?></p>
                                    <p class="mb-1"><strong>Program:</strong> <?php echo s($student->current_program ?: 'N/A'); ?></p>
                                    <p class="mb-1"><strong>Nationality:</strong> <?php echo s($student->nationality); ?></p>
                                    <p class="mb-0"><small><?php echo s($student->email); ?></small></p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">
                                Valid for academic year 2025-2026<br>
                                This card remains property of ABC Horizon College
                            </small>
                        </div>
                    </div>
                </div>
                
                <a href="student_card.php?action=generate" class="btn btn-primary btn-lg">
                    <i class="fa fa-download"></i> Download Student Card (PDF)
                </a>
                
                <div class="alert alert-info mt-4">
                    <i class="fa fa-info-circle"></i>
                    <strong>Note:</strong> Print your student card on durable cardstock paper for best results. 
                    This card must be carried at all times while on campus.
                </div>
            </div>
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
