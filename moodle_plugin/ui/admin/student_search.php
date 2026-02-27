<?php
/**
 * Admin Student Search and View
 * 
 * Allows administrators to search for students and view their complete data
 * Search by: name, username, or academic email
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once(__DIR__ . '/includes/navigation.php');
require_login();

// Check if user has admin or manager capabilities
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/student_search.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Admin: Student Search');
$PAGE->set_heading('Student Data Search');

// Get search parameter
$search = optional_param('search', '', PARAM_TEXT);
$student_id = optional_param('student_id', 0, PARAM_INT);

echo $OUTPUT->header();
mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('student_search', 'Moodle-Zoho Integration', 'Student Data Search');
mzi_render_breadcrumb('Student Search');
?>

<script>
function switchTab(tabName, button) {
    // Hide all tabs
    var tabs = document.getElementsByClassName('tab-content');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.remove('active');
    }
    
    // Remove active from all buttons
    var buttons = document.getElementsByClassName('tab-button');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('active');
    }
    
    // Show selected tab - safe null check
    var target = document.getElementById(tabName + '-tab');
    if (target) target.classList.add('active');
    if (button) button.classList.add('active');
}
</script>

<style>
    .search-container {
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    .search-box {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .search-box input {
        flex: 1;
        padding: 12px 20px;
        font-size: 16px;
        border: 2px solid #ddd;
        border-radius: 5px;
    }
    .search-box button {
        padding: 12px 30px;
        font-size: 16px;
        background: #0066cc;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    .search-box button:hover {
        background: #0052a3;
    }
    .student-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.3s;
    }
    .student-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    .student-card h3 {
        margin: 0 0 10px 0;
        color: #0066cc;
    }
    .student-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 10px;
        font-size: 14px;
        color: #666;
    }
    .student-detail {
        background: #f8f9fa;
        padding: 30px;
        border-radius: 10px;
        margin-top: 20px;
    }
    .nav-tabs {
        border-bottom: 2px solid #ddd;
        margin-bottom: 20px;
    }
    .nav-tabs button {
        padding: 10px 20px;
        border: none;
        background: none;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        margin-right: 5px;
    }
    .nav-tabs button.active {
        border-bottom-color: #0066cc;
        color: #0066cc;
        font-weight: bold;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .data-table th, .data-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .data-table th {
        background: #f8f9fa;
        font-weight: bold;
    }
    .back-button {
        display: inline-block;
        padding: 10px 20px;
        background: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .back-button:hover {
        background: #5a6268;
        color: white;
    }
    .no-results {
        text-align: center;
        padding: 40px;
        color: #999;
        font-size: 18px;
    }
    .student-photo {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 20px;
    }
</style>

<div class="search-container">
    <h2><i class="fa fa-search"></i> Search Students</h2>
    <p>Search by name, username, or academic email</p>
    
    <form method="get" action="">
        <div class="search-box">
            <input type="text" name="search" placeholder="Enter student name, username, or email..." 
                   value="<?php echo s($search); ?>" required>
            <button type="submit"><i class="fa fa-search"></i> Search</button>
        </div>
    </form>
</div>

<?php

if (!empty($search)) {
    // Search for students
    $sql = "SELECT s.*, u.username, u.firstname, u.lastname, u.email
            FROM {local_mzi_students} s
            LEFT JOIN {user} u ON s.moodle_user_id = u.id
            WHERE (s.first_name LIKE :search1 
                   OR s.last_name LIKE :search2 
                   OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search3
                   OR u.username LIKE :search4
                   OR s.academic_email LIKE :search5
                   OR u.email LIKE :search6)
            ORDER BY s.first_name, s.last_name
            LIMIT 50";
    
    $search_param = '%' . $search . '%';
    $students = $DB->get_records_sql($sql, [
        'search1' => $search_param,
        'search2' => $search_param,
        'search3' => $search_param,
        'search4' => $search_param,
        'search5' => $search_param,
        'search6' => $search_param
    ]);
    
    if (empty($students)) {
        echo '<div class="no-results"><i class="fa fa-search" style="font-size: 48px;"></i><br>No students found matching your search.</div>';
    } else {
        echo '<h3>Search Results (' . count($students) . ' found)</h3>';
        foreach ($students as $student) {
            $display_name = $student->first_name . ' ' . $student->last_name;
            if (empty(trim($display_name))) {
                $display_name = $student->username ?: 'Unknown Student';
            }
            
            echo '<div class="student-card" onclick="location.href=\'?student_id=' . $student->id . '\'">';
            echo '<h3>' . s($display_name) . '</h3>';
            echo '<div class="student-info">';
            echo '<div><strong>Student ID:</strong> ' . s($student->student_id ?: 'N/A') . '</div>';
            echo '<div><strong>Username:</strong> ' . s($student->username ?: 'N/A') . '</div>';
            echo '<div><strong>Academic Email:</strong> ' . s($student->academic_email ?: 'N/A') . '</div>';
            echo '<div><strong>Status:</strong> ' . s($student->status ?: 'N/A') . '</div>';
            echo '</div>';
            echo '</div>';
        }
    }
}

if ($student_id > 0) {
    // Get student details
    $student = $DB->get_record('local_mzi_students', ['id' => $student_id]);
    
    if ($student) {
        // Get Moodle user info
        $moodle_user = null;
        if ($student->moodle_user_id) {
            $moodle_user = $DB->get_record('user', ['id' => $student->moodle_user_id]);
        }
        
        echo '<a href="?search=' . urlencode($search) . '" class="back-button"><i class="fa fa-arrow-left"></i> Back to Results</a>';
        
        echo '<div class="student-detail">';
        
        // Student Photo
        if (!empty($student->photo_url)) {
            echo '<img src="' . $CFG->wwwroot . s($student->photo_url) . '" alt="Student Photo" class="student-photo">';
        } else {
            echo '<div style="width: 150px; height: 150px; background: #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">';
            echo '<i class="fa fa-user" style="font-size: 60px; color: #999;"></i>';
            echo '</div>';
        }
        
        echo '<h2>' . s($student->first_name . ' ' . $student->last_name) . '</h2>';
        
        // Tabs
        echo '<div class="nav-tabs">';
        echo '<button class="tab-button active" onclick="switchTab(\'profile\', this)">Profile</button>';
        echo '<button class="tab-button" onclick="switchTab(\'programs\', this)">Enrollments</button>';
        echo '<button class="tab-button" onclick="switchTab(\'classes\', this)">Grades</button>';
        echo '<button class="tab-button" onclick="switchTab(\'requests\', this)">Requests</button>';
        echo '</div>';
        
        // Profile Tab
        echo '<div id="profile-tab" class="tab-content active">';
        echo '<h3>Personal Information</h3>';
        echo '<table class="data-table">';
        echo '<tr><th>Student ID</th><td>' . s($student->student_id ?: 'N/A') . '</td></tr>';
        echo '<tr><th>Zoho Student ID</th><td>' . s($student->zoho_student_id ?: 'N/A') . '</td></tr>';
        echo '<tr><th>First Name</th><td>' . s($student->first_name ?: 'N/A') . '</td></tr>';
        echo '<tr><th>Last Name</th><td>' . s($student->last_name ?: 'N/A') . '</td></tr>';
        echo '<tr><th>Date of Birth</th><td>' . s($student->date_of_birth ?: 'N/A') . '</td></tr>';
        echo '<tr><th>Gender</th><td>' . s(isset($student->gender) ? $student->gender : 'N/A') . '</td></tr>';
        echo '<tr><th>Nationality</th><td>' . s($student->nationality ?: 'N/A') . '</td></tr>';
        echo '<tr><th>Status</th><td>' . s($student->status ?: 'N/A') . '</td></tr>';
        echo '</table>';
        
        echo '<h3 style="margin-top: 30px;">Contact Information</h3>';
        echo '<table class="data-table">';
        echo '<tr><th>Academic Email</th><td>' . s($student->academic_email ?: 'N/A') . '</td></tr>';
        echo '<tr><th>Personal Email</th><td>' . s(isset($student->personal_email) ? $student->personal_email : 'N/A') . '</td></tr>';
        echo '<tr><th>Mobile Phone</th><td>' . s(isset($student->mobile_phone) ? $student->mobile_phone : 'N/A') . '</td></tr>';
        echo '<tr><th>Address</th><td>' . s($student->address ?: 'N/A') . '</td></tr>';
        echo '</table>';
        
        if ($moodle_user) {
            echo '<h3 style="margin-top: 30px;">Moodle Account</h3>';
            echo '<table class="data-table">';
            echo '<tr><th>Username</th><td>' . s($moodle_user->username) . '</td></tr>';
            echo '<tr><th>Email</th><td>' . s($moodle_user->email) . '</td></tr>';
            echo '<tr><th>First Access</th><td>' . ($moodle_user->firstaccess ? userdate($moodle_user->firstaccess) : 'Never') . '</td></tr>';
            echo '<tr><th>Last Access</th><td>' . ($moodle_user->lastaccess ? userdate($moodle_user->lastaccess) : 'Never') . '</td></tr>';
            echo '</table>';
        }
        echo '</div>';
        
        // Programs/Enrollments Tab
        echo '<div id="programs-tab" class="tab-content">';
        echo '<h3>Enrolled Classes</h3>';
        $enrollments = $DB->get_records_sql("
            SELECT e.*, c.class_name, c.unit_name, c.program_level, c.teacher_name,
                   c.start_date, c.end_date, c.class_status, c.class_type
            FROM {local_mzi_enrollments} e
            LEFT JOIN {local_mzi_classes} c ON e.class_id = c.id
            WHERE e.student_id = ?
            ORDER BY e.enrollment_date DESC
        ", [$student->id]);
        if ($enrollments) {
            echo '<table class="data-table">';
            echo '<thead><tr><th>Class Name</th><th>Unit</th><th>Program Level</th><th>Teacher</th><th>Enrollment Date</th><th>Status</th><th>Attendance %</th></tr></thead>';
            echo '<tbody>';
            foreach ($enrollments as $enrollment) {
                echo '<tr>';
                echo '<td>' . s($enrollment->class_name ?: 'N/A') . '</td>';
                echo '<td>' . s($enrollment->unit_name ?: 'N/A') . '</td>';
                echo '<td>' . s($enrollment->program_level ?: 'N/A') . '</td>';
                echo '<td>' . s($enrollment->teacher_name ?: 'N/A') . '</td>';
                echo '<td>' . s($enrollment->enrollment_date ?: 'N/A') . '</td>';
                echo '<td><span style="padding:3px 8px;border-radius:4px;background:' . ($enrollment->enrollment_status === 'Active' ? '#d4edda;color:#155724' : '#f8d7da;color:#721c24') . '">' . s($enrollment->enrollment_status ?: 'N/A') . '</span></td>';
                echo '<td>' . (isset($enrollment->attendance_percentage) ? number_format($enrollment->attendance_percentage, 1) . '%' : 'N/A') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="no-results">No enrollments found</p>';
        }
        echo '</div>';
        
        // Grades Tab
        echo '<div id="classes-tab" class="tab-content">';
        echo '<h3>Grades</h3>';
        $grades = $DB->get_records_sql("
            SELECT g.*, c.class_name, c.unit_name
            FROM {local_mzi_grades} g
            LEFT JOIN {local_mzi_classes} c ON g.class_id = c.id
            WHERE g.student_id = ?
            ORDER BY g.grade_date DESC
        ", [$student->id]);
        if ($grades) {
            echo '<table class="data-table">';
            echo '<thead><tr><th>Assignment</th><th>Class</th><th>Grade</th><th>Attempt</th><th>Feedback</th><th>Grade Date</th></tr></thead>';
            echo '<tbody>';
            foreach ($grades as $grade) {
                $grade_colors = ['P' => '#d4edda;color:#155724', 'M' => '#d1ecf1;color:#0c5460', 'D' => '#e2d9f3;color:#432874', 'R' => '#fff3cd;color:#856404', 'F' => '#f8d7da;color:#721c24', 'RR' => '#fde8d8;color:#8a3c0e'];
                $gc = $grade_colors[$grade->btec_grade_name] ?? '#e2e3e5;color:#383d41';
                echo '<tr>';
                echo '<td>' . s($grade->assignment_name ?: 'N/A') . '</td>';
                echo '<td>' . s($grade->class_name ?: 'N/A') . '</td>';
                echo '<td><span style="padding:3px 10px;border-radius:12px;font-weight:700;background:' . $gc . '">' . s($grade->btec_grade_name ?: 'N/A') . '</span></td>';
                echo '<td>' . s($grade->attempt_number ?: '1') . '</td>';
                echo '<td><small>' . s(mb_substr($grade->feedback ?: '', 0, 80)) . (strlen($grade->feedback ?? '') > 80 ? '...' : '') . '</small></td>';
                echo '<td>' . s($grade->grade_date ?: 'N/A') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="no-results">No grades found</p>';
        }
        echo '</div>';
        
        // Requests Tab
        echo '<div id="requests-tab" class="tab-content">';
        echo '<h3>Student Requests</h3>';
        $requests = $DB->get_records('local_mzi_requests', ['student_id' => $student->id], 'created_at DESC');
        if ($requests) {
            echo '<table class="data-table">';
            echo '<thead><tr><th>Request Type</th><th>Priority</th><th>Reason</th><th>Status</th><th>Admin Notes</th><th>Submitted</th></tr></thead>';
            echo '<tbody>';
            foreach ($requests as $request) {
                $status_colors = ['Approved' => '#d4edda;color:#155724', 'Rejected' => '#f8d7da;color:#721c24', 'Under Review' => '#fff3cd;color:#856404', 'Submitted' => '#d1ecf1;color:#0c5460'];
                $sc = $status_colors[$request->request_status] ?? '#e2e3e5;color:#383d41';
                $priority_colors = ['Urgent' => '#f8d7da;color:#721c24', 'High' => '#fde8d8;color:#8a3c0e', 'Medium' => '#fff3cd;color:#856404', 'Low' => '#e2e3e5;color:#383d41'];
                $pc = $priority_colors[$request->priority] ?? '#e2e3e5;color:#383d41';
                echo '<tr>';
                echo '<td>' . s($request->request_type ?: 'N/A') . '</td>';
                echo '<td><span style="padding:3px 8px;border-radius:4px;background:' . $pc . '">' . s($request->priority ?: 'N/A') . '</span></td>';
                echo '<td><small>' . s(mb_substr($request->reason ?: '', 0, 80)) . '</small></td>';
                echo '<td><span style="padding:3px 8px;border-radius:4px;background:' . $sc . '">' . s($request->request_status ?: 'N/A') . '</span></td>';
                echo '<td><small>' . s(mb_substr($request->admin_notes ?: '', 0, 80)) . '</small></td>';
                echo '<td>' . ($request->created_at ? userdate($request->created_at, '%d %b %Y') : 'N/A') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="no-results">No requests found</p>';
        }
        echo '</div>';
        
        echo '</div>'; // End student-detail
    } else {
        echo '<div class="no-results">Student not found</div>';
    }
}

?>

<?php
echo '</div>'; // Close mzi-page-container
echo $OUTPUT->footer();
?>
