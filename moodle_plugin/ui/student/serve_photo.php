<?php
/**
 * Student Photo Server
 *
 * Serves student photos stored in $CFG->dataroot/student_photos/ securely through Moodle.
 * Photos are stored outside the webroot for security; this script proxies them.
 *
 * Usage:
 *   /local/moodle_zoho_sync/ui/student/serve_photo.php?uid=<moodle_user_id>
 *   /local/moodle_zoho_sync/ui/student/serve_photo.php        (uses current logged-in user)
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', false);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../../../config.php');
require_login();

$uid = optional_param('uid', $USER->id, PARAM_INT);

// Only allow admins to view other students' photos
if ($uid !== (int)$USER->id) {
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
}

// Look up the student photo_url
$student = $DB->get_record_sql(
    "SELECT photo_url FROM {local_mzi_students} WHERE moodle_user_id = ? LIMIT 1",
    [$uid]
);

if (!$student || empty($student->photo_url)) {
    // Return a default placeholder (1x1 transparent gif)
    header('Content-Type: image/gif');
    header('Cache-Control: public, max-age=86400');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// Sanitize and resolve path
$relative_path = ltrim($student->photo_url, '/\\');
$filepath = $CFG->dataroot . DIRECTORY_SEPARATOR . str_replace(['/', '\\', '..'], [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, ''], $relative_path);

if (!file_exists($filepath) || !is_file($filepath)) {
    // Return placeholder
    header('Content-Type: image/gif');
    header('Cache-Control: public, max-age=86400');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// Determine MIME type
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
$mime_map = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];
$mime_type = $mime_map[$ext] ?? 'image/jpeg';

// Stream the file
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath)) . ' GMT');

// ETag for browser caching
$etag = md5_file($filepath);
header('ETag: "' . $etag . '"');

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === '"' . $etag . '"') {
    http_response_code(304);
    exit;
}

readfile($filepath);
exit;
