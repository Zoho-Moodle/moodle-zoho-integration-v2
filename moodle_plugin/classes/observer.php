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
            $context = webhook_sender::extract_context($user_data, 'user_created');
            $response = $sender->send_user_created($user_data, null, $context);

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
            $context = webhook_sender::extract_context($user_data, 'user_updated');
            $response = $sender->send_user_updated($user_data, null, $context);

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
            $context = webhook_sender::extract_context($enrollment_data, 'enrollment_created');
            $response = $sender->send_enrollment_created($enrollment_data, null, $context);

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
            // IMPORTANT: For deleted events, the record is already gone from DB
            // We need to extract data from the event itself
            $eventdata = $event->get_data();
            
            error_log('=== ENROLLMENT DELETE EVENT DATA === ' . json_encode($eventdata));
            
            // Build enrollment data from event
            $enrollment_data = [
                'enrolment_id' => $event->objectid,
                'user_id' => $event->relateduserid,
                'course_id' => $event->courseid,
                'action' => 'deleted',
                'timestamp' => $event->timecreated
            ];
            
            // Try to get additional info if available
            if (!empty($eventdata['other'])) {
                if (isset($eventdata['other']['userenrolment'])) {
                    $enrollment_data = array_merge($enrollment_data, $eventdata['other']['userenrolment']);
                }
            }

            error_log('=== ENROLLMENT DATA BUILT === ' . json_encode($enrollment_data));

            // Send webhook
            $sender = new webhook_sender();
            $context = webhook_sender::extract_context($enrollment_data, 'enrollment_deleted');
            $response = $sender->send_enrollment_deleted($enrollment_data, null, $context);

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
            $context = webhook_sender::extract_context($grade_data, 'grade_updated');
            $response = $sender->send_grade_updated($grade_data, null, $context);

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
     * Handle assignment submission graded (mod_assign) - LIGHTWEIGHT VERSION
     * Sends only BASIC data to Zoho, queues for enrichment by scheduled task.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        global $DB;
        
        error_log('=== 🔵 SUBMISSION_GRADED OBSERVER (LIGHTWEIGHT) ===');
        
        // Check if grade sync is enabled
        if (!get_config('local_moodle_zoho_sync', 'enable_grade_sync')) {
            error_log('Grade sync disabled - skipping');
            return;
        }

        try {
            $starttime = microtime(true);
            
            // ══════════════════════════════════════════════════════════
            // STEP 1: Fast Data Extraction (NO heavy queries)
            // ══════════════════════════════════════════════════════════
            
            $grade = $DB->get_record('assign_grades', ['id' => $event->objectid]);
            if (!$grade) {
                error_log('Grade record not found: ' . $event->objectid);
                return;
            }
            
            $assignment = $DB->get_record('assign', ['id' => $grade->assignment]);
            $course = $DB->get_record('course', ['id' => $assignment->course]);
            $student = $DB->get_record('user', ['id' => $grade->userid]);
            
            if (!$assignment || !$course || !$student) {
                error_log('Missing records - assignment/course/student');
                return;
            }
            
            // Check if submission exists (للتفريق بين F و R)
            // ✅ ONLY accept 'submitted' status - student MUST submit, not just draft
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $assignment->id,
                'userid' => $student->id
            ]);
            $has_submission = ($submission && $submission->status === 'submitted');
            
            // Get workflow state (قبل ما نمسح، نجيب الحالة)
            $workflow_state = null;
            $user_flags = $DB->get_record('assign_user_flags', [
                'assignment' => $assignment->id,
                'userid' => $student->id
            ]);
            if ($user_flags && !empty($user_flags->workflowstate)) {
                $workflow_state = $user_flags->workflowstate;
            }
            
            // Attempt number
            $attemptnumber = isset($grade->attemptnumber) ? (int)$grade->attemptnumber : 0;
            
            // Feedback (نجيبها قبل التحويل لأنها بتأثر على F)
            $feedback = self::get_quick_feedback($grade->id);
            
            // ✅ CRITICAL FIX: If feedback contains "01122", update grade in DB to 0
            // This ensures consistency: Observer + Extractor + Scheduled Task all see grade=0 → F
            if (!empty($feedback) && strpos($feedback, '01122') !== false) {
                error_log("⚠️ 01122 DETECTED - Updating grade in DB: {$grade->grade} → 0 (F)");
                $grade->grade = 0;  // Set to 0 to indicate F (Fail - Invalid file)
                $grade->timemodified = time();
                $DB->update_record('assign_grades', $grade);
                error_log("✅ Grade updated in DB successfully");
            }
            
            // Quick BTEC grade conversion (F for no submission, R for fail with submission, F if feedback contains 01122)
            $btec_grade = self::quick_btec_conversion($grade->grade, $has_submission, $feedback);
            
            // ⚠️ RR Detection REMOVED from Observer
            // Reason: Observer only sees submissions - cannot detect "no submission" for attempt 2
            // RR Logic: attempt 1 = R + attempt 2 = NO SUBMISSION → RR
            // This can only be detected by Scheduled Task after due date passes
            
            // Grader info (quick)
            $grader = $DB->get_record('user', ['id' => $event->userid]);
            $graderrole = self::detect_grader_role($grader->id, $course->id);
            
            // ✅ Extract Learning Outcomes (complete data in one go)
            $extractor = new data_extractor();
            $learning_outcomes = $extractor->extract_btec_learning_outcomes($grade);
            
            // ══════════════════════════════════════════════════════════
            // STEP 2: Build Complete Payload (ALL data in one request)
            // ══════════════════════════════════════════════════════════
            
            $composite_key = $student->id . '_' . $course->id . '_' . $assignment->id;
            
            $complete_payload = [
                'grade_id' => $grade->id,
                'student_id' => $student->id,
                'student_name' => fullname($student),
                'student_email' => $student->email,
                'assignment_id' => $assignment->id,
                'assignment_name' => $assignment->name,
                'course_id' => $course->id,
                'course_name' => $course->fullname,
                'grade' => $btec_grade,
                'raw_grade' => $grade->grade,
                'attempt_number' => $attemptnumber + 1,  // Display as 1-indexed
                'attemptnumber_zero_indexed' => $attemptnumber,  // For backend logic
                'timestamp' => time(),
                'graded_at' => date('Y-m-d H:i:s', $grade->timemodified),
                'grader_name' => fullname($grader),
                'grader_role' => $graderrole,
                'feedback' => $feedback,
                'workflow_state' => $workflow_state,
                'learning_outcomes' => $learning_outcomes,  // ✅ Complete Learning Outcomes included
                'composite_key' => $composite_key,
                'sync_type' => 'complete',  // ✅ Flag: this is complete data (not basic)
            ];
            
            $extraction_time = round((microtime(true) - $starttime) * 1000, 2);
            error_log("✅ Complete extraction: {$extraction_time}ms - {$student->firstname} {$student->lastname} - {$btec_grade}" . 
                      (count($learning_outcomes) > 0 ? " + " . count($learning_outcomes) . " LOs" : ""));
            
            // ═════════════Complete Data to Zoho (ONE request with everything)
            // ══════════════════════════════════════════════════════════
            
            $sender = new webhook_sender();
            $context = webhook_sender::extract_context($complete_payload, 'grade_updated');
            $response = $sender->send_grade_updated($complete_payload, null, $context);
            
            // Extract Zoho record ID from response
            $zoho_record_id = null;
            if (isset($response['body'])) {
                $body = json_decode($response['body'], true);
                $zoho_record_id = $body['zoho_id'] ?? null;
            }
            
            $send_time = round((microtime(true) - $starttime) * 1000, 2);
            error_log("✅ Zoho sync: {$send_time}ms - Zoho ID: " . ($zoho_record_id ?? 'N/A'));
            
            // ══════════════════════════════════════════════════════════
            // STEP 4: Queue for Tracking (no enrichment needed - already complete)
            // ══════════════════════════════════════════════════════════
            
            $queue_record = new \stdClass();
            $queue_record->grade_id = $grade->id;
            $queue_record->student_id = $student->id;
            $queue_record->assignment_id = $assignment->id;
            $queue_record->course_id = $course->id;
            $queue_record->zoho_record_id = $zoho_record_id;
            $queue_record->composite_key = $composite_key;
            $queue_record->workflow_state = $workflow_state;
            $queue_record->status = 'SYNCED';  // ✅ Complete sync (no enrichment needed)
            $queue_record->basic_sent_at = time();
            $queue_record->needs_enrichment = 0;  // ✅ Already has Learning Outcomes
            $queue_record->needs_rr_check = 0;  // ✅ RR already detected in Observer if applicable
            $queue_record->retry_count = 0;
            $queue_record->timecreated = time();
            $queue_record->timemodified = time();
            
            // Check if already queued (update instead of insert)
            $existing = $DB->get_record('local_mzi_grade_queue', ['composite_key' => $composite_key]);
            if ($existing) {
                $queue_record->id = $existing->id;
                $DB->update_record('local_mzi_grade_queue', $queue_record);
                error_log("✅ Updated queue record (resubmission)");
            } else {
                $DB->insert_record('local_mzi_grade_queue', $queue_record);
                error_log("✅ Inserted queue record (new submission)");
            }
            
            $total_time = round((microtime(true) - $starttime) * 1000, 2);
            error_log("🎉 OBSERVER COMPLETE: {$total_time}ms total (FULL SYNC)");
            
            self::log_debug('Grade sync complete (full)', [
                'grade_id' => $grade->id,
                'grade' => $btec_grade,
                'attempt' => $attemptnumber + 1,
                'learning_outcomes' => count($learning_outcomes),
                'time_ms' => $total_time,
                'zoho_id' => $zoho_record_id
            ]);

        } catch (\Exception $e) {
            error_log('=== ❌ OBSERVER ERROR === ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            self::log_error('Error in submission_graded observer: ' . $e->getMessage());
        }
    }
    
    /**
     * Quick BTEC grade conversion (no database joins)
     * Based on raw numeric grade from assign_grades.grade
     * 
     * ═══════════════════════════════════════════════════════════
     * Grade Logic (Updated v4.1 - Corrected RR):
     * ═══════════════════════════════════════════════════════════
     * Observer handles normal grading, Scheduled Task handles RR.
     * 
     * 1. F (Fail): 
     *    - grade = 0 (Observer sets this when feedback contains "01122")
     *    - Teacher explicitly marked wrong file
     * 
     * 2. R (Refer) - First OR Second Attempt Failed:
     *    - Submitted but grade < 2 (didn't meet pass criteria)
     *    - Teacher grades it as refer
     *    - ✅ Attempt 2 with R stays as R (not RR)
     * 
     * 3. RR (Double Refer) - NO SUBMISSION on Attempt 2:
     *    - ⚠️ Detected by SCHEDULED TASK (not observer)
     *    - Logic: attempt 1 = R AND attempt 2 = NO SUBMISSION → RR
     *    - Observer cannot detect "no submission"
     * 
     * 4. P/M/D (Pass):
     *    - First OR second attempt can pass
     *    - P: grade >= 2, M: grade >= 3, D: grade >= 4
     * 
     * 5. F (No Submission):
     *    - ⚠️ Created by SCHEDULED TASK create_f_grades()
     *    - Observer never sees students who don't submit
     * ═══════════════════════════════════════════════════════════
     * 
     * @param float|null $rawgrade Raw numeric grade (0-4)
     * @param bool $has_submission Whether student submitted work (not used in v4.1)
     * @param string $feedback Feedback text (not used - 01122 handled before conversion)
     * @return string BTEC grade (F/R/P/M/D)
     */
    private static function quick_btec_conversion($rawgrade, $has_submission = true, $feedback = '') {
        // ✅ PRIORITY 1: F (Fail) - grade = 0
        // Observer already set grade=0 if feedback contains "01122"
        if (isset($rawgrade) && $rawgrade == 0) {
            return "F";  // Fail - Invalid/Insufficient file
        }
        
        // ✅ PRIORITY 2: R (Refer) - Submitted but didn't meet Pass criteria
        // Observer ONLY sees submitted grades - cannot detect "no submission"
        if (is_null($rawgrade) || $rawgrade < 2) {
            return "R";  // Refer - Needs improvement (scheduled task will check for RR)
        }
        
        // ✅ PRIORITY 3: Pass grades (P/M/D)
        if ($rawgrade >= 4) {
            return "D";  // Distinction
        } elseif ($rawgrade >= 3) {
            return "M";  // Merit
        } elseif ($rawgrade >= 2) {
            return "P";  // Pass
        }
        
        // Default fallback
        return "R";
    }
    
    /**
     * Get quick feedback from assignfeedback_comments
     * Single query - no joins
     * 
     * @param int $grade_id assign_grades.id
     * @return string Feedback text (empty if none)
     */
    private static function get_quick_feedback($grade_id) {
        global $DB;
        
        $feedbackplugin = $DB->get_record('assignfeedback_comments', ['grade' => $grade_id]);
        if ($feedbackplugin && !empty($feedbackplugin->commenttext)) {
            return trim(strip_tags($feedbackplugin->commenttext));
        }
        
        return '';
    }
    
    /**
     * Detect grader role (Teacher or IV)
     * Quick role check without heavy queries
     * 
     * @param int $grader_id User ID of grader
     * @param int $course_id Course ID
     * @return string 'Teacher', 'IV', or 'Unknown'
     */
    private static function detect_grader_role($grader_id, $course_id) {
        $context = \context_course::instance($course_id);
        $roles = get_user_roles($context, $grader_id);
        
        foreach ($roles as $role) {
            if ($role->shortname === 'internalverifier') {
                return 'IV';
            } elseif ($role->shortname === 'editingteacher') {
                return 'Teacher';
            }
        }
        
        return 'Unknown';
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
