<?php



$USER = (object)['id' => 1];


class MockDB {
    private $data;
    
    public function __construct() {
        $this->data = [
            'zoho_students' => [
                ['student_id' => 1, 'display_name' => 'Majd kuzbari', 'first_name' => 'majd', 'last_name' => 'kuzbari',
                 'status' => 'Active', 'phone_number' => '+1 234-567-8900', 'country' => 'syria',
                 'address' => 'aksamstin, istanbul', 'academic_email' => 'm.akuzabri@university.edu',
                 'birth_date' => '2004-05-15', 'city' => 'istanbul', 
                 'photo' => 'https://ui-avatars.com/api/?name=Majd+Kuzbari&size=200&background=667eea&color=fff&bold=true']
            ],
            'zoho_registrations' => [
                ['student_id' => 1, 'program' => 'Computer Science', 'major' => 'Software Engineering',
                 'study_language' => 'English', 'study_mode' => 'Full-time', 'registration_status' => 'Active',
                 'registration_date' => strtotime('2023-09-01'), 'program_price' => 5000],
                ['student_id' => 1, 'program' => 'Data Science', 'major' => 'Machine Learning',
                 'study_language' => 'English', 'study_mode' => 'Part-time', 'registration_status' => 'Active',
                 'registration_date' => strtotime('2023-10-01'), 'program_price' => 3000]
            ],
            'zoho_payments' => [
                ['student_id' => 1, 'payment_amount' => 2500, 'currency' => 'USD', 'payment_type' => 'Tuition',
                 'payment_method' => 'Credit Card', 'payment_date' => strtotime('2023-09-15')],
                ['student_id' => 1, 'payment_amount' => 1500, 'currency' => 'USD', 'payment_type' => 'Tuition',
                 'payment_method' => 'Bank Transfer', 'payment_date' => strtotime('2023-10-20')]
            ],
            'zoho_enrollments' => [
                ['student_id' => 1, 'class_name' => 'Introduction to Programming', 'class_teacher' => 'Dr. Smith',
                 'enrolled_program' => 'Computer Science', 'start_date' => strtotime('2023-09-01'),
                 'end_date' => strtotime('2023-12-15')],
                ['student_id' => 1, 'class_name' => 'Advanced Algorithms', 'class_teacher' => 'Prof. Johnson',
                 'enrolled_program' => 'Computer Science', 'start_date' => strtotime('2023-09-01'),
                 'end_date' => strtotime('2023-12-15')],
                ['student_id' => 1, 'class_name' => 'Machine Learning Basics', 'class_teacher' => 'Dr. Williams',
                 'enrolled_program' => 'Data Science', 'start_date' => strtotime('2023-10-01'),
                 'end_date' => strtotime('2024-01-30')]
            ],
            'zoho_grades' => [
                ['student_id' => 1, 'class_id' => 'CS101', 'grade_name' => 'Midterm Exam', 'grade' => 85,
                 'grader_name' => 'Dr. Smith', 'attempt_number' => 1, 'attempt_date' => strtotime('2023-10-15')],
                ['student_id' => 1, 'class_id' => 'CS101', 'grade_name' => 'Final Project', 'grade' => 92,
                 'grader_name' => 'Dr. Smith', 'attempt_number' => 1, 'attempt_date' => strtotime('2023-11-20')],
                ['student_id' => 1, 'class_id' => 'CS205', 'grade_name' => 'Quiz 1', 'grade' => 88,
                 'grader_name' => 'Prof. Johnson', 'attempt_number' => 1, 'attempt_date' => strtotime('2023-10-10')]
            ]
        ];
    }
    
    public function get_record($table, $conditions) {
        $records = $this->get_records($table, $conditions);
        return $records ? reset($records) : false;
    }
    
    public function get_records($table, $conditions) {
        if (!isset($this->data[$table])) return [];
        $result = [];
        foreach ($this->data[$table] as $record) {
            $match = true;
            foreach ($conditions as $key => $value) {
                if (!isset($record[$key]) || $record[$key] != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) $result[] = (object)$record;
        }
        return $result;
    }
}


function format_string($str) { return htmlspecialchars($str); }
function has_capability() { return true; }
function optional_param($name, $default, $type) {
    return isset($_GET[$name]) ? $_GET[$name] : $default;
}


class MockOutput {
    public function header() { return ''; }
    public function footer() { return ''; }
}


$DB = new MockDB();
$OUTPUT = new MockOutput();
$isadmin = has_capability('moodle/site:config', null);
$userid = optional_param('userid', 1, 1);
if (!$isadmin) {
    $userid = $USER->id;
}

$student = $DB->get_record('zoho_students', ['student_id' => $userid]);
if (!$student) {
    echo "<div class='alert alert-warning'>Student record not found in database.</div>";
    exit;
}


$programs = $DB->get_records('zoho_registrations', ['student_id' => $student->student_id]);


$payments = $DB->get_records('zoho_payments', ['student_id' => $student->student_id]);


$classes = $DB->get_records('zoho_enrollments', ['student_id' => $student->student_id]);


$grades = $DB->get_records('zoho_grades', ['student_id' => $student->student_id]);


$total_programs = count($programs);
$total_payments = 0;
$total_program_price = 0;
foreach ($payments as $p) {
    $total_payments += (float)$p->payment_amount;
}
foreach ($programs as $p) {
    $total_program_price += (float)$p->program_price;
}


$program_label = '';
if ($total_programs == 1) {
    $program_label = ' – ' . reset($programs)->program;
} elseif ($total_programs == 2) {
    $names = array_map(function($p) { return $p->program; }, $programs);
    $program_label = ' – ' . implode(' & ', $names);
}


$payment_text = 'Payments';
if ($total_program_price > 0) {
    $payment_text = number_format($total_payments, 0) . ' / ' .
                    number_format($total_program_price, 0) . ' USD';
}

$stats = [
    'name'      => $student->display_name ?? "{$student->first_name} {$student->last_name}",
    'programs'  => $total_programs,
    'program_label' => $program_label,
    'payments'  => $payment_text,
    'classes'   => count($classes),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
:root {
  --bg:#f8fafc; --card:#fff; --radius:16px;
  --shadow:0 4px 6px -1px rgba(0,0,0,0.1),0 2px 4px -1px rgba(0,0,0,0.06);
  --shadow-lg:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);
  --accent1:#3b82f6; --accent2:#f59e0b; --accent3:#10b981; --accent4:#f59e0b;
  --text:#0f172a; --muted:#64748b;
  --gradient1:linear-gradient(135deg,#3b82f6 0%,#10b981 50%,#f59e0b 100%);
  --gradient2:linear-gradient(135deg,#f59e0b 0%,#10b981 50%,#3b82f6 100%);
  --gradient3:linear-gradient(135deg,#3b82f6 0%,#10b981 50%,#06b6d4 100%);
  --gradient4:linear-gradient(135deg,#f59e0b 0%,#10b981 50%,#eab308 100%);
}
body { background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%); min-height:100vh; color:var(--text); font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
.kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; max-width:1400px; margin:0 auto; padding:0 30px 20px 30px; }
.kpi-card {
  background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow-lg);
  padding:24px; transition:all .3s ease; position:relative; overflow:hidden;
  border-left:4px solid;
}
.kpi-card:before { content:''; position:absolute; top:0; right:0; width:100px; height:100px; opacity:.05; border-radius:50%; transform:translate(30%,-30%); }
.kpi-card:nth-child(1) { border-left-color:#3b82f6; }
.kpi-card:nth-child(2) { border-left-color:#10b981; }
.kpi-card:nth-child(3) { border-left-color:#f59e0b; }
.kpi-card:nth-child(4) { border-left-color:#2F1D77; }
.kpi-card:hover { transform:translateY(-5px); box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); border-left-width:8px; margin-left:-4px; }
.kpi-card .icon { font-size:32px; margin-bottom:12px; }
.kpi-card .num { font-size:32px; font-weight:800; margin:8px 0; background:var(--gradient1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.kpi-card .label { font-size:14px; color:var(--muted); font-weight:500; text-transform:uppercase; letter-spacing:.5px; }
.headerx { display:flex; justify-content:space-between; align-items:center; max-width:1400px; margin:30px auto; padding:0 20px; flex-wrap:wrap; gap:15px; position:relative; }
.headerx .title { font-size:36px; font-weight:900; background:var(--gradient1); -webkit-background-clip:text; -webkit-text-fill-color:transparent; letter-spacing:-1px; }
.search-container { position:relative; min-width:300px; max-width:400px; margin-left:auto; }
.search-wrapper { position:relative; background:white; border-radius:50px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); transition:all .3s ease; overflow:hidden; border:2px solid transparent; }
.search-wrapper:hover { box-shadow:0 10px 20px -5px rgba(0,0,0,0.15); transform:translateY(-2px); }
.search-wrapper:focus-within { box-shadow:0 0 0 4px rgba(59,130,246,0.2),0 10px 20px -5px rgba(0,0,0,0.2); border-color:#3b82f6; }
.search-input { width:100%; padding:15px 50px 15px 55px; border:none; background:transparent; font-size:14px; color:var(--text); outline:0; }
.search-input::placeholder { color:#94a3b8; font-weight:400; }
.search-icon { position:absolute; left:18px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:20px; pointer-events:none; transition:all .3s; }
.search-wrapper:focus-within .search-icon { color:#3b82f6; transform:translateY(-50%) scale(1.1); }
.search-results { position:absolute; top:calc(100% + 10px); left:0; right:0; background:white; border-radius:16px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); max-height:350px; overflow-y:auto; z-index:9999; padding:10px; display:none; }
.theme-btn { border:none; background:white; border-radius:50%; width:50px; height:50px; font-size:1.5rem; cursor:pointer; transition:all .3s; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); }
.theme-btn:hover { transform:rotate(360deg); box-shadow:0 10px 20px -5px rgba(0,0,0,0.2); }
.id-card-btn { border:none; background:white; border-radius:50%; width:50px; height:50px; font-size:1.5rem; cursor:pointer; transition:all .3s; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); margin-left:10px; }
.id-card-btn:hover { transform:scale(1.1); box-shadow:0 10px 20px -5px rgba(0,0,0,0.2); }
.wrap { max-width:1400px; margin:0 auto 40px; background:var(--card); border-radius:20px; box-shadow:var(--shadow-lg); padding:30px; }
.nav-tabs { border-bottom:2px solid #e2e8f0; margin-bottom:25px; position:relative; }
.nav-tabs::after { content:''; position:absolute; bottom:-2px; left:var(--indicator-left, 12px); width:var(--indicator-width, 80px); height:4px; background:var(--gradient1); border-radius:4px 4px 0 0; transition:all .4s cubic-bezier(0.68,-0.55,0.265,1.55); z-index:1; }
.nav-tabs .nav-link { border:0; color:var(--muted); font-weight:600; padding:12px 24px; margin-right:8px; border-radius:12px; transition:all .3s; position:relative; z-index:2; }
.nav-tabs .nav-link:hover { background:#f1f5f9; }
.nav-tabs .nav-link.active { color:var(--accent1) !important; font-weight:700; background:transparent; }
.table { margin-top:20px; }
.table thead { background:#f8fafc; }
.table thead th { font-weight:700; text-transform:uppercase; font-size:12px; letter-spacing:.5px; color:var(--muted); padding:15px; }
.table tbody tr { transition:all .2s; border-bottom:1px solid #f1f5f9; }
.table tbody tr:hover { background:#f8fafc; transform:scale(1.01); }
.dark-mode .table { background-color:#334155 !important; color:#f1f5f9 !important; }
.dark-mode .table thead { background-color:#475569 !important; }
.dark-mode .table thead th { color:#f1f5f9 !important; background-color:#475569 !important; }
.dark-mode .table tbody tr { background-color:#334155 !important; color:#f1f5f9 !important; }
.dark-mode .table tbody tr:hover { background:#475569 !important; }
.dark-mode .table tbody td { color:#f1f5f9 !important; background-color:#334155 !important; }
.dark-mode .table tbody th { color:#f1f5f9 !important; background-color:#334155 !important; }
.dark-mode .wrap { background-color:#334155 !important; }
.dark-mode .tab-content { background-color:#334155 !important; }
.dark-mode .search-wrapper { background-color:#334155 !important; border-color:#475569 !important; }
.dark-mode .search-input { color:#f1f5f9 !important; background-color:#334155 !important; }
.dark-mode .search-input::placeholder { color:#94a3b8 !important; }
.dark-mode .search-icon { color:#94a3b8 !important; }
.dark-mode .search-wrapper:focus-within { border-color:#3b82f6 !important; box-shadow:0 0 0 4px rgba(59,130,246,0.2),0 10px 20px -5px rgba(0,0,0,0.2) !important; }
.dark-mode .search-wrapper:focus-within .search-icon { color:#3b82f6 !important; }
.dark-mode .theme-btn { background-color:#334155 !important; color:#f1f5f9 !important; }
.dark-mode .id-card-btn { background-color:#334155 !important; color:#f1f5f9 !important; }
.badge { padding:6px 12px; border-radius:20px; font-weight:600; font-size:11px; text-transform:uppercase; }
.badge-success { background:#d1fae5; color:#065f46; }
.badge-warning { background:#fef3c7; color:#92400e; }
.badge-info { background:#dbeafe; color:#1e40af; }
.profile-section h5 { font-size:20px; font-weight:700; margin-bottom:20px; color:var(--text); }
.profile-photo { width:120px; height:120px; border-radius:50%; border:4px solid white; box-shadow:0 8px 24px rgba(0,0,0,0.15); margin-right:20px; object-fit:cover; transition:all .3s; cursor:pointer; }
.profile-photo:hover { transform:scale(1.05); box-shadow:0 12px 32px rgba(0,0,0,0.2); }
.header-profile { display:flex; align-items:center; gap:20px; }
.academy-logo { height:100px; width:auto; object-fit:contain; border-radius:12px; padding:15px; background:white; box-shadow:0 8px 16px rgba(0,0,0,0.15); transition:all .3s; }
.academy-logo:hover { transform:scale(1.08); box-shadow:0 12px 24px rgba(0,0,0,0.2); }
.logo-container { display:flex; align-items:center; gap:15px; }
    </style>
</head>
<body>

<div class="headerx">
  <div class="header-profile">
    <img src="<?= $student->photo ?? 'https://ui-avatars.com/api/?name='.urlencode($student->display_name).'&size=120&background=667eea&color=fff' ?>" 
         alt="<?= htmlspecialchars($student->display_name) ?>"
         class="profile-photo" id="profilePhoto" title="Click to generate ID card">
    <div>
      <div style="display:flex; align-items:center; gap:20px;">
        <div class="title">🎓 Student Dashboard</div>
        <img src="logo.png" alt="Academy Logo" class="academy-logo" style="height:100px; margin-left:580px;" onerror="this.style.display='none'">
      </div>
      <p style="margin:5px 0 0 0; color:var(--muted); font-size:16px;"><?= htmlspecialchars($student->display_name) ?></p>
      <p style="margin:3px 0 0 0; color:var(--muted); font-size:14px; font-style:italic;">Welcome, <?= htmlspecialchars($student->first_name) ?>!</p>
    </div>
  </div>
  <div class="search-container">
    <div class="search-wrapper">
      <div class="search-icon">🔍</div>
      <input type="text" class="search-input" placeholder="Search students, classes, grades..." id="searchInput">
      <div class="search-results" id="searchResults"></div>
    </div>
  </div>
  <div class="logo-container">
    <button id="themeToggle" class="theme-btn" title="Toggle theme">🌙</button>
  </div>
</div>

<!-- KPIs -->
<div class="kpis">
  <div class="kpi-card">
    <div class="icon">👤</div>
    <div class="num"><?= format_string($stats['name']) ?></div>
    <div class="label">Active Student</div>
  </div>
  <div class="kpi-card">
    <div class="icon">📚</div>
    <div class="num"><?= $stats['programs'] ?></div>
    <div class="label">Programs<?= $stats['program_label'] ?></div>
  </div>
  <div class="kpi-card">
    <div class="icon">💳</div>
    <div class="num"><?= count($payments) ?></div>
    <div class="label"><?= $stats['payments'] ?></div>
  </div>
  <div class="kpi-card">
    <div class="icon">🏫</div>
    <div class="num"><?= $stats['classes'] ?></div>
    <div class="label">Enrolled Classes</div>
  </div>
</div>

<div class="wrap mt-4">
  <ul class="nav nav-tabs" id="tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tProfile">Profile</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tAcad">Academics</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tPay">Payments</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tClasses">Classes</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tGrades">Grades</button></li>
  </ul>

  <div class="tab-content p-3">
    <!-- Profile -->
    <div class="tab-pane fade show active" id="tProfile">
      <h5 class="profile-section">👤 Profile Information</h5>
      <table class="table table-hover">
        <tr><th>Status:</th><td><span class="badge badge-success"><?= $student->status ?></span></td></tr>
        <tr><th>Full Name:</th><td><strong><?= $student->display_name ?></strong></td></tr>
        <tr><th>Phone:</th><td><?= $student->phone_number ?></td></tr>
        <tr><th>Country:</th><td><?= $student->country ?></td></tr>
        <tr><th>Address:</th><td><?= $student->address ?></td></tr>
        <tr><th>Academic Email:</th><td><a href="mailto:<?= $student->academic_email ?>"><?= $student->academic_email ?></a></td></tr>
        <tr><th>Date of Birth:</th><td><?= $student->birth_date ?></td></tr>
        <tr><th>City:</th><td><?= $student->city ?></td></tr>
      </table>
    </div>

    <!-- Academics -->
    <div class="tab-pane fade" id="tAcad">
      <h5>📘 Enrolled Programs</h5>
      <?php if ($programs): ?>
        <table class="table table-striped table-sm">
          <thead><tr><th>Program</th><th>Major</th><th>Language</th><th>Mode</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($programs as $p): ?>
            <tr>
              <td><?= $p->program ?></td>
              <td><?= $p->major ?></td>
              <td><?= $p->study_language ?></td>
              <td><?= $p->study_mode ?></td>
              <td><span class="badge badge-success"><?= $p->registration_status ?></span></td>
              <td><?= $p->registration_date ? date('Y-m-d', $p->registration_date) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No registrations found.</p>
      <?php endif; ?>
    </div>

    <!-- Payments -->
    <div class="tab-pane fade" id="tPay">
      <h5>💰 Payments</h5>
      <?php if ($payments): ?>
        <table class="table table-striped table-sm">
          <thead><tr><th>Amount</th><th>Currency</th><th>Type</th><th>Method</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($payments as $pay): ?>
            <tr>
              <td><?= number_format($pay->payment_amount, 2) ?></td>
              <td><?= $pay->currency ?></td>
              <td><?= $pay->payment_type ?></td>
              <td><?= $pay->payment_method ?></td>
              <td><?= $pay->payment_date ? date('Y-m-d', $pay->payment_date) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No payments found.</p>
      <?php endif; ?>
    </div>

    <!-- Classes -->
    <div class="tab-pane fade" id="tClasses">
      <h5>🏫 Enrolled Classes</h5>
      <?php if ($classes): ?>
        <table class="table table-striped table-sm">
          <thead><tr><th>Class</th><th>Teacher</th><th>Program</th><th>Start</th><th>End</th></tr></thead>
          <tbody>
          <?php foreach ($classes as $c): ?>
            <tr>
              <td><?= $c->class_name ?></td>
              <td><?= $c->class_teacher ?></td>
              <td><?= $c->enrolled_program ?></td>
              <td><?= $c->start_date ? date('Y-m-d', $c->start_date) : '' ?></td>
              <td><?= $c->end_date ? date('Y-m-d', $c->end_date) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No classes found.</p>
      <?php endif; ?>
    </div>

    <!-- Grades -->
    <div class="tab-pane fade" id="tGrades">
      <h5>🧾 Grades</h5>
      <?php if ($grades): ?>
        <table class="table table-striped table-sm">
          <thead><tr><th>Class</th><th>Grade Item</th><th>Grade</th><th>Grader</th><th>Attempt</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($grades as $g): ?>
            <tr>
              <td><?= $g->class_id ?></td>
              <td><?= $g->grade_name ?></td>
              <td><?= $g->grade ?></td>
              <td><?= $g->grader_name ?></td>
              <td><?= $g->attempt_number ?></td>
              <td><?= $g->attempt_date ? date('Y-m-d', $g->attempt_date) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>No grades found.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('themeToggle').addEventListener('click', function() {
    const root = document.documentElement;
    const body = document.body;
    const isDark = body.classList.contains('dark-mode');
    if (isDark) {
        root.style.setProperty('--bg', '#f9fafc');
        root.style.setProperty('--card', '#fff');
        root.style.setProperty('--text', '#1e293b');
        root.style.setProperty('--muted', '#6b7280');
        body.style.background = 'linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%)';
        body.classList.remove('dark-mode');
        this.textContent = '🌙';
    } else {
        root.style.setProperty('--bg', '#1e293b');
        root.style.setProperty('--card', '#334155');
        root.style.setProperty('--text', '#f1f5f9');
        root.style.setProperty('--muted', '#94a3b8');
        body.style.background = 'linear-gradient(135deg,#1e293b 0%,#334155 100%)';
        body.classList.add('dark-mode');
        this.textContent = '☀️';
    }
});


const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
const navTabs = document.querySelector('.nav-tabs');

function updateIndicator() {
    const activeLink = navTabs.querySelector('.nav-link.active');
    if (activeLink) {
        const navRect = navTabs.getBoundingClientRect();
        const linkRect = activeLink.getBoundingClientRect();
        const offsetLeft = linkRect.left - navRect.left;
        const width = linkRect.width;
        
        navTabs.style.setProperty('--indicator-left', offsetLeft + 'px');
        navTabs.style.setProperty('--indicator-width', width + 'px');
    }
}

tabLinks.forEach((link, index) => {
    link.addEventListener('click', function() {
        setTimeout(updateIndicator, 10);
    });
});

updateIndicator();
window.addEventListener('resize', updateIndicator);


document.getElementById('profilePhoto').addEventListener('click', function() {
    generateStudentIdCard();
});

function generateStudentIdCard() {
  
    const studentData = {
        id: <?= $student->student_id ?>,
        name: '<?= addslashes($student->display_name) ?>',
        email: '<?= addslashes($student->academic_email) ?>',
        phone: '<?= addslashes($student->phone_number) ?>',
        country: '<?= addslashes($student->country) ?>',
        city: '<?= addslashes($student->city) ?>',
        birthDate: '<?= addslashes($student->birth_date) ?>',
        photo: '<?= addslashes($student->photo) ?>',
        status: '<?= addslashes($student->status) ?>'
    };
    
   
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); z-index: 10000; display: flex; 
        align-items: center; justify-content: center; padding: 20px;
    `;
    
    const idCard = document.createElement('div');
    idCard.style.cssText = `
        background: linear-gradient(135deg, #3b82f6 0%, #10b981 50%, #f59e0b 100%);
        border-radius: 20px; padding: 30px; max-width: 400px; width: 100%;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        color: white; text-align: center; position: relative;
    `;
    
    idCard.innerHTML = `
        <div style="position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold;">
            ${studentData.status}
        </div>
        <div style="margin-bottom: 20px;">
            <img src="${studentData.photo}" alt="Student Photo" style="width: 120px; height: 120px; border-radius: 50%; border: 4px solid white; object-fit: cover;">
        </div>
        <h2 style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold;">${studentData.name}</h2>
        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; margin: 15px 0;">
            <div style="font-size: 18px; font-weight: bold; margin-bottom: 5px;">Student ID: ${studentData.id}</div>
            <div style="font-size: 14px; opacity: 0.9;">${studentData.email}</div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 15px 0;">
            <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.8;">Phone</div>
                <div style="font-weight: bold;">${studentData.phone}</div>
            </div>
            <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 8px;">
                <div style="font-size: 12px; opacity: 0.8;">Location</div>
                <div style="font-weight: bold;">${studentData.city}, ${studentData.country}</div>
            </div>
        </div>
        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <button onclick="downloadIdCard()" style="flex: 1; background: rgba(255,255,255,0.2); border: none; color: white; padding: 10px; border-radius: 8px; cursor: pointer; font-weight: bold;">
                📥 Download
            </button>
            <button onclick="closeIdCard()" style="flex: 1; background: rgba(255,255,255,0.2); border: none; color: white; padding: 10px; border-radius: 8px; cursor: pointer; font-weight: bold;">
                ✕ Close
            </button>
        </div>
    `;
    
    modal.appendChild(idCard);
    document.body.appendChild(modal);
    
 
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeIdCard();
        }
    });
    

    window.downloadIdCard = function() {
  
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = 400;
        canvas.height = 600;
        
 
        const gradient = ctx.createLinearGradient(0, 0, 400, 600);
        gradient.addColorStop(0, '#3b82f6');
        gradient.addColorStop(0.5, '#10b981');
        gradient.addColorStop(1, '#f59e0b');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, 400, 600);
        

        ctx.fillStyle = 'white';
        ctx.font = 'bold 24px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(studentData.name, 200, 200);
        
        ctx.font = '18px Arial';
        ctx.fillText(`Student ID: ${studentData.id}`, 200, 250);
        

        const link = document.createElement('a');
        link.download = `student-id-${studentData.id}.png`;
        link.href = canvas.toDataURL();
        link.click();
    };
    
    window.closeIdCard = function() {
        document.body.removeChild(modal);
    };
}
</script>

</body>
</html>