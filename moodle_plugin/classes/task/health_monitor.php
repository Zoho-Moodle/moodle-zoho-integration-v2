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
        mtrace('Running health check...');

        // Check 1: Backend connection.
        mtrace('Checking Backend API connection...');
        $connectiontest = config_manager::test_connection();
        
        if ($connectiontest['success']) {
            mtrace('✓ Backend API is reachable.');
        } else {
            mtrace('✗ Backend API connection failed: ' . $connectiontest['message']);
        }

        // Check 2: Event statistics (last 24 hours).
        mtrace('Checking event statistics (last 24 hours)...');
        $since = time() - 86400;
        $stats = event_logger::get_statistics($since);

        mtrace("  Total events: {$stats['total']}");
        mtrace("  Sent: {$stats['sent']}");
        mtrace("  Failed: {$stats['failed']}");
        mtrace("  Pending: {$stats['pending']}");
        mtrace("  Success rate: {$stats['success_rate']}%");

        // Check 3: Failed events requiring attention.
        $maxretries = config_manager::get_max_retry_attempts();
        $failedevents = event_logger::get_failed_events($maxretries);
        
        if (!empty($failedevents)) {
            mtrace("⚠ Warning: " . count($failedevents) . " events have failed and need retry.");
        } else {
            mtrace('✓ No failed events requiring attention.');
        }

        // Check 4: Success rate threshold.
        if ($stats['total'] > 10 && $stats['success_rate'] < 90) {
            mtrace('⚠ Warning: Success rate is below 90%!');
        } elseif ($stats['total'] > 10) {
            mtrace('✓ Success rate is healthy.');
        }

        mtrace('Health check complete.');
    }
}
