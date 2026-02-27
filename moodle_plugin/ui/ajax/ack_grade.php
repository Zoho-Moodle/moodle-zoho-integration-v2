<?php
/**
 * AJAX handler: Acknowledge a grade / feedback
 *
 * Sets local_mzi_grades.feedback_acknowledged = 1 and records the timestamp.
 * Students may only acknowledge their own grades.
 *
 * Input  (JSON body): { sesskey, grade_id }
 * Output (JSON):      { success: bool, ack_date: str, error: str }
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

require_login();

header('Content-Type: application/json');

// ── Parse JSON body ───────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

// ── CSRF validation ───────────────────────────────────────────────────────
$sesskey = $data['sesskey'] ?? '';
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'error' => 'Invalid sesskey']);
    exit;
}

// ── Validate grade_id ─────────────────────────────────────────────────────
$grade_id = (int)($data['grade_id'] ?? 0);
if ($grade_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid grade_id']);
    exit;
}

// ── Get student record ────────────────────────────────────────────────────
$student = $DB->get_record_sql(
    "SELECT id FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$USER->id]
);
if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student record not found']);
    exit;
}

// ── Verify grade belongs to this student ─────────────────────────────────
$grade = $DB->get_record('local_mzi_grades', ['id' => $grade_id]);
if (!$grade) {
    echo json_encode(['success' => false, 'error' => 'Grade not found']);
    exit;
}
if ((int)$grade->student_id !== (int)$student->id) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}
if ($grade->feedback_acknowledged) {
    // Already acknowledged — idempotent: return success
    echo json_encode([
        'success'  => true,
        'ack_date' => userdate($grade->feedback_acknowledged_at, '%d %b %Y'),
    ]);
    exit;
}

// ── Update ────────────────────────────────────────────────────────────────
$now = time();
$DB->set_field('local_mzi_grades', 'feedback_acknowledged',    1,    ['id' => $grade_id]);
$DB->set_field('local_mzi_grades', 'feedback_acknowledged_at', $now, ['id' => $grade_id]);

echo json_encode([
    'success'  => true,
    'ack_date' => userdate($now, '%d %b %Y'),
]);
exit;
