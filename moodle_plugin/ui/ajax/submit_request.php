<?php
/**
 * AJAX handler: Submit a student request
 *
 * Accepts multipart/form-data (FormData) POST:
 *   sesskey, request_type, description, reason
 *   enrolled_classes[]      (Class Drop)
 *   enrolled_class_names[]  (Class Drop)
 *   unit_name               (Late Submission)
 *   receipt                 (Late Submission — file upload)
 *   change_field, change_current_value, change_requested_value (Change Information)
 *   id_document (file, required for Name Correction / Nationality changes)
 *
 * Output (JSON): { success, request_number, local_id, zoho_request_id, error }
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

require_login();

header('Content-Type: application/json');

// ── Parse input (FormData via $_POST / $_FILES) ────────────────────────────
$sesskey      = $_POST['sesskey']      ?? '';
$request_type = trim($_POST['request_type'] ?? '');
$description  = trim($_POST['description']  ?? '');
$reason       = trim($_POST['reason']       ?? '');
$note         = trim($_POST['note']         ?? '');

// ── CSRF validation ───────────────────────────────────────────────────────
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'error' => 'Invalid sesskey']);
    exit;
}

// ── Type validation ───────────────────────────────────────────────────────
$allowed_types = [
    'Enroll Next Semester',
    'Class Drop',
    'Late Submission',
    'Change Information',
    'Student Card',
];
if (!in_array($request_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid request type']);
    exit;
}
if (strlen($description) < 5) {
    echo json_encode(['success' => false, 'error' => 'Description too short']);
    exit;
}

// ── Get student record ────────────────────────────────────────────────────
$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$USER->id]
);
if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student record not found for your account']);
    exit;
}

// ── Check request window (config-based: 4 annual windows per type) ───────
$windowed_types = ['Enroll Next Semester', 'Class Drop'];
if (in_array($request_type, $windowed_types)) {
    $slug = preg_replace('/[^a-z0-9]/', '_', strtolower($request_type));
    $now  = time();
    $open = false;
    for ($n = 1; $n <= 4; $n++) {
        $date  = get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_date")  ?: '';
        $weeks = (int)(get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_weeks") ?: 0);
        if (!$date || !$weeks) continue;
        $start = strtotime($date);
        if ($start === false) continue;
        $end = $start + $weeks * 7 * 86400;
        if ($now >= $start && $now < $end) { $open = true; break; }
    }
    if (!$open) {
        // Find next window to give a helpful message
        $next = null;
        for ($n = 1; $n <= 4; $n++) {
            $date  = get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_date")  ?: '';
            $weeks = (int)(get_config('local_moodle_zoho_sync', "rw_{$slug}_{$n}_weeks") ?: 0);
            if (!$date || !$weeks) continue;
            $start = strtotime($date);
            if ($start !== false && $start > $now) {
                if ($next === null || $start < $next) $next = $start;
            }
        }
        $msg = $next
            ? 'Submission window is closed. Next opening: ' . date('M j, Y', $next)
            : 'This request type is not currently available.';
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
}

// ── Type-specific data ────────────────────────────────────────────────────
$requested_classes_json = null;  // stored in local_mzi_requests.requested_classes
$grade_details_json     = null;  // stored in local_mzi_requests.grade_details
$receipt_path           = null;

if ($request_type === 'Class Drop') {
    $class_ids   = array_map('intval', (array)($_POST['enrolled_classes']     ?? []));
    $class_names = array_map('trim',   (array)($_POST['enrolled_class_names'] ?? []));
    if (empty($class_ids)) {
        echo json_encode(['success' => false, 'error' => 'No classes selected for Class Drop']);
        exit;
    }
    // Verify these are actual Moodle enrollments of this user (security check)
    $month_ago = $now - (30 * 24 * 3600);
    $valid_ids  = [];
    foreach ($class_ids as $cid) {
        $ok = $DB->record_exists_sql(
            "SELECT 1 FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE ue.userid = ? AND c.id = ? AND ue.timecreated >= ? AND ue.status = 0",
            [$USER->id, $cid, $month_ago]
        );
        if ($ok) $valid_ids[] = $cid;
    }
    if (empty($valid_ids)) {
        echo json_encode(['success' => false, 'error' => 'None of the selected classes are valid recent enrollments']);
        exit;
    }
    $requested_classes_json = json_encode($valid_ids);
}

if ($request_type === 'Late Submission') {
    $unit_name = trim($_POST['unit_name'] ?? '');
    if (empty($unit_name)) {
        echo json_encode(['success' => false, 'error' => 'Unit name is required for Late Submission']);
        exit;
    }
    // Handle receipt upload
    if (!empty($_FILES['receipt']['tmp_name']) && is_uploaded_file($_FILES['receipt']['tmp_name'])) {
        $receipts_dir = $CFG->dataroot . '/local_mzi_receipts/';
        if (!is_dir($receipts_dir)) {
            @mkdir($receipts_dir, 0755, true);
        }
        $ext      = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $filename = 'receipt_' . $student->id . '_' . $now . '.' . ($ext ?: 'bin');
        $dest     = $receipts_dir . $filename;
        if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
            $receipt_path = 'local_mzi_receipts/' . $filename;
        }
    }
    $receipt_file_base64 = null;
    $receipt_file_name   = null;
    if ($receipt_path && file_exists($CFG->dataroot . '/' . $receipt_path)) {
        $receipt_file_base64 = base64_encode(file_get_contents($CFG->dataroot . '/' . $receipt_path));
        $receipt_file_name   = basename($receipt_path);
    }
    $grade_details_json = json_encode([
        'unit_name'        => $unit_name,
        'receipt_path'     => $receipt_path,
        'file_base64'      => $receipt_file_base64,
        'file_name'        => $receipt_file_name,
        'file_mime'        => $receipt_file_base64 ? ($_FILES['receipt']['type'] ?? 'application/octet-stream') : null,
    ]);
}

if ($request_type === 'Change Information') {
    $id_doc_path      = null;
    $id_file_base64   = null;
    $id_file_name     = null;
    $id_file_mime     = null;
    if (!empty($_FILES['id_document']['tmp_name']) && is_uploaded_file($_FILES['id_document']['tmp_name'])) {
        $receipts_dir = $CFG->dataroot . '/local_mzi_receipts/';
        if (!is_dir($receipts_dir)) {
            @mkdir($receipts_dir, 0755, true);
        }
        $ext      = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'pdf', 'gif', 'webp'];
        $ext      = in_array($ext, $allowed) ? $ext : 'bin';
        $filename = 'id_' . $student->id . '_' . $now . '.' . $ext;
        if (move_uploaded_file($_FILES['id_document']['tmp_name'], $receipts_dir . $filename)) {
            $id_doc_path    = 'local_mzi_receipts/' . $filename;
            $id_file_base64 = base64_encode(file_get_contents($receipts_dir . $filename));
            $id_file_name   = $filename;
            $id_file_mime   = $_FILES['id_document']['type'] ?? 'application/octet-stream';
        }
    }
    $grade_details_json = json_encode([
        'change_field'           => trim($_POST['change_field']           ?? ''),
        'change_current_value'   => trim($_POST['change_current_value']   ?? ''),
        'change_requested_value' => trim($_POST['change_requested_value'] ?? ''),
        'change_new_name'        => trim($_POST['change_new_name']        ?? ''),
        'id_document_path'       => $id_doc_path,
        'file_base64'            => $id_file_base64,
        'file_name'              => $id_file_name,
        'file_mime'              => $id_file_mime,
    ]);
}

// ── Generate request number ───────────────────────────────────────────────
$req_count = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_mzi_requests} WHERE student_id = ?",
    [$student->id]
);
$request_number = strtoupper(substr(str_replace(' ', '', $request_type), 0, 3))
                . '-' . date('Ymd') . '-' . sprintf('%04d', $req_count + 1);

// ── Insert local row ──────────────────────────────────────────────────────
$record                    = new stdClass();
$record->student_id        = $student->id;
$record->request_number    = $request_number;
$record->request_type      = $request_type;
$record->request_status    = 'Submitted';
$record->reason            = $reason;
$record->description       = $description;
$record->requested_classes = $requested_classes_json;
$record->grade_details     = $grade_details_json;
$record->created_at        = $now;
$record->updated_at        = $now;
$record->synced_at         = 0;

try {
    $local_id = $DB->insert_record('local_mzi_requests', $record);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB insert failed: ' . $e->getMessage()]);
    exit;
}

// ── Forward to backend ────────────────────────────────────────────────────
$backend_url = rtrim(get_config('local_moodle_zoho_sync', 'backend_url') ?: 'http://localhost:8001', '/');
$endpoint    = $backend_url . '/api/v1/requests/submit';

$payload = json_encode([
    'zoho_student_id'   => $student->zoho_student_id ?? null,
    'student_id_local'  => (int)$student->id,
    'moodle_user_id'    => (int)$USER->id,
    'moodle_request_id' => (int)$local_id,
    'request_number'    => $request_number,
    'request_type'      => $request_type,
    'description'       => $description,
    'reason'            => $reason,
    'note'              => $note ?: null,
    'student_name'      => (trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) !== '')
                            ? trim($student->first_name . ' ' . $student->last_name)
                            : trim($USER->firstname . ' ' . $USER->lastname),
    'academic_email'    => (!empty($student->academic_email) ? $student->academic_email
                            : (!empty($student->email) ? $student->email : $USER->email)),
    'extra'             => [
        'requested_classes' => $requested_classes_json ? json_decode($requested_classes_json) : null,
        'grade_details'     => $grade_details_json     ? json_decode($grade_details_json)     : null,
    ],
]);

$zoho_request_id = null;
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$resp      = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($resp && !$curl_err) {
    $resp_data = json_decode($resp, true);
    if (!empty($resp_data['zoho_request_id'])) {
        $zoho_request_id = $resp_data['zoho_request_id'];
        $DB->set_field('local_mzi_requests', 'zoho_request_id', $zoho_request_id, ['id' => $local_id]);
        $DB->set_field('local_mzi_requests', 'synced_at', $now, ['id' => $local_id]);
    }
}

// ── Return response ───────────────────────────────────────────────────────
echo json_encode([
    'success'         => true,
    'request_number'  => $request_number,
    'local_id'        => $local_id,
    'zoho_request_id' => $zoho_request_id,
    'backend_status'  => $http_code,
]);
exit;
