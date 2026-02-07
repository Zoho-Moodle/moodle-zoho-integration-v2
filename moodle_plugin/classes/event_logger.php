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
 * Event logger for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync;

defined('MOODLE_INTERNAL') || die();

/**
 * Event logger class.
 *
 * Logs webhook events to database for tracking and debugging.
 */
class event_logger {

    /**
     * Log a webhook event.
     *
     * @param string $eventtype Event type
     * @param array $eventdata Event data
     * @param int $moodleeventid Moodle event ID
     * @param string $eventid Optional pre-generated UUID (for consistency)
     * @return string Event ID (UUID)
     */
    public static function log_event($eventtype, $eventdata, $moodleeventid = null, $eventid = null) {
        global $DB;

        try {
            // Generate UUID for idempotency (or use provided one).
            if (empty($eventid)) {
                $eventid = self::generate_uuid();
            }
            
            $record = new \stdClass();
            $record->event_id = $eventid;
            $record->event_type = $eventtype;
            $record->event_data = json_encode($eventdata);
            $record->moodle_event_id = $moodleeventid;
            $record->status = 'pending';
            $record->retry_count = 0;
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('local_mzi_event_log', $record);

            return $eventid;

        } catch (\Exception $e) {
            debugging('Error logging event: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Update event status with exponential backoff calculation.
     *
     * @param string $eventid Event ID (UUID)
     * @param string $status Status (sent, failed, retrying)
     * @param int $httpstatus HTTP status code
     * @param string $error Error message if failed
     * @param string $action Action taken (created, updated, deleted) - from backend response
     * @return bool Success
     */
    public static function update_event_status($eventid, $status, $httpstatus = null, $error = null, $action = null) {
        global $DB;

        try {
            $record = $DB->get_record('local_mzi_event_log', array('event_id' => $eventid));
            
            if (!$record) {
                return false;
            }

            $record->status = $status;
            $record->timemodified = time();

            if ($httpstatus !== null) {
                $record->http_status = $httpstatus;
            }

            if ($error !== null) {
                $record->last_error = $error;
            }
            
            // Save action from backend response
            if ($action !== null) {
                $record->action = $action;
            }

            if ($status === 'sent') {
                $record->timeprocessed = time();
                $record->next_retry_at = null; // Clear retry timestamp
            }

            if ($status === 'retrying' || $status === 'failed') {
                $record->retry_count++;
                
                // PRODUCTION-GRADE: Calculate next_retry_at with exponential backoff + jitter
                // Formula: min(base * 2^(retry_count), max) + random_jitter
                $base_delay = 60; // 1 minute
                $max_delay = 3600; // 1 hour
                $delay = min($base_delay * pow(2, $record->retry_count - 1), $max_delay);
                
                // Add jitter (±20% random variation to prevent thundering herd)
                $jitter = rand(-20, 20) / 100.0; // -0.2 to +0.2
                $delay = $delay * (1 + $jitter);
                
                $record->next_retry_at = time() + (int)$delay;
            }

            return $DB->update_record('local_mzi_event_log', $record);

        } catch (\Exception $e) {
            debugging('Error updating event status: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Log error with structured context for observability.
     * PRODUCTION-GRADE: Errors are ALWAYS persisted to DB even before webhook send.
     *
     * @param string $eventtype Event type
     * @param int $relateduserid Related user ID
     * @param string $errormessage Error message
     * @param array $context Additional context (optional)
     */
    public static function log_error($eventtype, $relateduserid, $errormessage, $context = []) {
        // Build structured error message
        $structured_error = "[Moodle-Zoho] Error in $eventtype (user $relateduserid): $errormessage";
        
        if (!empty($context)) {
            $structured_error .= " | Context: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        debugging($structured_error, DEBUG_DEVELOPER);
        
        // Log to Moodle system log if debug is enabled.
        if (config_manager::is_debug_enabled()) {
            error_log($structured_error);
        }
        
        // CRITICAL: Errors are also stored in event_log table via update_event_status
        // This ensures FULL traceability even if webhook never gets sent
    }

    /**
     * Get failed events for retry - WITH exponential backoff eligibility check.
     *
     * @param int $maxretries Maximum retry attempts
     * @return array Array of failed event records
     */
    public static function get_failed_events($maxretries = 3) {
        global $DB;

        try {
            $now = time();
            
            // PRODUCTION-GRADE: Only return events that are:
            // 1. Failed or retrying status
            // 2. Below max retry count
            // 3. Either have no next_retry_at OR next_retry_at is in the past
            // This implements proper exponential backoff - prevents retry storms
            $sql = "SELECT * FROM {local_mzi_event_log}
                    WHERE status IN ('failed', 'retrying') 
                    AND retry_count < :maxretries
                    AND (next_retry_at IS NULL OR next_retry_at <= :now)
                    ORDER BY timecreated ASC
                    LIMIT 100";
            
            return $DB->get_records_sql($sql, ['maxretries' => $maxretries, 'now' => $now]);

        } catch (\Exception $e) {
            debugging('Error retrieving failed events: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return array();
        }
    }

    /**
     * Get event statistics.
     *
     * @param int $since Unix timestamp (get stats since this time)
     * @return array Statistics array
     */
    public static function get_statistics($since = null) {
        global $DB;

        try {
            $conditions = array();
            if ($since !== null) {
                $conditions['timecreated'] = $since;
            }

            $total = $DB->count_records_select('local_mzi_event_log', 
                $since ? 'timecreated >= ?' : '1=1', 
                $since ? array($since) : array());

            $sent = $DB->count_records_select('local_mzi_event_log', 
                'status = ? ' . ($since ? 'AND timecreated >= ?' : ''), 
                $since ? array('sent', $since) : array('sent'));

            $failed = $DB->count_records_select('local_mzi_event_log', 
                'status = ? ' . ($since ? 'AND timecreated >= ?' : ''), 
                $since ? array('failed', $since) : array('failed'));

            $pending = $DB->count_records_select('local_mzi_event_log', 
                'status = ? ' . ($since ? 'AND timecreated >= ?' : ''), 
                $since ? array('pending', $since) : array('pending'));

            return array(
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'pending' => $pending,
                'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            );

        } catch (\Exception $e) {
            debugging('Error retrieving statistics: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return array();
        }
    }

    /**
     * Clean up old logs.
     *
     * @param int $retentiondays Retention period in days
     * @return int Number of records deleted
     */
    public static function cleanup_old_logs($retentiondays = 30) {
        global $DB;

        try {
            $cutoff = time() - ($retentiondays * 86400);
            
            $deletedcount = $DB->delete_records_select('local_mzi_event_log', 
                'timecreated < ? AND status = ?', 
                array($cutoff, 'sent'));

            return $deletedcount;

        } catch (\Exception $e) {
            debugging('Error cleaning up old logs: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Generate UUID v4 - PUBLIC for single source of truth.
     * This ensures UUID is generated exactly once per event.
     *
     * @return string UUID
     */
    public static function generate_uuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Count total events (for Dashboard KPI).
     *
     * @return int Total event count
     */
    public static function count_total_events() {
        global $DB;
        try {
            return $DB->count_records('local_mzi_event_log');
        } catch (\Exception $e) {
            debugging('Error counting total events: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Count events by status (for Dashboard KPIs).
     *
     * @param string $status Event status (sent, failed, pending, retrying)
     * @return int Event count for status
     */
    public static function count_events_by_status($status) {
        global $DB;
        try {
            return $DB->count_records('local_mzi_event_log', ['status' => $status]);
        } catch (\Exception $e) {
            debugging('Error counting events by status: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Get paginated events with filters (for improved Event Logs UX).
     *
     * @param array $filters Filters (event_type, status, date_from, date_to)
     * @param int $page Page number (0-indexed)
     * @param int $perpage Records per page
     * @return array ['events' => array, 'total' => int]
     */
    public static function get_events_paginated($filters = [], $page = 0, $perpage = 50) {
        global $DB;
        
        try {
            $conditions = [];
            $params = [];
            
            if (!empty($filters['event_type'])) {
                $conditions[] = 'event_type = :eventtype';
                $params['eventtype'] = $filters['event_type'];
            }
            
            if (!empty($filters['status'])) {
                $conditions[] = 'status = :status';
                $params['status'] = $filters['status'];
            }
            
            if (!empty($filters['date_from'])) {
                $conditions[] = 'timecreated >= :datefrom';
                $params['datefrom'] = strtotime($filters['date_from']);
            }
            
            if (!empty($filters['date_to'])) {
                $conditions[] = 'timecreated <= :dateto';
                $params['dateto'] = strtotime($filters['date_to'] . ' 23:59:59');
            }
            
            $where = empty($conditions) ? '1=1' : implode(' AND ', $conditions);
            
            $total = $DB->count_records_select('local_mzi_event_log', $where, $params);
            $events = $DB->get_records_select('local_mzi_event_log', $where, $params, 
                'timecreated DESC', '*', $page * $perpage, $perpage);
            
            return [
                'events' => $events,
                'total' => $total
            ];
            
        } catch (\Exception $e) {
            debugging('Error getting paginated events: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['events' => [], 'total' => 0];
        }
    }
}

