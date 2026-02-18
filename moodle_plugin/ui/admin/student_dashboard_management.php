<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Student Dashboard Management - Admin Interface
 * 
 * Provides centralized management for Student Dashboard:
 * - Statistics overview (total students, registrations, payments, etc.)
 * - Student requests management (approve/reject/review)
 * - Data synchronization monitoring
 * - Dashboard settings configuration
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Ensure user is logged in and has admin capabilities
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up page
$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/student_dashboard_management.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('student_dashboard_management', 'local_moodle_zoho_sync'));
$PAGE->set_heading(get_string('student_dashboard_management', 'local_moodle_zoho_sync'));

// Navigation breadcrumb
$PAGE->navbar->add(get_string('administration'), new moodle_url('/admin'));
$PAGE->navbar->add(get_string('pluginname', 'local_moodle_zoho_sync'));
$PAGE->navbar->add(get_string('student_dashboard_management', 'local_moodle_zoho_sync'));

// Handle actions (approve/reject requests)
$action = optional_param('action', '', PARAM_ALPHA);
$requestid = optional_param('requestid', 0, PARAM_INT);

if ($action && $requestid && confirm_sesskey()) {
    if ($action === 'approve') {
        // Update request status to approved
        $DB->set_field('local_mzi_requests', 'request_status', 'Approved', ['id' => $requestid]);
        $DB->set_field('local_mzi_requests', 'reviewed_by', $USER->id, ['id' => $requestid]);
        $DB->set_field('local_mzi_requests', 'reviewed_at', time(), ['id' => $requestid]);
        
        redirect($PAGE->url, get_string('request_approved', 'local_moodle_zoho_sync'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'reject') {
        // Update request status to rejected
        $DB->set_field('local_mzi_requests', 'request_status', 'Rejected', ['id' => $requestid]);
        $DB->set_field('local_mzi_requests', 'reviewed_by', $USER->id, ['id' => $requestid]);
        $DB->set_field('local_mzi_requests', 'reviewed_at', time(), ['id' => $requestid]);
        
        redirect($PAGE->url, get_string('request_rejected', 'local_moodle_zoho_sync'), null, \core\output\notification::NOTIFY_WARNING);
    }
}

// Check if tables exist (they might not be created yet)
$dbman = $DB->get_manager();
$students_table_exists = $dbman->table_exists(new xmldb_table('local_mzi_students'));

if (!$students_table_exists) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Student Dashboard tables have not been created yet. Please run the database upgrade first.', 'error');
    echo '<div style="padding: 20px;">';
    echo '<h3>Missing Tables</h3>';
    echo '<p>The following tables are required but do not exist:</p>';
    echo '<ul>';
    echo '<li>local_mzi_students</li>';
    echo '<li>local_mzi_registrations</li>';
    echo '<li>local_mzi_installments</li>';
    echo '<li>local_mzi_payments</li>';
    echo '<li>local_mzi_classes</li>';
    echo '<li>local_mzi_enrollments</li>';
    echo '<li>local_mzi_grades</li>';
    echo '<li>local_mzi_requests</li>';
    echo '</ul>';
    echo '<p><strong>To fix this:</strong></p>';
    echo '<ol>';
    echo '<li>Go to: Site administration → Notifications</li>';
    echo '<li>Or run: <code>php admin/cli/upgrade.php</code></li>';
    echo '</ol>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Gather statistics
$stats = new stdClass();

// Total students
$stats->total_students = $DB->count_records('local_mzi_students');

// Active registrations
$stats->active_registrations = $DB->count_records('local_mzi_registrations', ['registration_status' => 'Active']);

// Total payments received
$sql = "SELECT SUM(payment_amount) as total FROM {local_mzi_payments} WHERE payment_status = 'Confirmed'";
$result = $DB->get_record_sql($sql);
$stats->total_payments = $result && $result->total ? number_format($result->total, 2) : '0.00';

// Pending student requests
$stats->pending_requests = $DB->count_records('local_mzi_requests', ['request_status' => 'Submitted']);

// Active classes
$stats->active_classes = $DB->count_records('local_mzi_classes', ['class_status' => 'Active']);

// Total enrollments
$stats->total_enrollments = $DB->count_records('local_mzi_enrollments', ['enrollment_status' => 'Active']);

// Recent grades (last 7 days)
$weekago = time() - (7 * 24 * 60 * 60);
$stats->recent_grades = $DB->count_records_select('local_mzi_grades', 'synced_at > ?', [$weekago]);

// Unacknowledged feedback
$stats->unacknowledged_feedback = $DB->count_records('local_mzi_grades', ['feedback_acknowledged' => 0]);

// Get pending requests for display
$pending_requests = $DB->get_records_sql("
    SELECT r.*, s.first_name, s.last_name, s.email
    FROM {local_mzi_requests} r
    JOIN {local_mzi_students} s ON s.id = r.student_id
    WHERE r.request_status = 'Submitted'
    ORDER BY r.created_at DESC
    LIMIT 10
");

// Get recent sync activities
$recent_syncs = $DB->get_records('local_mzi_sync_status', null, 'updated_at DESC', '*', 0, 5);

echo $OUTPUT->header();

// CSS for dashboard cards
?>
<style>
.dashboard-container {
    padding: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.green {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-card.blue {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card.orange {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.stat-card.red {
    background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
}

.stat-card.purple {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.teal {
    background: linear-gradient(135deg, #12c2e9 0%, #c471ed 100%);
}

.stat-card.pink {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    margin: 10px 0;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.section-title {
    font-size: 24px;
    font-weight: 600;
    margin: 30px 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 3px solid #667eea;
}

.requests-table {
    width: 100%;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.requests-table th {
    background: #667eea;
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
}

.requests-table td {
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
}

.requests-table tr:hover {
    background: #f5f5f5;
}

.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-warning {
    background: #fee140;
    color: #333;
}

.badge-success {
    background: #38ef7d;
    color: white;
}

.badge-danger {
    background: #ff6a00;
    color: white;
}

.action-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    margin-right: 5px;
    transition: all 0.3s;
}

.action-btn.approve {
    background: #38ef7d;
    color: white;
}

.action-btn.approve:hover {
    background: #2dd36f;
}

.action-btn.reject {
    background: #ff6a00;
    color: white;
}

.action-btn.reject:hover {
    background: #e55d00;
}

.sync-status {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 30px;
}

.sync-item {
    padding: 15px;
    border-left: 4px solid #667eea;
    margin-bottom: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.sync-item.success {
    border-left-color: #38ef7d;
}

.sync-item.failed {
    border-left-color: #ff6a00;
}
</style>

<div class="dashboard-container">
    
    <h2 style="color: #667eea; margin-bottom: 10px;">Student Dashboard Overview</h2>
    <p style="color: #666; margin-bottom: 30px;">Monitor and manage Student Dashboard data, requests, and synchronization</p>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card purple">
            <div class="stat-label">Total Students</div>
            <div class="stat-number"><?php echo $stats->total_students; ?></div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-label">Active Registrations</div>
            <div class="stat-number"><?php echo $stats->active_registrations; ?></div>
        </div>
        
        <div class="stat-card blue">
            <div class="stat-label">Total Payments</div>
            <div class="stat-number">$<?php echo $stats->total_payments; ?></div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-number"><?php echo $stats->pending_requests; ?></div>
        </div>
        
        <div class="stat-card teal">
            <div class="stat-label">Active Classes</div>
            <div class="stat-number"><?php echo $stats->active_classes; ?></div>
        </div>
        
        <div class="stat-card pink">
            <div class="stat-label">Active Enrollments</div>
            <div class="stat-number"><?php echo $stats->total_enrollments; ?></div>
        </div>
        
        <div class="stat-card red">
            <div class="stat-label">Grades (Last 7 Days)</div>
            <div class="stat-number"><?php echo $stats->recent_grades; ?></div>
        </div>
        
        <div class="stat-card orange">
            <div class="stat-label">Unacknowledged Feedback</div>
            <div class="stat-number"><?php echo $stats->unacknowledged_feedback; ?></div>
        </div>
    </div>

    <!-- Pending Student Requests -->
    <h3 class="section-title">⏳ Pending Student Requests</h3>
    
    <?php if (empty($pending_requests)): ?>
        <div style="padding: 30px; text-align: center; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <p style="color: #666; font-size: 16px;">✅ No pending requests at the moment.</p>
        </div>
    <?php else: ?>
        <table class="requests-table">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_requests as $request): ?>
                    <tr>
                        <td><strong><?php echo $request->request_number ?: '#' . $request->id; ?></strong></td>
                        <td>
                            <?php echo $request->first_name . ' ' . $request->last_name; ?><br>
                            <small style="color: #666;"><?php echo $request->email; ?></small>
                        </td>
                        <td><?php echo $request->request_type; ?></td>
                        <td>
                            <?php 
                            $priority_class = 'warning';
                            if ($request->priority === 'High' || $request->priority === 'Urgent') {
                                $priority_class = 'danger';
                            } else if ($request->priority === 'Low') {
                                $priority_class = 'success';
                            }
                            ?>
                            <span class="badge badge-<?php echo $priority_class; ?>"><?php echo $request->priority; ?></span>
                        </td>
                        <td><?php echo userdate($request->created_at, '%d %b %Y'); ?></td>
                        <td>
                            <a href="<?php echo $PAGE->url->out(false, ['action' => 'approve', 'requestid' => $request->id, 'sesskey' => sesskey()]); ?>" 
                               class="action-btn approve">Approve</a>
                            <a href="<?php echo $PAGE->url->out(false, ['action' => 'reject', 'requestid' => $request->id, 'sesskey' => sesskey()]); ?>" 
                               class="action-btn reject">Reject</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Recent Sync Activities -->
    <h3 class="section-title">🔄 Recent Sync Activities</h3>
    
    <div class="sync-status">
        <?php if (empty($recent_syncs)): ?>
            <p style="color: #666;">No sync activities recorded yet.</p>
        <?php else: ?>
            <?php foreach ($recent_syncs as $sync): ?>
                <div class="sync-item <?php echo $sync->sync_status === 'completed' ? 'success' : ($sync->sync_status === 'failed' ? 'failed' : ''); ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 16px;"><?php echo ucfirst($sync->module); ?></strong>
                            <p style="margin: 5px 0; color: #666;">
                                Status: <strong><?php echo ucfirst($sync->sync_status); ?></strong> | 
                                Records: <?php echo $sync->total_records; ?>
                            </p>
                            <?php if ($sync->error_message): ?>
                                <p style="color: #ff6a00; font-size: 13px; margin: 5px 0;">
                                    ⚠️ <?php echo $sync->error_message; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: right; color: #666;">
                            <small><?php echo userdate($sync->updated_at, '%d %b %Y %H:%M'); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <h3 class="section-title">⚡ Quick Actions</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/dashboard.php'); ?>" 
           style="padding: 15px; background: #667eea; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600;">
            📊 Main Dashboard
        </a>
        <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/sync_management.php'); ?>" 
           style="padding: 15px; background: #38ef7d; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600;">
            🔄 Sync Management
        </a>
        <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/statistics.php'); ?>" 
           style="padding: 15px; background: #4facfe; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600;">
            📈 Statistics
        </a>
        <a href="<?php echo new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'); ?>" 
           style="padding: 15px; background: #fa709a; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600;">
            📋 Event Logs
        </a>
    </div>

</div>

<?php
echo $OUTPUT->footer();
