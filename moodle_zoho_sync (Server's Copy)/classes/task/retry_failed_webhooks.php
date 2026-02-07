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
 * Scheduled task to retry failed webhooks.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync\task;

use local_moodle_zoho_sync\event_logger;
use local_moodle_zoho_sync\webhook_sender;
use local_moodle_zoho_sync\config_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task class for retrying failed webhooks.
 */
class retry_failed_webhooks extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_retry_failed_webhooks', 'local_moodle_zoho_sync');
    }

    /**
     * Execute task.
     */
    public function execute() {
        mtrace('Starting retry of failed webhooks...');

        $maxretries = config_manager::get_max_retry_attempts();
        $failedevents = event_logger::get_failed_events($maxretries);

        if (empty($failedevents)) {
            mtrace('No failed events to retry.');
            return;
        }

        mtrace('Found ' . count($failedevents) . ' failed events to retry.');

        $sender = new webhook_sender();
        $retried = 0;
        $success = 0;

        foreach ($failedevents as $event) {
            try {
                mtrace("Retrying event {$event->event_id} (type: {$event->event_type}, attempt: {$event->retry_count})...");

                // Decode event data.
                $eventdata = json_decode($event->event_data, true);

                // Mark as retrying.
                event_logger::update_event_status($event->event_id, 'retrying');

                // Retry sending.
                $result = $sender->send_event_internal(
                    $event->event_type, 
                    $eventdata, 
                    $event->event_id,
                    $event->moodle_event_id
                );

                if ($result['success']) {
                    $success++;
                    mtrace("✓ Successfully retried event {$event->event_id}");
                } else {
                    mtrace("✗ Failed to retry event {$event->event_id}: {$result['error']}");
                }

                $retried++;

                // Small delay to avoid overwhelming the API.
                usleep(100000); // 0.1 seconds

            } catch (\Exception $e) {
                mtrace("✗ Exception retrying event {$event->event_id}: " . $e->getMessage());
                event_logger::update_event_status($event->event_id, 'failed', null, $e->getMessage());
            }
        }

        mtrace("Retry complete: {$success}/{$retried} events successfully resent.");
    }
}
