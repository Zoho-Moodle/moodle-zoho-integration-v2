<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Grade Operations Monitor - Advanced monitoring for Hybrid Grading System
 * Real-time dashboard for Observer & Scheduled Task operations
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/navigation.php');

admin_externalpage_setup('local_moodle_zoho_sync_grade_queue');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$view = optional_param('view', 'dashboard', PARAM_ALPHA); // dashboard, observer, scheduled, failed
$status = optional_param('status', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$timerange = optional_param('timerange', '24h', PARAM_ALPHA); // 1h, 24h, 7d, 30d

$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/grade_queue_monitor.php'));
$PAGE->set_title('Grade Operations Monitor');
$PAGE->set_heading('Grade Operations Monitor');

// Handle actions
if ($action === 'retry' && $id && confirm_sesskey()) {
    $record = $DB->get_record('local_mzi_grade_queue', ['id' => $id], '*', MUST_EXIST);
    
    // Reset for retry
    $record->status = 'SYNCED';
    $record->error_message = null;
    $record->retry_count = 0;
    $record->timemodified = time();
    
    $DB->update_record('local_mzi_grade_queue', $record);
    
    redirect($PAGE->url, 'Record queued for retry', null, \core\output\notification::NOTIFY_SUCCESS);
} elseif ($action === 'export' && confirm_sesskey()) {
    // Export to CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="grade_operations_' . date('Y-m-d_His') . '.csv"');
    
    $records = $DB->get_records('local_mzi_grade_queue', null, 'timemodified DESC', '*', 0, 1000);
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Composite Key', 'Grade ID', 'Student ID', 'Status', 'Zoho ID', 'Created', 'Modified', 'Retries']);
    
    foreach ($records as $record) {
        fputcsv($output, [
            $record->id,
            $record->composite_key,
            $record->grade_id,
            $record->student_id,
            $record->status,
            $record->zoho_record_id ?? 'N/A',
            date('Y-m-d H:i:s', $record->timecreated),
            date('Y-m-d H:i:s', $record->timemodified),
            $record->retry_count
        ]);
    }
    
    fclose($output);
    exit;
}

// Calculate time range filter
$time_filter = time();
switch ($timerange) {
    case '1h':
        $time_filter -= 3600;
        break;
    case '24h':
        $time_filter -= 86400;
        break;
    case '7d':
        $time_filter -= 604800;
        break;
    case '30d':
        $time_filter -= 2592000;
        break;
}

echo $OUTPUT->header();

mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('grade_monitor', 'Grade Operations Monitor', 'Real-time monitoring for Observer & Scheduled Task operations');
mzi_render_breadcrumb('Grade Operations Monitor');
echo '<div class="mzi-content-wrapper">';

// ══════════════════════════════════════════════════════════
// ENHANCED STATISTICS - Real-time metrics
// ══════════════════════════════════════════════════════════

$stats = [
    // Total operations
    'total' => $DB->count_records('local_mzi_grade_queue'),
    'total_range' => $DB->count_records_select('local_mzi_grade_queue', "timemodified >= ?", [$time_filter]),
    
    // Observer operations (SYNCED status)
    'observer' => $DB->count_records('local_mzi_grade_queue', ['status' => 'SYNCED']),
    'observer_range' => $DB->count_records_select('local_mzi_grade_queue', "status = 'SYNCED' AND timemodified >= ?", [$time_filter]),
    
    // Scheduled Task operations
    'f_created' => $DB->count_records('local_mzi_grade_queue', ['status' => 'F_CREATED']),
    'f_created_range' => $DB->count_records_select('local_mzi_grade_queue', "status = 'F_CREATED' AND timemodified >= ?", [$time_filter]),
    
    'rr_created' => $DB->count_records('local_mzi_grade_queue', ['status' => 'RR_CREATED']),
    'rr_created_range' => $DB->count_records_select('local_mzi_grade_queue', "status = 'RR_CREATED' AND timemodified >= ?", [$time_filter]),
    
    // Failed operations
    'failed' => $DB->count_records_select('local_mzi_grade_queue', "error_message IS NOT NULL AND error_message != ''"),
    'failed_range' => $DB->count_records_select('local_mzi_grade_queue', "error_message IS NOT NULL AND error_message != '' AND timemodified >= ?", [$time_filter]),
    
    // Pending checks
    'needs_enrichment' => $DB->count_records('local_mzi_grade_queue', ['needs_enrichment' => 1]),
    'needs_rr_check' => $DB->count_records('local_mzi_grade_queue', ['needs_rr_check' => 1]),
];

// Calculate success rate
$stats['success_rate'] = $stats['total'] > 0 ? round(($stats['observer'] + $stats['f_created'] + $stats['rr_created']) / $stats['total'] * 100, 1) : 0;

// Get grade distribution
$grade_dist = $DB->get_records_sql("
    SELECT 
        SUBSTRING_INDEX(composite_key, '_', -1) as grade_type,
        COUNT(*) as count
    FROM {local_mzi_grade_queue}
    WHERE timemodified >= ?
    GROUP BY grade_type
", [$time_filter]);

?>

<style>
/* Dashboard Layout */
.grade-monitor-container {
    max-width: 100%;
    margin: 0;
    padding: 0 5px;
}

/* Navigation Tabs */
.monitor-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #dee2e6;
}
.monitor-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #6c757d;
    text-decoration: none;
    transition: all 0.3s;
}
.monitor-tab:hover {
    color: #007bff;
    background: #f8f9fa;
}
.monitor-tab.active {
    color: #007bff;
    border-bottom-color: #007bff;
}

/* Time Range Filter */
.time-filter {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    align-items: center;
}
.time-filter select {
    padding: 8px 16px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

/* Enhanced Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #007bff;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, transparent 50%, rgba(0,123,255,0.05) 50%);
    border-radius: 0 12px 0 0;
}

.stat-card.observer { border-left-color: #28a745; }
.stat-card.observer::before { background: linear-gradient(135deg, transparent 50%, rgba(40,167,69,0.05) 50%); }

.stat-card.scheduled { border-left-color: #fd7e14; }
.stat-card.scheduled::before { background: linear-gradient(135deg, transparent 50%, rgba(253,126,20,0.05) 50%); }

.stat-card.failed { border-left-color: #dc3545; }
.stat-card.failed::before { background: linear-gradient(135deg, transparent 50%, rgba(220,53,69,0.05) 50%); }

.stat-card.success { border-left-color: #17a2b8; }
.stat-card.success::before { background: linear-gradient(135deg, transparent 50%, rgba(23,162,184,0.05) 50%); }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.stat-card h3 {
    margin: 0;
    font-size: 13px;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.stat-icon {
    font-size: 24px;
    opacity: 0.6;
}

.stat-value {
    font-size: 36px;
    font-weight: bold;
    color: #212529;
    margin-bottom: 8px;
    position: relative;
    z-index: 1;
}

.stat-change {
    font-size: 12px;
    color: #6c757d;
}
.stat-change.positive { color: #28a745; }
.stat-change.negative { color: #dc3545; }

.stat-subtitle {
    font-size: 12px;
    color: #6c757d;
    margin-top: 8px;
}

/* Search & Filters */
.monitor-filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.filter-row {
    display: grid;
    grid-template-columns: 1fr 200px 200px auto;
    gap: 15px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
}

.filter-group input,
.filter-group select {
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #007bff;
    color: white;
}
.btn-primary:hover {
    background: #0056b3;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-secondary:hover {
    background: #545b62;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}
.btn-success:hover {
    background: #1e7e34;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Enhanced Table */
.operations-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 30px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.table-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #212529;
}

.table-actions {
    display: flex;
    gap: 10px;
}

.operations-table table {
    width: 100%;
    border-collapse: collapse;
}

.operations-table th {
    background: #f8f9fa;
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    color: #495057;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
}

.operations-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 13px;
    vertical-align: middle;
}

.operations-table tr:hover {
    background: #f8f9fa;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-SYNCED {
    background: #d4edda;
    color: #155724;
}
.status-SYNCED::before { background: #28a745; }

.status-F_CREATED {
    background: #e2e3e5;
    color: #383d41;
}
.status-F_CREATED::before { background: #6c757d; }

.status-RR_CREATED {
    background: #fff3cd;
    color: #856404;
}
.status-RR_CREATED::before { background: #ffc107; }

.status-error {
    background: #f8d7da;
    color: #721c24;
}
.status-error::before { background: #dc3545; }

/* Grade Badge */
.grade-badge {
    display: inline-block;
    width: 32px;
    height: 32px;
    line-height: 32px;
    text-align: center;
    border-radius: 50%;
    font-weight: 700;
    font-size: 13px;
}

.grade-F { background: #dc3545; color: white; }
.grade-R { background: #ffc107; color: #212529; }
.grade-RR { background: #fd7e14; color: white; }
.grade-P { background: #28a745; color: white; }
.grade-M { background: #17a2b8; color: white; }
.grade-D { background: #6f42c1; color: white; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 18px;
    margin-bottom: 8px;
    color: #495057;
}

.empty-state p {
    font-size: 14px;
    color: #6c757d;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .monitor-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    
    .mzi-nav-items {
        flex-direction: column;
    }
    
    .mzi-nav-link {
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .mzi-nav-item:last-child .mzi-nav-link {
        border-bottom: none;
    }
}
</style>

<div class="grade-monitor-container">
    
    <!-- Navigation Tabs -->
    <div class="monitor-tabs">
        <a href="?view=dashboard&timerange=<?php echo $timerange; ?>" 
           class="monitor-tab <?php echo $view === 'dashboard' ? 'active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="?view=observer&timerange=<?php echo $timerange; ?>" 
           class="monitor-tab <?php echo $view === 'observer' ? 'active' : ''; ?>">
            ⚡ Observer Operations
        </a>
        <a href="?view=scheduled&timerange=<?php echo $timerange; ?>" 
           class="monitor-tab <?php echo $view === 'scheduled' ? 'active' : ''; ?>">
            ⏰ Scheduled Tasks
        </a>
        <a href="?view=failed&timerange=<?php echo $timerange; ?>" 
           class="monitor-tab <?php echo $view === 'failed' ? 'active' : ''; ?>">
            ❌ Failed Operations
        </a>
    </div>
    
    <!-- Time Range Filter -->
    <div class="time-filter">
        <label><strong>Time Range:</strong></label>
        <select onchange="window.location.href='?view=<?php echo $view; ?>&timerange=' + this.value;">
            <option value="1h" <?php echo $timerange === '1h' ? 'selected' : ''; ?>>Last Hour</option>
            <option value="24h" <?php echo $timerange === '24h' ? 'selected' : ''; ?>>Last 24 Hours</option>
            <option value="7d" <?php echo $timerange === '7d' ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="30d" <?php echo $timerange === '30d' ? 'selected' : ''; ?>>Last 30 Days</option>
        </select>
        <a href="?action=export&sesskey=<?php echo sesskey(); ?>" class="btn btn-secondary btn-sm">
            📥 Export CSV
        </a>
        <button onclick="location.reload();" class="btn btn-primary btn-sm">
            🔄 Refresh
        </button>
    </div>    
    <!-- Statistics Dashboard -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <h3>Total Operations</h3>
                <span class="stat-icon">📊</span>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-subtitle">
                <?php echo number_format($stats['total_range']); ?> in selected range
            </div>
        </div>
        
        <div class="stat-card observer">
            <div class="stat-header">
                <h3>Observer Syncs</h3>
                <span class="stat-icon">⚡</span>
            </div>
            <div class="stat-value"><?php echo number_format($stats['observer']); ?></div>
            <div class="stat-subtitle">
                <?php echo number_format($stats['observer_range']); ?> in selected range
            </div>
        </div>
        
        <div class="stat-card scheduled">
            <div class="stat-header">
                <h3>F Grades Created</h3>
                <span class="stat-icon">📝</span>
            </div>
            <div class="stat-value"><?php echo number_format($stats['f_created']); ?></div>
            <div class="stat-subtitle">
                <?php echo number_format($stats['f_created_range']); ?> in selected range
            </div>
        </div>
        
        <div class="stat-card scheduled">
            <div class="stat-header">
                <h3>RR Grades Created</h3>
                <span class="stat-icon">🔄</span>
            </div>
            <div class="stat-value"><?php echo number_format($stats['rr_created']); ?></div>
            <div class="stat-subtitle">
                <?php echo number_format($stats['rr_created_range']); ?> in selected range
            </div>
        </div>
        
        <div class="stat-card failed">
            <div class="stat-header">
                <h3>Failed Operations</h3>
                <span class="stat-icon">❌</span>
            </div>
            <div class="stat-value"><?php echo number_format($stats['failed']); ?></div>
            <div class="stat-subtitle">
                <?php echo number_format($stats['failed_range']); ?> in selected range
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-header">
                <h3>Success Rate</h3>
                <span class="stat-icon">✅</span>
            </div>
            <div class="stat-value"><?php echo $stats['success_rate']; ?>%</div>
            <div class="stat-subtitle">
                Overall system performance
            </div>
        </div>
    </div>

<?php

// ══════════════════════════════════════════════════════════
// VIEW-SPECIFIC CONTENT
// ══════════════════════════════════════════════════════════

if ($view === 'dashboard') {
    // Dashboard view - show recent operations from all sources
    ?>
    <div class="operations-table">
        <div class="table-header">
            <h2>Recent Operations (All Sources)</h2>
            <div class="table-actions">
                <span style="color: #6c757d; font-size: 14px;">
                    Showing last 50 operations
                </span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Student</th>
                    <th>Assignment</th>
                    <th>Composite Key</th>
                    <th>Zoho ID</th>
                    <th>Modified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $records = $DB->get_records_select('local_mzi_grade_queue', 
                    "timemodified >= ?", 
                    [$time_filter], 
                    'timemodified DESC', 
                    '*', 
                    0, 
                    50
                );
                
                if (empty($records)) {
                    echo '<tr><td colspan="8" class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <h3>No operations found</h3>
                            <p>No grade operations in the selected time range</p>
                          </td></tr>';
                } else {
                    foreach ($records as $record) {
                        $student = $DB->get_record('user', ['id' => $record->student_id]);
                        $assignment = $DB->get_record('assign', ['id' => $record->assignment_id]);
                        
                        $status_class = !empty($record->error_message) ? 'status-error' : 'status-' . $record->status;
                        ?>
                        <tr>
                            <td><strong><?php echo $record->id; ?></strong></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $record->status; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $student ? fullname($student) : 'Unknown'; ?>
                                <br><small style="color: #6c757d;"><?php echo $student ? $student->email : ''; ?></small>
                            </td>
                            <td>
                                <small><?php echo $assignment ? $assignment->name : 'Unknown'; ?></small>
                            </td>
                            <td><code style="font-size: 11px;"><?php echo s($record->composite_key); ?></code></td>
                            <td>
                                <?php if ($record->zoho_record_id): ?>
                                    <a href="https://crm.zoho.com/crm/org20084326443/tab/CustomModule11/<?php echo $record->zoho_record_id; ?>" 
                                       target="_blank" style="color: #007bff; font-size: 11px;">
                                        <?php echo substr($record->zoho_record_id, 0, 12); ?>...
                                    </a>
                                <?php else: ?>
                                    <span style="color: #6c757d;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo userdate($record->timemodified, '%d %b %H:%M'); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($record->error_message)): ?>
                                    <a href="?action=retry&id=<?php echo $record->id; ?>&sesskey=<?php echo sesskey(); ?>" 
                                       class="btn btn-success btn-sm">
                                        🔄 Retry
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($record->error_message)): ?>
                            <tr>
                                <td colspan="8" style="background: #fff3cd; padding: 12px; font-size: 12px;">
                                    <strong>❌ Error:</strong> <?php echo s($record->error_message); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
    
} elseif ($view === 'observer') {
    // Observer operations view
    ?>
    <div class="operations-table">
        <div class="table-header">
            <h2>Observer Operations (Real-time Syncs)</h2>
            <div class="table-actions">
                <span style="color: #6c757d; font-size: 14px;">
                    Showing operations from Observer (status=SYNCED)
                </span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Assignment</th>
                    <th>Grade</th>
                    <th>Composite Key</th>
                    <th>Zoho ID</th>
                    <th>Sync Time</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $records = $DB->get_records_select('local_mzi_grade_queue', 
                    "status = 'SYNCED' AND timemodified >= ?", 
                    [$time_filter], 
                    'timemodified DESC', 
                    '*', 
                    0, 
                    50
                );
                
                if (empty($records)) {
                    echo '<tr><td colspan="8" class="empty-state">
                            <div class="empty-state-icon">⚡</div>
                            <h3>No observer operations found</h3>
                            <p>No real-time syncs in the selected time range</p>
                          </td></tr>';
                } else {
                    foreach ($records as $record) {
                        $student = $DB->get_record('user', ['id' => $record->student_id]);
                        $assignment = $DB->get_record('assign', ['id' => $record->assignment_id]);
                        $grade = $DB->get_record('assign_grades', ['id' => $record->grade_id]);
                        
                        // Calculate duration
                        $duration = $record->timemodified - $record->timecreated;
                        ?>
                        <tr>
                            <td><strong><?php echo $record->id; ?></strong></td>
                            <td>
                                <?php echo $student ? fullname($student) : 'Unknown'; ?>
                                <br><small style="color: #6c757d;"><?php echo $student ? $student->email : ''; ?></small>
                            </td>
                            <td>
                                <small><?php echo $assignment ? $assignment->name : 'Unknown'; ?></small>
                            </td>
                            <td>
                                <?php if ($grade): ?>
                                    <span class="grade-badge grade-<?php echo $grade->grade < 2 ? 'r' : ($grade->grade == 0 ? 'f' : 'p'); ?>">
                                        <?php 
                                        if ($grade->grade == 0) {
                                            echo 'F';
                                        } elseif ($grade->grade < 2) {
                                            echo 'R';
                                        } elseif ($grade->grade < 3) {
                                            echo 'P';
                                        } elseif ($grade->grade < 4) {
                                            echo 'M';
                                        } else {
                                            echo 'D';
                                        }
                                        ?>
                                    </span>
                                    <small style="color: #6c757d;">(<?php echo number_format($grade->grade, 1); ?>)</small>
                                <?php else: ?>
                                    <span style="color: #6c757d;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-size: 11px;"><?php echo s($record->composite_key); ?></code></td>
                            <td>
                                <?php if ($record->zoho_record_id): ?>
                                    <a href="https://crm.zoho.com/crm/org20084326443/tab/CustomModule11/<?php echo $record->zoho_record_id; ?>" 
                                       target="_blank" style="color: #007bff; font-size: 11px;">
                                        <?php echo substr($record->zoho_record_id, 0, 12); ?>...
                                    </a>
                                <?php else: ?>
                                    <span style="color: #6c757d;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo userdate($record->timemodified, '%d %b %H:%M:%S'); ?></small>
                            </td>
                            <td>
                                <small style="color: #28a745;"><?php echo number_format($duration * 1000, 0); ?>ms</small>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
    
} elseif ($view === 'scheduled') {
    // Scheduled task operations view
    ?>
    <div class="operations-table">
        <div class="table-header">
            <h2>Scheduled Task Operations (F & RR Grades)</h2>
            <div class="table-actions">
                <span style="color: #6c757d; font-size: 14px;">
                    Showing F_CREATED and RR_CREATED operations
                </span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Student</th>
                    <th>Assignment</th>
                    <th>Grade</th>
                    <th>Composite Key</th>
                    <th>Zoho ID</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $records = $DB->get_records_select('local_mzi_grade_queue', 
                    "(status = 'F_CREATED' OR status = 'RR_CREATED') AND timemodified >= ?", 
                    [$time_filter], 
                    'timemodified DESC', 
                    '*', 
                    0, 
                    50
                );
                
                if (empty($records)) {
                    echo '<tr><td colspan="8" class="empty-state">
                            <div class="empty-state-icon">📝</div>
                            <h3>No scheduled task operations found</h3>
                            <p>No F or RR grades created in the selected time range</p>
                          </td></tr>';
                } else {
                    foreach ($records as $record) {
                        $student = $DB->get_record('user', ['id' => $record->student_id]);
                        $assignment = $DB->get_record('assign', ['id' => $record->assignment_id]);
                        $grade = $DB->get_record('assign_grades', ['id' => $record->grade_id]);
                        
                        $status_class = $record->status === 'F_CREATED' ? 'status-F_CREATED' : 'status-RR_CREATED';
                        ?>
                        <tr>
                            <td><strong><?php echo $record->id; ?></strong></td>
                            <td>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $record->status; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $student ? fullname($student) : 'Unknown'; ?>
                                <br><small style="color: #6c757d;"><?php echo $student ? $student->email : ''; ?></small>
                            </td>
                            <td>
                                <small><?php echo $assignment ? $assignment->name : 'Unknown'; ?></small>
                            </td>
                            <td>
                                <?php if ($grade): ?>
                                    <span class="grade-badge grade-<?php echo $grade->grade == 0 ? 'f' : 'r'; ?>">
                                        <?php 
                                        if ($record->status === 'F_CREATED') {
                                            echo 'F';
                                        } else {
                                            echo 'RR';
                                        }
                                        ?>
                                    </span>
                                    <small style="color: #6c757d;">(<?php echo number_format($grade->grade, 1); ?>)</small>
                                <?php else: ?>
                                    <span style="color: #6c757d;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-size: 11px;"><?php echo s($record->composite_key); ?></code></td>
                            <td>
                                <?php if ($record->zoho_record_id): ?>
                                    <a href="https://crm.zoho.com/crm/org20084326443/tab/CustomModule11/<?php echo $record->zoho_record_id; ?>" 
                                       target="_blank" style="color: #007bff; font-size: 11px;">
                                        <?php echo substr($record->zoho_record_id, 0, 12); ?>...
                                    </a>
                                <?php else: ?>
                                    <span style="color: #6c757d;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo userdate($record->timemodified, '%d %b %H:%M'); ?></small>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
    
} elseif ($view === 'failed') {
    // Failed operations view
    ?>
    <div class="operations-table">
        <div class="table-header">
            <h2>Failed Operations</h2>
            <div class="table-actions">
                <span style="color: #6c757d; font-size: 14px;">
                    Showing operations with errors
                </span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Student</th>
                    <th>Assignment</th>
                    <th>Composite Key</th>
                    <th>Error</th>
                    <th>Failed At</th>
                    <th>Retries</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $records = $DB->get_records_select('local_mzi_grade_queue', 
                    "error_message IS NOT NULL AND error_message != '' AND timemodified >= ?", 
                    [$time_filter], 
                    'timemodified DESC', 
                    '*', 
                    0, 
                    50
                );
                
                if (empty($records)) {
                    echo '<tr><td colspan="9" class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <h3>No failed operations</h3>
                            <p>All operations completed successfully!</p>
                          </td></tr>';
                } else {
                    foreach ($records as $record) {
                        $student = $DB->get_record('user', ['id' => $record->student_id]);
                        $assignment = $DB->get_record('assign', ['id' => $record->assignment_id]);
                        ?>
                        <tr>
                            <td><strong><?php echo $record->id; ?></strong></td>
                            <td>
                                <span class="status-badge status-error">
                                    <?php echo $record->status; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $student ? fullname($student) : 'Unknown'; ?>
                                <br><small style="color: #6c757d;"><?php echo $student ? $student->email : ''; ?></small>
                            </td>
                            <td>
                                <small><?php echo $assignment ? $assignment->name : 'Unknown'; ?></small>
                            </td>
                            <td><code style="font-size: 11px;"><?php echo s($record->composite_key); ?></code></td>
                            <td>
                                <small style="color: #dc3545; max-width: 300px; display: inline-block;">
                                    <?php echo s(substr($record->error_message, 0, 100)); ?>
                                    <?php if (strlen($record->error_message) > 100): ?>
                                        <a href="#" onclick="alert('<?php echo addslashes($record->error_message); ?>'); return false;" 
                                           style="color: #007bff;">...more</a>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <small><?php echo userdate($record->timemodified, '%d %b %H:%M'); ?></small>
                            </td>
                            <td>
                                <small><?php echo $record->retry_count; ?> / 5</small>
                            </td>
                            <td>
                                <a href="?action=retry&id=<?php echo $record->id; ?>&sesskey=<?php echo sesskey(); ?>" 
                                   class="btn btn-success btn-sm"
                                   onclick="return confirm('Retry this operation?');">
                                    🔄 Retry
                                </a>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

echo '</div>'; // Close grade-monitor-container
echo '</div>'; // Close mzi-content-wrapper
echo '</div>'; // Close mzi-page-container
echo $OUTPUT->footer();
