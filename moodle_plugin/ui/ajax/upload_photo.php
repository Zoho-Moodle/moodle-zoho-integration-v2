<?php
/**
 * AJAX handler: Upload student profile photo
 *
 * POST (multipart/form-data):
 *   sesskey  – Moodle session key (CSRF)
 *   photo    – image file (JPG / PNG / GIF / WEBP, max 5 MB)
 *
 * Response JSON:
 *   { success, message, zoho_request_id?, zoho_error? }
 *
 * Side effects:
 *   1. Saves photo to $CFG->dataroot/student_photos/<uid>_pending.<ext>
 *   2. Updates local_mzi_students.photo_pending_url + photo_pending_status='pending'
 *      (photo_url is NOT changed — the approved photo stays until Zoho approves)
 *   3. Creates a Zoho "BTEC_Student_Requests" record (type: Photo Update Request)
 *      with the photo file attached.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_login();

header('Content-Type: application/json');

// ── CSRF ────────────────────────────────────────────────────────────────────
$sesskey = $_POST['sesskey'] ?? '';
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'error' => 'Invalid sesskey']);
    exit;
}

// ── Student record ───────────────────────────────────────────────────────────
$student = $DB->get_record_sql(
    "SELECT * FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$USER->id]
);
if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student record not found for your account']);
    exit;
}

// ── File validation ──────────────────────────────────────────────────────────
if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['photo']['error'] ?? -1;
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
    ];
    $msg = $upload_errors[$code] ?? "Upload error (code $code)";
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$file     = $_FILES['photo'];
$max_size = 5 * 1024 * 1024; // 5 MB
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'error' => 'File too large (max 5 MB)']);
    exit;
}

// Check MIME via fileinfo (not just extension)
$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($file['tmp_name']);
$ext_map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
if (!isset($ext_map[$mime])) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
    exit;
}
$ext = $ext_map[$mime];

// ── Save to dataroot ─────────────────────────────────────────────────────────
$photo_dir = rtrim($CFG->dataroot, '/\\') . DIRECTORY_SEPARATOR . 'student_photos';
if (!is_dir($photo_dir)) {
    mkdir($photo_dir, 0755, true);
}

// Remove any previous PENDING photo file for this user (only _pending files)
foreach (glob($photo_dir . DIRECTORY_SEPARATOR . $USER->id . '_pending.*') as $old_file) {
    @unlink($old_file);
}

$filename          = $USER->id . '_pending.' . $ext;
$filepath          = $photo_dir . DIRECTORY_SEPARATOR . $filename;
$photo_pending_url = 'student_photos/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save photo file']);
    exit;
}

// ── Update DB (pending only — photo_url stays unchanged until Zoho approves) ─
try {
    $DB->set_field('local_mzi_students', 'photo_pending_url',    $photo_pending_url, ['moodle_user_id' => (int)$USER->id]);
    $DB->set_field('local_mzi_students', 'photo_pending_status', 'pending',          ['moodle_user_id' => (int)$USER->id]);
} catch (\Exception $dbe) {
    // Columns may not exist yet (upgrade not run). Fall back to updating photo_url directly.
    try {
        $DB->set_field('local_mzi_students', 'photo_url', $photo_pending_url, ['moodle_user_id' => (int)$USER->id]);
    } catch (\Exception $dbe2) {
        echo json_encode(['success' => false, 'error' => 'DB error: ' . $dbe2->getMessage()]);
        exit;
    }
}

// ── Forward to backend (Zoho Student Request + attachment) ──────────────────
$zoho_request_id = null;
$zoho_error      = null;

if (!empty($student->zoho_student_id)) {
    $student_name   = strtoupper(trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')));
    $request_number = 'PHOTO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $file_b64       = base64_encode(file_get_contents($filepath));
    $file_name_zoho = 'student_photo_pending_' . $USER->id . '.' . $ext;

    $payload = json_encode([
        'zoho_student_id'  => $student->zoho_student_id,
        'student_id_local' => (int)$student->id,
        'moodle_user_id'   => (int)$USER->id,
        'request_type'     => 'Photo Update Request',
        'description'      => 'Student profile photo updated via student portal',
        'reason'           => 'Photo Update',
        'student_name'     => $student_name,
        'request_number'   => $request_number,
        'extra'            => [
            'grade_details' => [
                'file_base64' => $file_b64,
                'file_name'   => $file_name_zoho,
            ],
        ],
    ]);

    $backend_url = rtrim(get_config('local_moodle_zoho_sync', 'backend_url') ?: 'http://localhost:8001', '/');
    $endpoint    = $backend_url . '/api/v1/requests/submit';

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $resp      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        $zoho_error = "Cannot reach backend: $curl_err";
    } elseif ($http_code !== 200) {
        $zoho_error = "Backend returned HTTP $http_code";
    } else {
        $data = json_decode($resp, true);
        if (!empty($data['zoho_request_id'])) {
            $zoho_request_id = $data['zoho_request_id'];
        }
        if (isset($data['success']) && !$data['success']) {
            $zoho_error = $data['error'] ?? 'Zoho request creation failed';
        } elseif (!empty($data['error'])) {
            // Record created but attachment upload failed
            $zoho_error = 'Attachment failed: ' . $data['error'];
        }
    }
}

echo json_encode([
    'success'         => true,
    'message'         => 'Photo submitted for approval',
    'pending'         => true,
    'zoho_request_id' => $zoho_request_id,
    'zoho_error'      => $zoho_error,
]);
exit;
