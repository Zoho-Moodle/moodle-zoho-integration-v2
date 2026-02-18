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
 * Scheduled task to monitor system health.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync\task;

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\config_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task class for monitoring health.
 */
class health_monitor extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_health_monitor', 'local_moodle_zoho_sync');
    }

    /**
     * Execute task.
     */
    public function execute() {
        mtrace('Running detailed health check...');

        $services = [
            'backend_api' => $this->check_backend_api(),
            'user_sync' => $this->check_service_health('user_created', 'user_updated'),
            'course_sync' => $this->check_service_health('course_created', 'course_updated'),
            'enrollment_sync' => $this->check_service_health('enrollment_created', 'enrollment_deleted'),
            'grade_sync' => $this->check_service_health('grade_created', 'grade_updated'),
            'learning_outcomes' => $this->check_learning_outcomes_health(),
        ];

        // Store results in config for dashboard display
        foreach ($services as $service => $status) {
            config_manager::set_config("health_status_{$service}", json_encode($status));
            config_manager::set_config("health_last_check_{$service}", time());
        }

        // Display summary
        mtrace('=== Health Check Summary ===');
        foreach ($services as $service => $status) {
            $icon = $status['status'] === 'ok' ? '✓' : ($status['status'] === 'warning' ? '⚠' : '✗');
            mtrace("$icon " . ucwords(str_replace('_', ' ', $service)) . ": {$status['status']} - {$status['message']}");
        }

        mtrace('Health check complete.');
    }

    /**
     * Check Backend API connectivity.
     *
     * @return array Status details
     */
    private function check_backend_api() {
        $result = config_manager::test_connection();
        
        if ($result['success']) {
            return [
                'status' => 'ok',
                'message' => 'Backend API is reachable',
                'last_success' => time(),
                'details' => $result
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Backend API connection failed: ' . $result['message'],
                'last_failure' => time(),
                'details' => $result
            ];
        }
    }

    /**
     * Check health of a specific service based on event types.
     *
     * @param string ...$eventtypes Event types to check
     * @return array Status details
     */
    private function check_service_health(...$eventtypes) {
        global $DB;
        
        $since = time() - 86400; // Last 24 hours
        $total = 0;
        $sent = 0;
        $failed = 0;
        
        foreach ($eventtypes as $eventtype) {
            $total += $DB->count_records_select('local_mzi_event_log', 
                "event_type = ? AND timecreated >= ?", [$eventtype, $since]);
            $sent += $DB->count_records_select('local_mzi_event_log', 
                "event_type = ? AND status = 'sent' AND timecreated >= ?", [$eventtype, $since]);
            $failed += $DB->count_records_select('local_mzi_event_log', 
                "event_type = ? AND status = 'failed' AND timecreated >= ?", [$eventtype, $since]);
        }
        
        $success_rate = $total > 0 ? round(($sent / $total) * 100, 2) : 100;
        
        if ($total === 0) {
            return [
                'status' => 'ok',
                'message' => 'No events in last 24 hours',
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'success_rate' => 100
            ];
        }
        
        if ($success_rate >= 95) {
            $status = 'ok';
            $message = "Success rate: {$success_rate}%";
        } elseif ($success_rate >= 80) {
            $status = 'warning';
            $message = "Success rate below 95%: {$success_rate}%";
        } else {
            $status = 'error';
            $message = "Success rate critically low: {$success_rate}%";
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'success_rate' => $success_rate,
            'event_types' => $eventtypes
        ];
    }

    /**
     * Check Learning Outcomes sync health.
     *
     * @return array Status details
     */
    private function check_learning_outcomes_health() {
        global $DB;
        
        $since = time() - 86400; // Last 24 hours
        
        // Count grades with learning outcomes
        $sql = "SELECT COUNT(*) 
                FROM {local_mzi_event_log} 
                WHERE event_type IN ('grade_created', 'grade_updated') 
                AND timecreated >= ? 
                AND event_data LIKE '%learning_outcomes%'";
        
        $total_with_lo = $DB->count_records_sql($sql, [$since]);
        
        // Count successfully sent
        $sql_sent = "SELECT COUNT(*) 
                     FROM {local_mzi_event_log} 
                     WHERE event_type IN ('grade_created', 'grade_updated') 
                     AND timecreated >= ? 
                     AND status = 'sent'
                     AND event_data LIKE '%learning_outcomes%'";
        
        $sent_with_lo = $DB->count_records_sql($sql_sent, [$since]);
        
        if ($total_with_lo === 0) {
            return [
                'status' => 'ok',
                'message' => 'No LO grades in last 24 hours',
                'total' => 0,
                'sent' => 0,
                'success_rate' => 100
            ];
        }
        
        $success_rate = round(($sent_with_lo / $total_with_lo) * 100, 2);
        
        if ($success_rate >= 95) {
            $status = 'ok';
            $message = "LO sync healthy: {$success_rate}%";
        } elseif ($success_rate >= 80) {
            $status = 'warning';
            $message = "LO sync below 95%: {$success_rate}%";
        } else {
            $status = 'error';
            $message = "LO sync critically low: {$success_rate}%";
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'total' => $total_with_lo,
            'sent' => $sent_with_lo,
            'success_rate' => $success_rate
        ];
    }
}
