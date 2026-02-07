<?php
/**
 * Event Observer for Moodle-Zoho Integration
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/data_extractor.php');
require_once(__DIR__ . '/webhook_sender.php');

class observer {
    
    /**
     * Handle user created event
     * 
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        // Check if user sync is enabled
        if (!get_config('local_moodle_zoho_sync', 'enable_user_sync')) {
            return;
        }

        try {
            // Extract user data
            $extractor = new data_extractor();
            $user_data = $extractor->extract_user_data($event->objectid);

            if (!$user_data) {
                self::log_error('Failed to extract user data for user ID: ' . $event->objectid);
                return;
            }

            // Send webhook
            $sender = new webhook_sender();
            $response = $sender->send_user_created($user_data);

            self::log_debug('User created webhook sent', [
                'user_id' => $event->objectid,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            self::log_error('Error in user_created observer: ' . $e->getMessage());
        }
    }

    /**
     * Handle user updated event
     * 
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event) {
        // Check if user sync is enabled
        if (!get_config('local_moodle_zoho_sync', 'enable_user_sync')) {
            return;
        }

        try {
            // Extract user data
            $extractor = new data_extractor();
            $user_data = $extractor->extract_user_data($event->objectid);

            if (!$user_data) {
                self::log_error('Failed to extract user data for user ID: ' . $event->objectid);
                return;
            }

            // Send webhook
            $sender = new webhook_sender();
            $response = $sender->send_user_updated($user_data);

            self::log_debug('User updated webhook sent', [
                'user_id' => $event->objectid,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            self::log_error('Error in user_updated observer: ' . $e->getMessage());
        }
    }

    /**
     * Handle enrollment created event
     * 
     * @param \core\event\user_enrolment_created $event
     */
    public static function enrollment_created(\core\event\user_enrolment_created $event) {
        // FORCE LOG - ALWAYS fires to verify observer is called
        error_log('=== ENROLLMENT CREATED OBSERVER FIRED === Enrolment ID: ' . $event->objectid);
        
        // Check if enrollment sync is enabled
        $enrollment_sync_enabled = get_config('local_moodle_zoho_sync', 'enable_enrollment_sync');
        $backend_url = get_config('local_moodle_zoho_sync', 'backend_url');
        
        error_log('=== ENROLLMENT CONFIG === enable_enrollment_sync: ' . ($enrollment_sync_enabled ? 'YES' : 'NO') . ', backend_url: ' . $backend_url);
        
        if (!$enrollment_sync_enabled) {
            error_log('=== ENROLLMENT SYNC DISABLED === Skipping webhook');
            return;
        }

        try {
            // Extract enrollment data
            $extractor = new data_extractor();
            $enrollment_data = $extractor->extract_enrollment_data($event->objectid);

            if (!$enrollment_data) {
                self::log_error('Failed to extract enrollment data for enrollment ID: ' . $event->objectid);
                error_log('=== ENROLLMENT EXTRACTION FAILED === Enrolment ID: ' . $event->objectid);
                return;
            }

            error_log('=== ENROLLMENT DATA EXTRACTED === ' . json_encode($enrollment_data));

            // Send webhook
            $sender = new webhook_sender();
            $response = $sender->send_enrollment_created($enrollment_data);

            error_log('=== WEBHOOK RESPONSE === ' . json_encode($response));

            self::log_debug('Enrollment created webhook sent', [
                'enrollment_id' => $event->objectid,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            error_log('=== ENROLLMENT CREATED ERROR === ' . $e->getMessage());
            self::log_error('Error in enrollment_created observer: ' . $e->getMessage());
        }
    }

    /**
     * Handle enrollment deleted event (unenrolment)
     * 
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function enrollment_deleted(\core\event\user_enrolment_deleted $event) {
        // FORCE LOG - ALWAYS fires to verify observer is called
        error_log('=== ENROLLMENT DELETED OBSERVER FIRED === Enrolment ID: ' . $event->objectid);
        
        // Check if enrollment sync is enabled
        $enrollment_sync_enabled = get_config('local_moodle_zoho_sync', 'enable_enrollment_sync');
        $backend_url = get_config('local_moodle_zoho_sync', 'backend_url');
        
        error_log('=== ENROLLMENT DELETE CONFIG === enable_enrollment_sync: ' . ($enrollment_sync_enabled ? 'YES' : 'NO') . ', backend_url: ' . $backend_url);
        
        if (!$enrollment_sync_enabled) {
            error_log('=== ENROLLMENT SYNC DISABLED === Skipping webhook');
            return;
        }

        try {
            // Extract enrollment data
            $extractor = new data_extractor();
            $enrollment_data = $extractor->extract_enrollment_data($event->objectid);

            if (!$enrollment_data) {
                self::log_error('Failed to extract enrollment data for deleted enrollment ID: ' . $event->objectid);
                error_log('=== ENROLLMENT EXTRACTION FAILED === Enrolment ID: ' . $event->objectid);
                return;
            }

            error_log('=== ENROLLMENT DATA EXTRACTED === ' . json_encode($enrollment_data));

            // Send webhook
            $sender = new webhook_sender();
            $response = $sender->send_enrollment_deleted($enrollment_data);

            error_log('=== WEBHOOK RESPONSE === ' . json_encode($response));

            self::log_debug('Enrollment deleted webhook sent', [
                'enrollment_id' => $event->objectid,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            error_log('=== ENROLLMENT DELETED ERROR === ' . $e->getMessage());
            self::log_error('Error in enrollment_deleted observer: ' . $e->getMessage());
        }
    }

    /**
     * Handle grade updated event
     * 
     * @param \core\event\user_graded $event
     */
    public static function grade_updated(\core\event\user_graded $event) {
        // FORCE LOG - ALWAYS fires to verify observer is called
        error_log('=== GRADE OBSERVER FIRED === Event: user_graded, ID: ' . $event->objectid);
        
        // Check if grade sync is enabled
        $grade_sync_enabled = get_config('local_moodle_zoho_sync', 'enable_grade_sync');
        $backend_url = get_config('local_moodle_zoho_sync', 'backend_url');
        
        error_log('=== GRADE SYNC CONFIG === enable_grade_sync: ' . ($grade_sync_enabled ? 'YES' : 'NO') . ', backend_url: ' . $backend_url);
        
        if (!$grade_sync_enabled) {
            error_log('=== GRADE SYNC DISABLED === Skipping webhook');
            return;
        }

        try {
            // Extract grade data
            $extractor = new data_extractor();
            $grade_data = $extractor->extract_grade_data($event->objectid, $event->userid);

            if (!$grade_data) {
                self::log_error('Failed to extract grade data for grade ID: ' . $event->objectid);
                error_log('=== GRADE EXTRACTION FAILED === Grade ID: ' . $event->objectid);
                return;
            }

            error_log('=== GRADE DATA EXTRACTED === ' . json_encode($grade_data));

            // Send webhook
            $sender = new webhook_sender();
            $response = $sender->send_grade_updated($grade_data);

            error_log('=== WEBHOOK RESPONSE === ' . json_encode($response));

            self::log_debug('Grade updated webhook sent', [
                'grade_id' => $event->objectid,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            error_log('=== GRADE OBSERVER ERROR === ' . $e->getMessage());
            self::log_error('Error in grade_updated observer: ' . $e->getMessage());
        }
    }

    /**
     * Handle assignment submission graded (mod_assign) and forward to grade extraction.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        // FORCE LOG - ALWAYS fires to verify observer is called
        error_log('=== SUBMISSION_GRADED OBSERVER FIRED === Assignment: ' . ($event->other['assignmentid'] ?? 'N/A'));
        
        // Check if grade sync is enabled
        $grade_sync_enabled = get_config('local_moodle_zoho_sync', 'enable_grade_sync');
        $backend_url = get_config('local_moodle_zoho_sync', 'backend_url');
        
        error_log('=== SUBMISSION GRADE CONFIG === enable_grade_sync: ' . ($grade_sync_enabled ? 'YES' : 'NO') . ', backend_url: ' . $backend_url);
        
        if (!$grade_sync_enabled) {
            error_log('=== GRADE SYNC DISABLED === Skipping webhook');
            return;
        }

        try {
            global $DB;

            $assignmentid = $event->other['assignmentid'] ?? null;
            $studentid = $event->relateduserid ?? null;
            
            error_log('=== SUBMISSION_GRADED DATA === assignmentid: ' . $assignmentid . ', studentid: ' . $studentid);
            
            if (empty($assignmentid) || empty($studentid)) {
                error_log('=== MISSING DATA === Assignment or student ID empty');
                return;
            }

            // Resolve grade_items entry for this assignment
            $gradeitem = $DB->get_record('grade_items', [
                'itemtype' => 'mod',
                'itemmodule' => 'assign',
                'iteminstance' => $assignmentid
            ], 'id');

            if (!$gradeitem) {
                error_log('=== GRADE ITEM NOT FOUND === for assignment: ' . $assignmentid);
                return;
            }

            error_log('=== GRADE ITEM FOUND === ID: ' . $gradeitem->id);

            // Resolve grade_grades record for this student/item
            $grade = $DB->get_record('grade_grades', [
                'itemid' => $gradeitem->id,
                'userid' => $studentid
            ], 'id');

            if (!$grade) {
                error_log('=== GRADE RECORD NOT FOUND === for item: ' . $gradeitem->id . ', student: ' . $studentid);
                return;
            }

            error_log('=== GRADE RECORD FOUND === ID: ' . $grade->id);

            $extractor = new data_extractor();
            $grade_data = $extractor->extract_grade_data($grade->id, $event->userid);

            if (!$grade_data) {
                self::log_error('Failed to extract grade data for submission_graded grade ID: ' . $grade->id);
                error_log('=== GRADE EXTRACTION FAILED === Grade ID: ' . $grade->id);
                return;
            }

            error_log('=== GRADE DATA EXTRACTED === ' . json_encode($grade_data));

            $sender = new webhook_sender();
            $response = $sender->send_grade_updated($grade_data);

            error_log('=== WEBHOOK RESPONSE === ' . json_encode($response));

            self::log_debug('Submission graded webhook sent', [
                'grade_id' => $grade->id,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            error_log('=== SUBMISSION_GRADED ERROR === ' . $e->getMessage());
            self::log_error('Error in submission_graded observer: ' . $e->getMessage());
        }
    }

    /**
     * Log error message
     * 
     * @param string $message
     */
    private static function log_error($message) {
        error_log('[Moodle-Zoho Sync ERROR] ' . $message);
    }

    /**
     * Log debug message if debug is enabled
     * 
     * @param string $message
     * @param array $context
     */
    private static function log_debug($message, $context = []) {
        if (get_config('local_moodle_zoho_sync', 'enable_debug')) {
            error_log('[Moodle-Zoho Sync DEBUG] ' . $message . ' ' . json_encode($context));
        }
    }
}
