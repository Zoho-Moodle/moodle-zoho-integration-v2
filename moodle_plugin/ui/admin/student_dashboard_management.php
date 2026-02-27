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

// (approve/reject actions are handled in Zoho CRM — status is mirrored back via webhook)
$action = optional_param('action', '', PARAM_ALPHA);
if ($action && confirm_sesskey()) {
    if ($action === 'save_windows') {
        // Save request window settings
        $all_rw_types = [
            'Enroll Next Semester',
            'Class Drop',
        ];
        foreach ($all_rw_types as $rw_type) {
            $key       = str_replace([' ', '/'], '_', strtolower($rw_type));
            $enabled   = optional_param('rw_enabled_'  . $key, 0, PARAM_INT);
            $start_raw = optional_param('rw_start_'    . $key, '', PARAM_TEXT);
            $end_raw   = optional_param('rw_end_'      . $key, '', PARAM_TEXT);
            $msg       = optional_param('rw_message_'  . $key, '', PARAM_TEXT);

            $start_ts = !empty($start_raw) ? strtotime($start_raw) : null;
            $end_ts   = !empty($end_raw)   ? strtotime($end_raw)   : null;

            $existing = $DB->get_record('local_mzi_request_windows', ['request_type' => $rw_type]);
            $obj = new stdClass();
            $obj->request_type = $rw_type;
            $obj->enabled      = ($enabled ? 1 : 0);
            $obj->start_date   = $start_ts ?: null;
            $obj->end_date     = $end_ts   ?: null;
            $obj->message      = $msg;
            $obj->updated_by   = $USER->id;
            $obj->updated_at   = time();

            if ($existing) {
                $obj->id = $existing->id;
                $DB->update_record('local_mzi_request_windows', $obj);
            } else {
                $DB->insert_record('local_mzi_request_windows', $obj);
            }
        }
        redirect($PAGE->url . '#rw-section',
            'Request windows saved successfully.', null, \core\output\notification::NOTIFY_SUCCESS);
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
    ORDER BY r.created_at DESC
    LIMIT 20
");

// Get recent sync activities
$recent_syncs = $DB->get_records('local_mzi_sync_status', null, 'updated_at DESC', '*', 0, 5);

// ── Student search ────────────────────────────────────────────────────────
$search_q       = optional_param('search_student', '', PARAM_TEXT);
$found_student  = null;
$found_regs     = [];
$found_requests = [];
$found_enr_count = 0;
if (strlen(trim($search_q)) >= 2) {
    $sq = '%' . $DB->sql_like_escape(trim($search_q)) . '%';
    $found_student = $DB->get_record_sql(
        "SELECT * FROM {local_mzi_students}
          WHERE " . $DB->sql_like('first_name', '?', false) . "
             OR " . $DB->sql_like('last_name',  '?', false) . "
             OR " . $DB->sql_like('email',      '?', false) . "
             OR " . $DB->sql_like('student_id', '?', false) . "
             OR  moodle_user_id = ?
          LIMIT 1",
        [$sq, $sq, $sq, $sq, is_numeric(trim($search_q)) ? (int)trim($search_q) : -1]
    );
    if ($found_student) {
        $found_regs     = $DB->get_records('local_mzi_registrations', ['student_id' => $found_student->id]);
        $found_requests = $DB->get_records_sql(
            "SELECT * FROM {local_mzi_requests} WHERE student_id = ? ORDER BY created_at DESC LIMIT 10",
            [$found_student->id]
        );
        $found_enr_count = $DB->count_records('local_mzi_enrollments', ['student_id' => $found_student->id]);
    }
}

// ── Request window settings ───────────────────────────────────────────────
$rw_all_types = [
    'Enroll Next Semester',
    'Class Drop',
];
$rw_rows = [];
foreach ($rw_all_types as $rwt) {
    $r = $DB->get_record('local_mzi_request_windows', ['request_type' => $rwt]);
    $rw_rows[$rwt] = $r ?: (object)[
        'request_type' => $rwt, 'enabled' => 1,
        'start_date'   => null, 'end_date' => null, 'message' => '',
    ];
}

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

    <!-- Student Requests -->
    <h3 class="section-title">📋 Student Requests</h3>
    
    <?php if (empty($pending_requests)): ?>
        <div style="padding: 30px; text-align: center; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <p style="color: #666; font-size: 16px;">No requests submitted yet.</p>
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
                    <th>Status</th>
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
                            <?php
                            $st = strtolower($request->request_status ?? '');
                            $st_cls = match($st) {
                                'approved'     => 'success',
                                'rejected'     => 'danger',
                                'under review',
                                'under-review' => 'warning',
                                'submitted'    => 'primary',
                                default        => 'secondary',
                            };
                            ?>
                            <span class="badge badge-<?php echo $st_cls; ?>">
                                <?php echo s($request->request_status ?? 'Submitted'); ?>
                            </span>
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

    <!-- Manual Student Sync -->
    <h3 class="section-title">🔍 Manual Student Sync from Zoho</h3>

    <?php
    // Try to read backend URL from plugin settings
    $backend_url = get_config('local_moodle_zoho_sync', 'backend_url') ?: 'http://localhost:8001';
    $backend_url = rtrim($backend_url, '/');
    ?>

    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 24px; margin-bottom: 30px;">
        <p style="color: #555; margin-bottom: 18px; font-size: 14px;">
            Enter a Zoho CRM Student Record ID to fetch and sync that student (and optionally their registrations, payments, enrollments, grades, and requests) immediately.
        </p>

        <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 14px;">
            <div style="flex: 1; min-width: 260px;">
                <label for="syncStudentId" style="display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px; text-transform:uppercase; letter-spacing:.4px;">
                    Zoho Student Record ID
                </label>
                <input type="text" id="syncStudentId" placeholder="e.g. 5975843000001234567"
                       style="width:100%; padding:10px 14px; border:2px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px; text-transform:uppercase; letter-spacing:.4px;">
                    Options
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; padding:10px 0; cursor:pointer;">
                    <input type="checkbox" id="syncIncludeRelated" checked style="width:16px;height:16px;">
                    Include related records (registrations, payments, grades...)
                </label>
            </div>
            <div>
                <button onclick="triggerStudentSync()" id="syncBtn"
                        style="padding:10px 22px; background:#0066cc; color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; white-space:nowrap;">
                    🔄 Sync Student
                </button>
            </div>
        </div>

        <div id="syncResultBox" style="display:none; margin-top:16px; border-radius:6px; overflow:hidden;"></div>
    </div>

    <script>
    async function triggerStudentSync() {
        var zohoId = document.getElementById('syncStudentId').value.trim();
        if (!zohoId) {
            alert('Please enter a Zoho Student Record ID.');
            return;
        }
        var includeRelated = document.getElementById('syncIncludeRelated').checked;
        var btn = document.getElementById('syncBtn');
        var box = document.getElementById('syncResultBox');

        btn.disabled = true;
        btn.textContent = '⏳ Syncing...';
        box.style.display = 'none';
        box.innerHTML = '';

        try {
            var resp = await fetch('<?php echo $backend_url; ?>/api/v1/admin/sync-student', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ zoho_student_id: zohoId, include_related: includeRelated })
            });
            var data = await resp.json();

            var isSuccess = data.success === true;
            var bg = isSuccess ? '#d4edda' : '#f8d7da';
            var border = isSuccess ? '#c3e6cb' : '#f5c6cb';
            var icon = isSuccess ? '✅' : '❌';

            var html = '<div style="padding:16px; background:' + bg + '; border:1px solid ' + border + ';">';
            html += '<strong>' + icon + ' ' + (data.message || data.error || 'Unknown response') + '</strong>';

            if (data.results && Object.keys(data.results).length > 0) {
                html += '<table style="margin-top:12px; width:100%; border-collapse:collapse; font-size:13px;">';
                html += '<thead><tr style="background:rgba(0,0,0,0.05)">';
                html += '<th style="padding:6px 10px; text-align:left">Module</th>';
                html += '<th style="padding:6px 10px; text-align:center">Total</th>';
                html += '<th style="padding:6px 10px; text-align:center">Synced</th>';
                html += '<th style="padding:6px 10px; text-align:center">Skipped</th>';
                html += '<th style="padding:6px 10px; text-align:center">Errors</th>';
                html += '</tr></thead><tbody>';

                for (var mod in data.results) {
                    var r = data.results[mod];
                    if (typeof r === 'object' && r !== null) {
                        html += '<tr style="border-top:1px solid rgba(0,0,0,0.1)">';
                        html += '<td style="padding:6px 10px; font-weight:600; text-transform:capitalize">' + mod + '</td>';
                        html += '<td style="padding:6px 10px; text-align:center">' + (r.total !== undefined ? r.total : '—') + '</td>';
                        html += '<td style="padding:6px 10px; text-align:center; color:#155724">' + (r.synced !== undefined ? r.synced : '—') + '</td>';
                        html += '<td style="padding:6px 10px; text-align:center; color:#856404">' + (r.skipped !== undefined ? r.skipped : '—') + '</td>';
                        html += '<td style="padding:6px 10px; text-align:center; color:#721c24">' + (r.errors !== undefined ? r.errors : (r.status === 'error' ? r.detail : '—')) + '</td>';
                        html += '</tr>';
                    } else {
                        html += '<tr style="border-top:1px solid rgba(0,0,0,0.1)">';
                        html += '<td style="padding:6px 10px; font-weight:600; text-transform:capitalize" colspan="5">' + mod + ': ' + r + '</td>';
                        html += '</tr>';
                    }
                }
                html += '</tbody></table>';
            }
            html += '</div>';

            box.innerHTML = html;
            box.style.display = 'block';
        } catch (err) {
            box.innerHTML = '<div style="padding:16px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:4px;">❌ <strong>Request failed:</strong> ' + err.message + '<br><small>Is the backend server running?</small></div>';
            box.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = '🔄 Sync Student';
        }
    }

    // Allow pressing Enter in the input field
    document.addEventListener('DOMContentLoaded', function() {
        var input = document.getElementById('syncStudentId');
        if (input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') triggerStudentSync();
            });
        }
    });
    </script>

</div>

    <!-- ══════════════════════════════════════════════════════════════
         STUDENT SEARCH
    ══════════════════════════════════════════════════════════════ -->
    <h3 class="section-title">🔍 Student Lookup</h3>
    <div style="background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1);padding:24px;margin-bottom:30px">
        <form method="get" action="" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
            <div style="flex:1;min-width:260px">
                <label for="search_student" style="display:block;font-size:12px;font-weight:600;color:#555;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">
                    Search by Name / Email / Student ID / Moodle User ID
                </label>
                <input type="text" id="search_student" name="search_student"
                       value="<?php echo s($search_q); ?>"
                       placeholder="e.g. John Smith / john@example.com / STU-001"
                       style="width:100%;padding:10px 14px;border:2px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box">
            </div>
            <button type="submit"
                    style="padding:10px 22px;background:#0066cc;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap">
                🔎 Search
            </button>
            <?php if ($search_q): ?>
                <a href="?" style="padding:10px 16px;background:#e9ecef;color:#333;border-radius:6px;text-decoration:none;font-size:14px">
                    ✕ Clear
                </a>
            <?php endif; ?>
        </form>

        <?php if ($search_q && !$found_student): ?>
            <div style="padding:18px;background:#fff3cd;border-radius:6px;color:#856404">
                No student found matching "<strong><?php echo s($search_q); ?></strong>".
            </div>
        <?php elseif ($found_student): ?>
            <?php
            $st_cls_map = ['active'=>'#d4edda:#155724','graduated'=>'#cce5ff:#004085','suspended'=>'#f8d7da:#721c24'];
            $st_raw     = strtolower($found_student->status ?? '');
            [$sbg,$sfg] = explode(':', $st_cls_map[$st_raw] ?? '#e9ecef:#495057');
            ?>
            <!-- Profile quick-view -->
            <div style="border:2px solid #0066cc;border-radius:10px;overflow:hidden">
                <div style="background:linear-gradient(135deg,#0066cc,#00b4d8);color:#fff;padding:14px 20px;display:flex;align-items:center;gap:14px">
                    <?php if (!empty($found_student->photo_url)): ?>
                        <img src="<?php echo s($found_student->photo_url); ?>" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.5)" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div>
                        <div style="font-size:17px;font-weight:700"><?php echo s($found_student->first_name . ' ' . $found_student->last_name); ?></div>
                        <div style="font-size:12px;opacity:.85"><?php echo s($found_student->student_id ?? ''); ?> &nbsp;·&nbsp; <?php echo s($found_student->email ?? ''); ?></div>
                    </div>
                    <span style="margin-left:auto;background:<?php echo $sbg; ?>;color:<?php echo $sfg; ?>;padding:4px 12px;border-radius:10px;font-size:11px;font-weight:700;text-transform:uppercase">
                        <?php echo s($found_student->status ?? 'Unknown'); ?>
                    </span>
                </div>
                <div style="padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;background:#fafbff">
                    <?php
                    $fields = [
                        'Academic Email'  => $found_student->academic_email ?? '',
                        'Phone'           => $found_student->phone_number   ?? '',
                        'Nationality'     => $found_student->nationality     ?? '',
                        'Program'         => $found_student->academic_program ?? '',
                        'Study Language'  => $found_student->study_language   ?? '',
                        'Moodle User ID'  => $found_student->moodle_user_id   ?? '',
                    ];
                    foreach ($fields as $lbl => $val): if (empty($val)) continue; ?>
                        <div>
                            <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.4px;font-weight:600"><?php echo $lbl; ?></div>
                            <div style="font-size:13px;color:#333;margin-top:2px"><?php echo s($val); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.4px;font-weight:600">Enrollments</div>
                        <div style="font-size:13px;color:#333;margin-top:2px"><?php echo (int)$found_enr_count; ?> class(es)</div>
                    </div>
                    <div>
                        <div style="font-size:10px;color:#888;text-transform:uppercase;letter-spacing:.4px;font-weight:600">Registrations</div>
                        <div style="font-size:13px;color:#333;margin-top:2px"><?php echo count($found_regs); ?> program(s)</div>
                    </div>
                </div>
                <?php if (!empty($found_regs)): ?>
                <div style="padding:12px 20px;border-top:1px solid #eee">
                    <div style="font-size:12px;font-weight:700;color:#444;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Registrations</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                    <?php foreach ($found_regs as $reg): ?>
                        <div style="background:#f0f4ff;border:1px solid #c8d8ff;border-radius:6px;padding:6px 12px;font-size:12px">
                            <strong><?php echo s($reg->program_name); ?></strong>
                            <?php if (!empty($reg->registration_status)): ?>
                                <span style="margin-left:6px;background:#e9ecef;padding:1px 7px;border-radius:8px;font-size:10px"><?php echo s($reg->registration_status); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($reg->total_fees)): ?>
                                <span style="margin-left:6px;color:#0066cc">Fees: <?php echo number_format((float)$reg->total_fees); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($found_requests)): ?>
                <div style="padding:12px 20px;border-top:1px solid #eee">
                    <div style="font-size:12px;font-weight:700;color:#444;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Recent Requests</div>
                    <table style="width:100%;border-collapse:collapse;font-size:12px">
                        <thead><tr style="background:#f8f9fa">
                            <th style="padding:6px 10px;text-align:left;color:#555">Type</th>
                            <th style="padding:6px 10px;text-align:left;color:#555">Status</th>
                            <th style="padding:6px 10px;text-align:left;color:#555">Date</th>
                            <th style="padding:6px 10px;text-align:left;color:#555">Admin Response</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($found_requests as $fr): ?>
                            <tr style="border-top:1px solid #f0f0f0">
                                <td style="padding:6px 10px"><?php echo s($fr->request_type); ?></td>
                                <td style="padding:6px 10px"><span class="badge badge-<?php echo strtolower($fr->request_status ?? '') === 'approved' ? 'success' : (strtolower($fr->request_status ?? '') === 'rejected' ? 'danger' : 'warning'); ?>"><?php echo s($fr->request_status ?? ''); ?></span></td>
                                <td style="padding:6px 10px"><?php echo !empty($fr->created_at) ? userdate($fr->created_at, '%d %b %Y') : '—'; ?></td>
                                <td style="padding:6px 10px;color:#555"><?php echo s($fr->admin_response ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         REQUEST WINDOWS MANAGEMENT
    ══════════════════════════════════════════════════════════════ -->
    <h3 class="section-title" id="rw-section">🗓 Request Windows</h3>
    <div style="background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1);padding:24px;margin-bottom:30px">
        <p style="color:#555;font-size:13px;margin-bottom:18px">
            Control which request types are available to students, and optionally set an open/close date window.
            Leave the start/end date blank to keep the window always open (while enabled).
        </p>
        <form method="post" action="">
            <input type="hidden" name="action"  value="save_windows">
            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="background:#f8f9fa">
                    <th style="padding:10px 12px;text-align:left;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e0e0e0">Request Type</th>
                    <th style="padding:10px 12px;text-align:center;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e0e0e0;width:80px">Enabled</th>
                    <th style="padding:10px 12px;text-align:left;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e0e0e0">Window Open (date)</th>
                    <th style="padding:10px 12px;text-align:left;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e0e0e0">Window Close (date)</th>
                    <th style="padding:10px 12px;text-align:left;color:#555;font-size:11px;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #e0e0e0">Message shown when closed</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rw_rows as $rwtype => $rw):
                    $rw_key    = str_replace([' ', '/'], '_', strtolower($rwtype));
                    $start_val = (!empty($rw->start_date)) ? date('Y-m-d', (int)$rw->start_date) : '';
                    $end_val   = (!empty($rw->end_date))   ? date('Y-m-d', (int)$rw->end_date)   : '';
                    $is_open   = (bool)(int)$rw->enabled;
                    $now_ts    = time();
                    $in_window = $is_open
                        && (!$rw->start_date || $now_ts >= (int)$rw->start_date)
                        && (!$rw->end_date   || $now_ts <= (int)$rw->end_date);
                ?>
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:12px">
                        <strong><?php echo s($rwtype); ?></strong>
                        <span style="margin-left:8px;font-size:11px;font-weight:700;padding:2px 8px;border-radius:8px;
                              background:<?php echo $in_window ? '#d4edda' : '#f8d7da'; ?>;
                              color:<?php echo $in_window ? '#155724' : '#721c24'; ?>">
                            <?php echo $in_window ? 'OPEN' : 'CLOSED'; ?>
                        </span>
                    </td>
                    <td style="padding:12px;text-align:center">
                        <input type="checkbox" name="rw_enabled_<?php echo $rw_key; ?>" value="1"
                               <?php echo $is_open ? 'checked' : ''; ?>
                               style="width:18px;height:18px;cursor:pointer">
                    </td>
                    <td style="padding:12px">
                        <input type="date" name="rw_start_<?php echo $rw_key; ?>" value="<?php echo s($start_val); ?>"
                               style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px">
                    </td>
                    <td style="padding:12px">
                        <input type="date" name="rw_end_<?php echo $rw_key; ?>" value="<?php echo s($end_val); ?>"
                               style="padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px">
                    </td>
                    <td style="padding:12px">
                        <input type="text" name="rw_message_<?php echo $rw_key; ?>"
                               value="<?php echo s($rw->message ?? ''); ?>"
                               placeholder="Optional: reason shown to student when window is closed"
                               style="width:100%;padding:6px 10px;border:1px solid #ccc;border-radius:6px;font-size:13px;box-sizing:border-box">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:16px;text-align:right">
                <button type="submit" style="padding:10px 28px;background:#0066cc;color:#fff;border:none;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer">
                    💾 Save Request Windows
                </button>
            </div>
        </form>
    </div>

<?php
echo $OUTPUT->footer();
