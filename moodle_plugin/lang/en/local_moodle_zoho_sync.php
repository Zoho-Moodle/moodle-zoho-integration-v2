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
 * English language strings for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'Moodle-Zoho Integration';

// Capabilities - Must match capability names in db/access.php
$string['moodle_zoho_sync:manage'] = 'Manage Moodle-Zoho Integration settings';
$string['moodle_zoho_sync:viewdashboard'] = 'View student dashboard';
$string['moodle_zoho_sync:viewlogs'] = 'View event logs';
$string['moodle_zoho_sync:triggersync'] = 'Trigger manual sync operations';
$string['moodle_zoho_sync:viewsynchistory'] = 'View sync history';

// Settings page.
$string['backend_settings'] = 'Backend API Configuration';
$string['backend_settings_desc'] = 'Configure connection to the Backend API server.';
$string['backend_url'] = 'Backend API URL';
$string['backend_url_desc'] = 'Full URL of the Backend API server (e.g., http://localhost:8001 or https://api.yourdomain.com)';
$string['api_token'] = 'API Token';
$string['api_token_desc'] = 'Authentication token for Backend API (leave empty if not required)';
$string['ssl_verify'] = 'Verify SSL Certificate';
$string['ssl_verify_desc'] = 'Enable SSL certificate verification (disable only for development)';

$string['sync_settings'] = 'Sync Configuration';
$string['sync_settings_desc'] = 'Enable or disable specific sync modules.';
$string['enable_user_sync'] = 'Enable User Sync';
$string['enable_user_sync_desc'] = 'Automatically sync user created/updated events to Backend';
$string['enable_enrollment_sync'] = 'Enable Enrollment Sync';
$string['enable_enrollment_sync_desc'] = 'Automatically sync enrollment events to Backend';
$string['enable_grade_sync'] = 'Enable Grade Sync';
$string['enable_grade_sync_desc'] = 'Automatically sync grade updated events to Backend';

$string['retry_settings'] = 'Retry Configuration';
$string['retry_settings_desc'] = 'Configure automatic retry behavior for failed webhooks.';
$string['max_retry_attempts'] = 'Max Retry Attempts';
$string['max_retry_attempts_desc'] = 'Maximum number of times to retry a failed webhook (default: 3)';
$string['retry_delay'] = 'Retry Delay (seconds)';
$string['retry_delay_desc'] = 'Delay between retry attempts in seconds (default: 5)';

$string['advanced_settings'] = 'Advanced Settings';
$string['advanced_settings_desc'] = 'Advanced configuration options.';
$string['enable_debug'] = 'Enable Debug Logging';
$string['enable_debug_desc'] = 'Enable detailed debug logging (use only for troubleshooting)';
$string['log_retention_days'] = 'Log Retention (days)';
$string['log_retention_days_desc'] = 'Number of days to keep event logs before automatic cleanup (default: 30)';
$string['connection_timeout'] = 'Connection Timeout (seconds)';
$string['connection_timeout_desc'] = 'HTTP connection timeout in seconds (default: 10)';

// Navigation.
$string['mystudentarea'] = 'My Student Area';
$string['studentprofile'] = 'Profile';
$string['myprograms'] = 'My Programs';
$string['myclasses'] = 'My Classes';
$string['mygrades'] = 'My Grades';
$string['myrequests'] = 'My Requests';
$string['studentcard'] = 'Student Card';
$string['sync_management'] = 'Zoho Sync Management';
$string['event_logs'] = 'Event Logs';
$string['statistics'] = 'Statistics';
$string['health_check'] = 'Health Check';
$string['cleanup_old_logs'] = 'Cleanup Old Logs';
$string['cleanup_success'] = 'Successfully deleted {$a} old event log records';
$string['confirm_cleanup'] = 'Are you sure you want to delete all event logs older than {$a} days? This action cannot be undone.';
$string['scheduled_task_logs'] = 'Scheduled Task Logs';

// Scheduled tasks.
$string['task_retry_failed_webhooks'] = 'Retry failed webhooks';
$string['task_cleanup_old_logs'] = 'Cleanup old event logs';
$string['task_health_monitor'] = 'Monitor system health';
$string['task_sync_missing_grades'] = 'Hybrid Grading: Enrich grades, detect RR, create F grades';

// Hybrid Grading System
$string['gradequeue_monitor'] = 'Grade Queue Monitor';
$string['gradequeue_pending'] = 'Pending Enrichment';
$string['gradequeue_enriched'] = 'Enriched';
$string['gradequeue_failed'] = 'Failed';
$string['gradequeue_total'] = 'Total Queued';
$string['gradequeue_status'] = 'Status';
$string['gradequeue_needs_enrichment'] = 'Needs Enrichment';
$string['gradequeue_needs_rr_check'] = 'Needs RR Check';
$string['gradequeue_retry'] = 'Retry Failed';
$string['gradequeue_composite_key'] = 'Composite Key';
$string['gradequeue_zoho_record_id'] = 'Zoho Record ID';
$string['gradequeue_error_message'] = 'Error Message';
$string['gradequeue_basic_sent'] = 'Basic Sent';
$string['gradequeue_enrichment_failed'] = 'Enrichment Failed';
$string['gradequeue_f_created'] = 'F Grade Created';
$string['gradequeue_rr_detected'] = 'RR Detected';
$string['gradequeue_workflow_state'] = 'Workflow State';
$string['gradequeue_invalid_submission'] = 'Invalid Submission (01122)';

// Connection messages.
$string['connection_success'] = 'Backend API connection successful';
$string['connection_failed'] = 'Backend API connection failed';
$string['connection_error'] = 'Backend API connection error';

// Dashboard strings.
$string['dashboard_title'] = 'Student Dashboard';
$string['dashboard_welcome'] = 'Welcome, {$a}';
$string['dashboard_subtitle'] = 'Here\'s a snapshot of your learning journey';
$string['quick_summary'] = 'Your information is synced with our system';
$string['quick_summary_help'] = 'All your data is up-to-date and available across tabs below';
$string['profile_tab'] = 'Profile';
$string['academics_tab'] = 'Academics';
$string['finance_tab'] = 'Finance';
$string['classes_tab'] = 'Classes';
$string['grades_tab'] = 'Grades';
$string['requests_tab'] = 'Requests';

$string['loading'] = 'Loading...';
$string['no_data'] = 'No data available';
$string['error_loading_data'] = 'Error loading data. Please try again later.';
$string['retry'] = 'Retry';

// Profile.
$string['student_info'] = 'Student Information';
$string['contact_info'] = 'Contact Information';
$string['emergency_contact'] = 'Emergency Contact';
$string['program_info'] = 'Program Information';

// Academics.
$string['current_programs'] = 'Current Programs';
$string['program_name'] = 'Program Name';
$string['program_status'] = 'Status';
$string['start_date'] = 'Start Date';
$string['expected_completion'] = 'Expected Completion';
$string['units_enrolled'] = 'Units Enrolled';
$string['units_completed'] = 'Units Completed';

// Finance.
$string['payment_summary'] = 'Payment Summary';
$string['total_fees'] = 'Total Fees';
$string['amount_paid'] = 'Amount Paid';
$string['balance_due'] = 'Balance Due';
$string['recent_payments'] = 'Recent Payments';
$string['payment_date'] = 'Payment Date';
$string['amount'] = 'Amount';
$string['payment_method'] = 'Method';
$string['payment_status'] = 'Status';
$string['receipt'] = 'Receipt';

// Classes.
$string['my_classes'] = 'My Classes';
$string['class_name'] = 'Class Name';
$string['instructor'] = 'Instructor';
$string['schedule'] = 'Schedule';
$string['room'] = 'Room';
$string['attendance'] = 'Attendance';

// Grades.
$string['my_grades'] = 'My Grades';
$string['unit'] = 'Unit';
$string['grade'] = 'Grade';
$string['submission_date'] = 'Submission Date';
$string['feedback'] = 'Feedback';

// Admin panel.
$string['admin_panel_title'] = 'Zoho Integration Admin Panel';
$string['settings_tab'] = 'Settings';
$string['sync_tab'] = 'Sync Management';
$string['logs_tab'] = 'Event Logs';
$string['diagnostics_tab'] = 'Diagnostics';

$string['test_connection'] = 'Test Connection';
$string['connection_status'] = 'Connection Status';
$string['last_sync'] = 'Last Sync';
$string['sync_now'] = 'Sync Now';

// Event log.
$string['event_id'] = 'Event ID';
$string['event_type'] = 'Event Type';
$string['event_status'] = 'Status';
$string['retry_count'] = 'Retries';
$string['timecreated'] = 'Created';
$string['timeprocessed'] = 'Processed';
$string['view_details'] = 'View Details';

// Sync operations.
$string['sync_users'] = 'Sync Users';
$string['sync_enrollments'] = 'Sync Enrollments';
$string['sync_grades'] = 'Sync Grades';
$string['sync_all'] = 'Sync All';
$string['sync_in_progress'] = 'Sync in progress...';
$string['sync_complete'] = 'Sync complete';
$string['sync_failed'] = 'Sync failed';

// Statistics.
$string['statistics'] = 'Statistics';
$string['total_events'] = 'Total Events';
$string['events_sent'] = 'Sent';
$string['events_failed'] = 'Failed';
$string['events_pending'] = 'Pending';
$string['success_rate'] = 'Success Rate';

// Error messages.
$string['error_invalid_config'] = 'Invalid configuration. Please check settings.';
$string['error_connection_failed'] = 'Connection to Backend API failed.';
$string['error_unauthorized'] = 'Unauthorized. Please check API token.';
$string['error_server_error'] = 'Server error. Please try again later.';
$string['error_unknown'] = 'Unknown error occurred.';

// Privacy.
$string['privacy:metadata:local_mzi_event_log'] = 'Stores webhook events sent to Backend API';
$string['privacy:metadata:local_mzi_event_log:event_data'] = 'JSON data containing user information';
$string['privacy:metadata:local_mzi_event_log:event_type'] = 'Type of event (user_created, enrollment_created, etc.)';
$string['privacy:metadata:local_mzi_event_log:timecreated'] = 'Timestamp when event was created';
$string['privacy:metadata:backend_api'] = 'User data is sent to Backend API for Zoho CRM synchronization';
$string['privacy:metadata:backend_api:userid'] = 'User ID from Moodle';
$string['privacy:metadata:backend_api:username'] = 'Username';
$string['privacy:metadata:backend_api:email'] = 'User email address';
$string['privacy:metadata:backend_api:fullname'] = 'User full name';

// Admin Dashboard strings.
$string['admin_dashboard'] = 'Admin Dashboard';
$string['admin_dashboard_welcome'] = 'Moodle-Zoho Sync Dashboard';
$string['admin_dashboard_subtitle'] = 'Monitor sync health and manage webhooks';
$string['kpi_total_events'] = 'Total Events';
$string['kpi_sent_events'] = 'Sent';
$string['kpi_failed_events'] = 'Failed';
$string['kpi_pending_events'] = 'Pending';
$string['success_rate'] = 'Success Rate';
$string['success_excellent'] = 'Excellent performance';
$string['success_good'] = 'Good performance';
$string['success_needs_attention'] = 'Needs attention';
$string['backend_status'] = 'Backend Connection';
$string['status_online'] = 'Online';
$string['status_offline'] = 'Offline';
$string['backend_healthy'] = 'Backend is healthy and responding';
$string['backend_unreachable'] = 'Cannot reach backend server';
$string['test_connection'] = 'Test';
$string['quick_actions'] = 'Quick Actions';
$string['retry_failed_events'] = 'Retry {$a} Failed Events';
$string['no_failed_events'] = 'No Failed Events';
$string['view_event_logs'] = 'View Event Logs';
$string['view_statistics'] = 'View Statistics';
$string['plugin_settings'] = 'Plugin Settings';
$string['processing'] = 'Processing...';
$string['confirm_retry_all'] = 'Are you sure you want to retry all failed events?';
$string['retry_initiated'] = 'Retry process has been initiated. Check Event Logs for progress.';
$string['retry_failed'] = 'Failed to initiate retry';
$string['error_occurred'] = 'An error occurred. Please try again.';
$string['testing'] = 'Testing...';

// Event Logs Enhanced strings.
$string['filters'] = 'Filters';
$string['event_type'] = 'Event Type';
$string['all'] = 'All';
$string['user_created'] = 'User Created';
$string['user_updated'] = 'User Updated';
$string['enrollment_created'] = 'Enrollment Created';
$string['grade_updated'] = 'Grade Updated';
$string['status'] = 'Status';
$string['from'] = 'From';
$string['to'] = 'To';
$string['apply'] = 'Apply';
$string['clear'] = 'Clear';
$string['total_results'] = 'Total Results';
$string['showing'] = 'Showing';
$string['no_events_found'] = 'No events found matching your criteria';
$string['details'] = 'Details';
$string['event_details'] = 'Event Details';
$string['event_id'] = 'Event ID';
$string['retry_count'] = 'Retry Count';
$string['created'] = 'Created';
$string['processed'] = 'Processed';
$string['next_retry'] = 'Next Retry';
$string['error_details'] = 'Error Details';
$string['copy_event_id'] = 'Copy Event ID';

// Settings Help Tooltips.
$string['max_retry_help'] = 'Maximum number of retry attempts before marking an event as permanently failed';
$string['log_retention_help'] = 'Number of days to keep successfully sent events in the database';

// Student Dashboard Management strings.
$string['student_dashboard_management'] = 'Student Dashboard Management';
$string['request_approved'] = 'Request has been approved successfully';
$string['request_rejected'] = 'Request has been rejected';

