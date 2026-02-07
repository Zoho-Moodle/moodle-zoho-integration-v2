<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

global $DB, $USER;

// ✅ تحديد القسم المطلوب والطالب
$section = optional_param('section', 'profile', PARAM_TEXT);
$userid  = optional_param('userid', 0, PARAM_INT);
if (!$userid) exit('Missing userid');

// ✅ جلب معلومات الطالب الأساسية
$student = $DB->get_record('student_profile', ['userid' => $userid]);
if (!$student) {
    echo "<div style='padding:10px;color:#888'>Student not found.</div>";
    exit;
}

$zohoid = $student->zoho_id;

// ✅ جمع الإحصاءات العامة (KPIs)
$stats = [
    'name'     => $student->display_name ?: fullname($USER),
    'programs' => $DB->count_records('zoho_registrations', ['student_zoho_id' => $zohoid]),
    'payments' => $DB->count_records('zoho_payments', ['student_zoho_id' => $zohoid]),
    'classes'  => $DB->count_records('zoho_enrollments', ['student_zoho_id' => $zohoid]),
    'grades'   => $DB->count_records('zoho_grades', ['student_zoho_id' => $zohoid]),
    'finance'  => $DB->count_records('financeinfo', ['userid' => $userid])
];

// ✅ نطبع JSON مخفي ليستخدمه الـ JS لتحديث الكروت
echo "<script id='metaJson' type='application/json'>" . json_encode($stats) . "</script>";


// ========================= PROFILE TAB =========================
if ($section === 'profile') {

    echo "<div style='padding:15px'>";
    echo "<h5>👤 Profile Information</h5>";
    echo "<table class='table table-sm table-bordered' style='max-width:600px'>
            <tr><th>Name</th><td>".s($student->display_name)."</td></tr>
            <tr><th>Zoho ID</th><td>".s($student->zoho_id)."</td></tr>
            <tr><th>Academic Email</th><td>".s($student->academic_email)."</td></tr>
            <tr><th>Country</th><td>".s($student->country)."</td></tr>
            <tr><th>City</th><td>".s($student->city)."</td></tr>
            <tr><th>Status</th><td>".s($student->status)."</td></tr>
          </table>";
    echo "</div>";
    exit;
}


// ========================= ACADEMICS TAB =========================
if ($section === 'academics') {
    $records = $DB->get_records('zoho_registrations', ['student_zoho_id' => $zohoid]);
    echo "<div style='padding:15px'>";
    echo "<h5>🎓 Academic Registrations</h5>";
    if (!$records) { echo "<div>No records found.</div>"; exit; }

    echo "<table class='table table-striped table-sm'>
            <tr><th>Program</th><th>Major</th><th>Price</th><th>Status</th></tr>";
    foreach ($records as $r) {
        echo "<tr>
                <td>".s($r->program)."</td>
                <td>".s($r->major)."</td>
                <td>".s($r->program_price)."</td>
                <td>".s($r->registration_status)."</td>
              </tr>";
    }
    echo "</table></div>";
    exit;
}


// ========================= FINANCE TAB =========================
if ($section === 'finance') {
    $info = $DB->get_records('financeinfo', ['userid' => $userid]);
    echo "<div style='padding:15px'>";
    echo "<h5>💰 Finance Information</h5>";

    if (!$info) { echo "<div>No finance info found.</div>"; exit; }

    foreach ($info as $f) {
        echo "<div style='margin-bottom:20px;padding:10px;border:1px solid #ddd;border-radius:8px'>";
        echo "<b>Currency:</b> ".s($f->currency)." | ";
        echo "<b>Payment Method:</b> ".s($f->payment_method)." | ";
        echo "<b>Total:</b> ".s($f->total_amount)."<br>";
        echo "<b>Discount:</b> ".s($f->discount_amount)." | ";
        echo "<b>Scholarship:</b> ".s($f->scholarship)." (".s($f->scholarship_percentage)."%)<br>";

        // Payments for this finance info
        $pays = $DB->get_records('financeinfo_payments', ['financeinfoid' => $f->id]);
        if ($pays) {
            echo "<table class='table table-sm table-bordered' style='margin-top:8px'>
                    <tr><th>Payment Name</th><th>Amount</th><th>Date</th><th>Invoice</th></tr>";
            foreach ($pays as $p) {
                echo "<tr>
                        <td>".s($p->payment_name)."</td>
                        <td>".s($p->amount)."</td>
                        <td>".($p->payment_date ? date('Y-m-d', $p->payment_date) : '')."</td>
                        <td>".s($p->invoice_number)."</td>
                      </tr>";
            }
            echo "</table>";
        } else {
            echo "<div style='opacity:.7'>No payments found.</div>";
        }
        echo "</div>";
    }
    echo "</div>";
    exit;
}


// ========================= CLASSES TAB =========================
if ($section === 'classes') {
    $classes = $DB->get_records('zoho_enrollments', ['student_zoho_id' => $zohoid]);
    echo "<div style='padding:15px'>";
    echo "<h5>📚 Enrolled Classes</h5>";
    if (!$classes) { echo "<div>No classes found.</div>"; exit; }

    echo "<table class='table table-striped table-sm'>
            <tr><th>Class Name</th><th>Teacher</th><th>Program</th><th>Start</th><th>End</th></tr>";
    foreach ($classes as $c) {
        echo "<tr>
                <td>".s($c->class_name)."</td>
                <td>".s($c->class_teacher)."</td>
                <td>".s($c->enrolled_program)."</td>
                <td>".($c->start_date ? date('Y-m-d', $c->start_date) : '')."</td>
                <td>".($c->end_date ? date('Y-m-d', $c->end_date) : '')."</td>
              </tr>";
    }
    echo "</table></div>";
    exit;
}


// ========================= GRADES TAB =========================
if ($section === 'grades') {
    $grades = $DB->get_records('zoho_grades', ['student_zoho_id' => $zohoid]);
    echo "<div style='padding:15px'>";
    echo "<h5>🏆 Grades</h5>";
    if (!$grades) { echo "<div>No grades found.</div>"; exit; }

    echo "<table class='table table-striped table-sm'>
            <tr><th>Class</th><th>Grade</th><th>Assessor</th><th>Feedback</th></tr>";
    foreach ($grades as $g) {
        echo "<tr>
                <td>".s($g->btec_grade_name)."</td>
                <td>".s($g->grade)."</td>
                <td>".s($g->grader_name)."</td>
                <td>".s($g->feedback)."</td>
              </tr>";
    }
    echo "</table></div>";
    exit;
}

?>
