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
 * Scheduled task to cleanup old event logs.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync\task;

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\config_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task class for cleaning up old logs.
 */
class cleanup_old_logs extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_cleanup_old_logs', 'local_moodle_zoho_sync');
    }

    /**
     * Execute task.
     */
    public function execute() {
        mtrace('Starting cleanup of old event logs...');

        $retentiondays = config_manager::get_log_retention_days();
        
        mtrace("Retention period: {$retentiondays} days");

        $deletedcount = event_logger::cleanup_old_logs($retentiondays);

        if ($deletedcount > 0) {
            mtrace("✓ Deleted {$deletedcount} old event log records.");
        } else {
            mtrace('No old logs to delete.');
        }
    }
}
