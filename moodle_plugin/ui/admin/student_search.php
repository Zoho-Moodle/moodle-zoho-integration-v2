<?php
/**
 * Admin Student Search and View
 * 
 * Allows administrators to search for students and view their complete data
 * Search by: name, username, or academic email
 */

require_once(__DIR__ . '/../../../../../config.php');
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
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    button.classList.add('active');
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
        echo '<button class="tab-button" onclick="switchTab(\'programs\', this)">Programs</button>';
        echo '<button class="tab-button" onclick="switchTab(\'classes\', this)">Classes</button>';
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
        
        // Programs Tab
        echo '<div id="programs-tab" class="tab-content">';
        echo '<h3>Enrolled Programs</h3>';
        $programs = $DB->get_records('local_mzi_enrollments', ['zoho_student_id' => $student->zoho_student_id]);
        if ($programs) {
            echo '<table class="data-table">';
            echo '<thead><tr><th>Program Name</th><th>Enrollment Date</th><th>Status</th><th>Expected Graduation</th></tr></thead>';
            echo '<tbody>';
            foreach ($programs as $program) {
                echo '<tr>';
                echo '<td>' . s(isset($program->program_name) ? $program->program_name : 'N/A') . '</td>';
                echo '<td>' . s($program->enrollment_date ?: 'N/A') . '</td>';
                echo '<td>' . s($program->enrollment_status ?: 'N/A') . '</td>';
                echo '<td>' . s(isset($program->expected_graduation_date) ? $program->expected_graduation_date : 'N/A') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="no-results">No programs found</p>';
        }
        echo '</div>';
        
        // Classes Tab
        echo '<div id="classes-tab" class="tab-content">';
        echo '<h3>Registered Classes</h3>';
        $classes = $DB->get_records('local_mzi_classes', ['zoho_student_id' => $student->zoho_student_id]);
        if ($classes) {
            echo '<table class="data-table">';
            echo '<thead><tr><th>Class Name</th><th>Section</th><th>Instructor</th><th>Schedule</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            foreach ($classes as $class) {
                echo '<tr>';
                echo '<td>' . s($class->class_name ?: 'N/A') . '</td>';
                echo '<td>' . s($class->section ?: 'N/A') . '</td>';
                echo '<td>' . s($class->instructor_name ?: 'N/A') . '</td>';
                echo '<td>' . s($class->class_schedule ?: 'N/A') . '</td>';
                echo '<td>' . s($class->registration_status ?: 'N/A') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="no-results">No classes found</p>';
        }
        echo '</div>';
        
        // Requests Tab
        echo '<div id="requests-tab" class="tab-content">';
        echo '<h3>Student Requests</h3>';
        $requests = $DB->get_records('local_mzi_requests', ['zoho_student_id' => $student->zoho_student_id]);
        if ($requests) {
            echo '<table class="data-table">';
            echo '<thead><tr><th>Request Type</th><th>Subject</th><th>Status</th><th>Submitted</th></tr></thead>';
            echo '<tbody>';
            foreach ($requests as $request) {
                echo '<tr>';
                echo '<td>' . s($request->request_type ?: 'N/A') . '</td>';
                echo '<td>' . s($request->request_subject ?: 'N/A') . '</td>';
                echo '<td>' . s($request->request_status ?: 'N/A') . '</td>';
                echo '<td>' . s($request->submission_date ?: 'N/A') . '</td>';
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
echo $OUTPUT->footer();
?>
