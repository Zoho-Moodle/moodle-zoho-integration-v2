<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Detailed Health Monitor Dashboard for Moodle-Zoho Integration
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/navigation.php');
require_once(__DIR__ . '/../../classes/config_manager.php');

use local_moodle_zoho_sync\config_manager;

admin_externalpage_setup('local_moodle_zoho_sync_health');

$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/health_monitor_detailed.php'));
$PAGE->set_title(get_string('pluginname', 'local_moodle_zoho_sync') . ' - Health Monitor');
$PAGE->set_heading(get_string('pluginname', 'local_moodle_zoho_sync') . ' - Health Monitor');

echo $OUTPUT->header();

mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('health_check', 'Health Monitor', 'Detailed system health and connectivity checks');
mzi_render_breadcrumb('Health Monitor Detailed');
echo '<div class="mzi-content-wrapper">';

echo $OUTPUT->heading('System Health Monitor', 2);

// Service names mapping
$services = [
    'backend_api' => 'Backend API Connection',
    'user_sync' => 'User Synchronization',
    'course_sync' => 'Course Synchronization',
    'enrollment_sync' => 'Enrollment Synchronization',
    'grade_sync' => 'Grade Synchronization',
    'learning_outcomes' => 'Learning Outcomes Sync'
];

// Get health status for each service
$health_data = [];
foreach ($services as $key => $name) {
    $status_json = config_manager::get("health_status_{$key}");
    $last_check = config_manager::get("health_last_check_{$key}");
    
    if ($status_json) {
        $status = json_decode($status_json, true);
        $status['last_check'] = $last_check ? $last_check : null;
        $health_data[$key] = $status;
    } else {
        $health_data[$key] = [
            'status' => 'unknown',
            'message' => 'No health data available. Run scheduled task.',
            'last_check' => null
        ];
    }
}

// Display overall status badge
$overall_status = 'ok';
foreach ($health_data as $data) {
    if ($data['status'] === 'error') {
        $overall_status = 'error';
        break;
    } elseif ($data['status'] === 'warning' && $overall_status === 'ok') {
        $overall_status = 'warning';
    }
}

$badge_class = $overall_status === 'ok' ? 'badge-success' : ($overall_status === 'warning' ? 'badge-warning' : 'badge-danger');
$badge_text = $overall_status === 'ok' ? 'Healthy' : ($overall_status === 'warning' ? 'Warning' : 'Critical');

echo '<div class="alert alert-info">';
echo '<strong>Overall System Status:</strong> <span class="badge ' . $badge_class . '">' . $badge_text . '</span>';
echo '</div>';

// Display service cards
echo '<div class="row">';

foreach ($services as $key => $name) {
    $data = $health_data[$key];
    $status = $data['status'];
    
    // Status badge
    if ($status === 'ok') {
        $card_class = 'border-success';
        $icon = '✓';
        $icon_class = 'text-success';
    } elseif ($status === 'warning') {
        $card_class = 'border-warning';
        $icon = '⚠';
        $icon_class = 'text-warning';
    } elseif ($status === 'error') {
        $card_class = 'border-danger';
        $icon = '✗';
        $icon_class = 'text-danger';
    } else {
        $card_class = 'border-secondary';
        $icon = '?';
        $icon_class = 'text-muted';
    }
    
    echo '<div class="col-md-6 mb-3">';
    echo '<div class="card ' . $card_class . '">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0"><span class="' . $icon_class . '">' . $icon . '</span> ' . htmlspecialchars($name) . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<p class="card-text"><strong>Status:</strong> ' . ucfirst($status) . '</p>';
    echo '<p class="card-text">' . htmlspecialchars($data['message']) . '</p>';
    
    // Display additional metrics if available
    if (isset($data['total'])) {
        echo '<ul class="list-unstyled">';
        echo '<li><strong>Total Events (24h):</strong> ' . $data['total'] . '</li>';
        echo '<li><strong>Sent:</strong> ' . ($data['sent'] ?? 0) . '</li>';
        echo '<li><strong>Failed:</strong> ' . ($data['failed'] ?? 0) . '</li>';
        echo '<li><strong>Success Rate:</strong> ' . ($data['success_rate'] ?? 0) . '%</li>';
        echo '</ul>';
    }
    
    if ($data['last_check']) {
        echo '<p class="text-muted small">Last checked: ' . userdate($data['last_check']) . '</p>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

echo '</div>';

// Refresh button
echo '<div class="mt-3">';
echo '<form method="post">';
echo '<button type="button" class="btn btn-primary" onclick="location.reload();">Refresh Status</button>';
echo ' <a href="' . new moodle_url('/admin/tool/task/scheduledtasks.php') . '" class="btn btn-secondary">Manage Scheduled Tasks</a>';
echo '</form>';
echo '</div>';

// Add some custom CSS
echo '<style>
.card {
    border-width: 2px;
}
.badge {
    font-size: 14px;
    padding: 5px 10px;
}
</style>';

echo '</div>'; // Close mzi-content-wrapper
echo '</div>'; // Close mzi-page-container

echo $OUTPUT->footer();
