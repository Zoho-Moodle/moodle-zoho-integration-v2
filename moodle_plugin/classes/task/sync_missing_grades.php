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
 * Scheduled task to sync missing grades (enrichment, RR detection, F grades).
 * Runs daily at 3 AM to process queued grade records (Hybrid Grading System).
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync\task;

defined('MOODLE_INTERNAL') || die();

// ✅ Correct paths: go up one level from /task/ to /classes/
require_once(dirname(__DIR__) . '/webhook_sender.php');
require_once(dirname(__DIR__) . '/data_extractor.php');

use local_moodle_zoho_sync\webhook_sender;
use local_moodle_zoho_sync\data_extractor;

/**
 * Scheduled task class for syncing missing grades.
 */
class sync_missing_grades extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_sync_missing_grades', 'local_moodle_zoho_sync');
    }

    /**
     * Execute task - SIMPLIFIED VERSION (v2)
     * Phase 1: Create F grades for students who didn't submit at all
     * Phase 2: Create RR grades for students who got R in attempt 1 and didn't submit attempt 2
     */
    public function execute() {
        global $DB;
        
        mtrace('=== 🔵 GRADING SCHEDULED TASK STARTED ===');
        $starttime = microtime(true);
        
        // Check if grade sync is enabled
        if (!get_config('local_moodle_zoho_sync', 'enable_grade_sync')) {
            mtrace('Grade sync disabled - skipping');
            return;
        }
        
        // ══════════════════════════════════════════════════════════
        // PHASE 1: Create F grades for missing submissions (attempt 0)
        // ══════════════════════════════════════════════════════════
        
        mtrace('--- Phase 1: Creating F grades for missing submissions ---');
        $f_count = $this->create_f_grades();
        mtrace("✅ F grades created: {$f_count}");
        
        // ══════════════════════════════════════════════════════════
        // PHASE 2: Create RR grades (attempt 1 = R, attempt 2 = no submission)
        // ══════════════════════════════════════════════════════════
        
        mtrace('--- Phase 2: Checking for RR (R + No Submit) ---');
        $rr_count = $this->check_for_rr();
        mtrace("✅ RR grades created: {$rr_count}");
        
        $totaltime = round(microtime(true) - $starttime, 2);
        mtrace("🎉 TASK COMPLETE: {$totaltime}s total");
        mtrace("Summary: {$f_count} F grades, {$rr_count} RR grades created");
    }
    
    
    /**
     * Create F grades for students who never submitted
     * Only for assignments past due date
     * 
     * @return int Number of F grades created
     */
    private function create_f_grades() {
        global $DB;
        
        $f_created = 0;
        
        // Get all assignments with due date in the past
        $sql = "SELECT a.id, a.name, a.course, a.duedate
                FROM {assign} a
                WHERE a.duedate > 0 
                  AND a.duedate < :now";
        
        $overdue_assignments = $DB->get_records_sql($sql, ['now' => time()]);
        
        if (empty($overdue_assignments)) {
            mtrace('No overdue assignments found');
            return 0;
        }
        
        mtrace('Found ' . count($overdue_assignments) . ' overdue assignments');
        
        foreach ($overdue_assignments as $assignment) {
            try {
                // Get all enrolled students in this course
                $context = \context_course::instance($assignment->course);
                $enrolled = get_enrolled_users($context, 'mod/assign:submit');
                
                foreach ($enrolled as $student) {
                    // Check if student has ANY submission
                    $has_submission = $DB->record_exists('assign_submission', [
                        'assignment' => $assignment->id,
                        'userid' => $student->id,
                        'status' => 'submitted'
                    ]);
                    
                    // Check if already has a grade (F or otherwise)
                    $has_grade = $DB->record_exists('assign_grades', [
                        'assignment' => $assignment->id,
                        'userid' => $student->id
                    ]);
                    
                    // Check if F already created in queue
                    $composite_key = $student->id . '_' . $assignment->course . '_' . $assignment->id;
                    $already_queued = $DB->record_exists('local_mzi_grade_queue', [
                        'composite_key' => $composite_key,
                        'status' => 'F_CREATED'
                    ]);
                    
                    // If no submission, no grade, and not already queued → create F
                    if (!$has_submission && !$has_grade && !$already_queued) {
                        $this->create_f_grade_record($student, $assignment);
                        $f_created++;
                    }
                }
                
            } catch (\Exception $e) {
                mtrace("❌ Error processing assignment {$assignment->id}: " . $e->getMessage());
            }
        }
        
        return $f_created;
    }
    
    /**
     * Create F grade record for a student
     * 
     * @param object $student Student user object
     * @param object $assignment Assignment object
     */
    private function create_f_grade_record($student, $assignment) {
        global $DB;
        
        try {
            $sender = new webhook_sender();
            $course = $DB->get_record('course', ['id' => $assignment->course]);
            
            // Build F grade payload
            $f_payload = [
                'student_id' => $student->id,
                'student_name' => fullname($student),
                'student_email' => $student->email,
                'assignment_id' => $assignment->id,
                'assignment_name' => $assignment->name,
                'course_id' => $course->id,
                'course_name' => $course->fullname,
                'grade' => 'F',  // Fail - No submission
                'raw_grade' => 0,
                'attempt_number' => 0,
                'status' => 'Failed to Submit',  // ✅ Clear status for Zoho Grade_Status field
                'workflow_state' => 'failedtosubmit',  // ✅ Moodle-style workflow state
                'composite_key' => $student->id . '_' . $course->id . '_' . $assignment->id,
                'timestamp' => time(),
                'graded_at' => date('Y-m-d H:i:s', $assignment->duedate),  // ✅ Use due date as "graded" date
                'auto_generated' => true
            ];
            
            // Send to Zoho
            $context = webhook_sender::extract_context($f_payload, 'grade_f_created');
            $response = $sender->send_grade_updated($f_payload, null, $context);
            
            // Extract Zoho record ID
            $zoho_record_id = null;
            if (isset($response['body'])) {
                $body = json_decode($response['body'], true);
                $zoho_record_id = $body['zoho_id'] ?? null;
            }
            
            // Create queue record for tracking
            $queue_record = new \stdClass();
            $queue_record->grade_id = 0; // No Moodle grade record
            $queue_record->student_id = $student->id;
            $queue_record->assignment_id = $assignment->id;
            $queue_record->course_id = $course->id;
            $queue_record->zoho_record_id = $zoho_record_id;
            $queue_record->composite_key = $f_payload['composite_key'];
            $queue_record->status = 'F_CREATED';
            $queue_record->basic_sent_at = time();
            $queue_record->needs_enrichment = 0; // F grades don't need enrichment
            $queue_record->needs_rr_check = 0;
            $queue_record->retry_count = 0;
            $queue_record->timecreated = time();
            $queue_record->timemodified = time();
            
            $DB->insert_record('local_mzi_grade_queue', $queue_record);
            
            mtrace("✅ Created F grade: {$student->firstname} {$student->lastname} - {$assignment->name}");
            
        } catch (\Exception $e) {
            mtrace("❌ Failed to create F grade: " . $e->getMessage());
        }
    }
    
    /**
     * Check for RR (Double Refer) - NEW LOGIC
     * RR = Attempt 1 got R (grade < 2) + Attempt 2 no submission (past due date)
     * 
     * @return int Number of RR grades created
     */
    private function check_for_rr() {
        global $DB;
        
        $rr_created = 0;
        
        // Get all assignments that allow multiple attempts and have due date in past
        $sql = "SELECT a.id, a.name, a.course, a.duedate, a.attemptreopenmethod
                FROM {assign} a
                WHERE a.duedate > 0 
                  AND a.duedate < :now
                  AND a.attemptreopenmethod != :manual";  // Must allow resubmissions
        
        $assignments = $DB->get_records_sql($sql, [
            'now' => time(),
            'manual' => 'none'  // Exclude assignments that don't allow resubmissions
        ]);
        
        if (empty($assignments)) {
            mtrace('No assignments with resubmissions found');
            return 0;
        }
        
        mtrace('Found ' . count($assignments) . ' assignments with resubmissions enabled');
        
        foreach ($assignments as $assignment) {
            try {
                // Find students with attempt 0 (first attempt) = R (grade < 2 but not F)
                $sql_students = "SELECT ag.userid, ag.grade, ag.assignment
                                 FROM {assign_grades} ag
                                 WHERE ag.assignment = :assignment
                                   AND ag.attemptnumber = 0
                                   AND ag.grade IS NOT NULL
                                   AND ag.grade > 0
                                   AND ag.grade < 2";
                
                $students_with_r = $DB->get_records_sql($sql_students, ['assignment' => $assignment->id]);
                
                if (empty($students_with_r)) {
                    continue;  // No students with R in this assignment
                }
                
                mtrace("  Assignment {$assignment->name}: Found " . count($students_with_r) . " students with R on attempt 1");
                
                foreach ($students_with_r as $record) {
                    // Check if attempt 1 (second attempt) exists AND is graded
                    $attempt1 = $DB->get_record('assign_grades', [
                        'assignment' => $assignment->id,
                        'userid' => $record->userid,
                        'attemptnumber' => 1
                    ]);
                    
                    // RR Logic: attempt 0 = R + attempt 1 = not graded (grade = -1 or NULL)
                    // Note: In Moodle, when student opens attempt 2 but doesn't submit,
                    // a grade record is created with grade = -1 (not graded)
                    $attempt1_not_graded = (!$attempt1 || $attempt1->grade == -1);
                    
                    mtrace("    Student {$record->userid}: attempt1_grade=" . ($attempt1 ? $attempt1->grade : 'NULL') . ", not_graded=" . ($attempt1_not_graded ? 'YES' : 'NO'));
                    
                    // RR condition: attempt 0 = R AND attempt 1 not graded
                    if ($attempt1_not_graded) {
                        // Check if RR already processed
                        $composite_key = $record->userid . '_' . $assignment->course . '_' . $assignment->id;
                        $existing_queue = $DB->get_record('local_mzi_grade_queue', ['composite_key' => $composite_key]);
                        
                        // Skip if status is RR_CREATED (already processed)
                        if ($existing_queue && $existing_queue->status == 'RR_CREATED') {
                            mtrace("    → RR already created, skipping");
                            continue;
                        }
                        
                        $student = $DB->get_record('user', ['id' => $record->userid]);
                        $this->create_rr_grade_record($student, $assignment);
                        $rr_created++;
                    }
                }
                
            } catch (\Exception $e) {
                mtrace("❌ Error checking RR for assignment {$assignment->id}: " . $e->getMessage());
            }
        }
        
        return $rr_created;
    }
    
    /**
     * Create RR grade record for a student
     * 
     * @param object $student Student user object
     * @param object $assignment Assignment object
     */
    private function create_rr_grade_record($student, $assignment) {
        global $DB;
        
        try {
            $sender = new webhook_sender();
            $course = $DB->get_record('course', ['id' => $assignment->course]);
            
            // Build RR grade payload
            $rr_payload = [
                'student_id' => $student->id,
                'student_name' => fullname($student),
                'student_email' => $student->email,
                'assignment_id' => $assignment->id,
                'assignment_name' => $assignment->name,
                'course_id' => $course->id,
                'course_name' => $course->fullname,
                'grade' => 'RR',  // Double Refer
                'raw_grade' => 0,
                'attempt_number' => 2,
                'status' => 'Double Refer',  // Clear status
                'workflow_state' => 'doublerefer',
                'composite_key' => $student->id . '_' . $course->id . '_' . $assignment->id,  // ✅ نفس الـ key (بدون _RR)
                'timestamp' => time(),
                'graded_at' => date('Y-m-d H:i:s', $assignment->duedate),
                'auto_generated' => true,
                'is_rr_update' => true,  // ✅ Flag: this is RR update (merge with existing data)
            ];
            
            // Send to Zoho
            $context = webhook_sender::extract_context($rr_payload, 'grade_rr_created');
            $response = $sender->send_grade_updated($rr_payload, null, $context);
            
            // Extract Zoho record ID
            $zoho_record_id = null;
            if (isset($response['body'])) {
                $body = json_decode($response['body'], true);
                $zoho_record_id = $body['zoho_id'] ?? null;
            }
            
            // Create queue record for tracking
            $queue_record = new \stdClass();
            $queue_record->grade_id = 0;
            $queue_record->student_id = $student->id;
            $queue_record->assignment_id = $assignment->id;
            $queue_record->course_id = $course->id;
            $queue_record->zoho_record_id = $zoho_record_id;
            $queue_record->composite_key = $rr_payload['composite_key'];  // ✅ نفس الـ key (بدون _RR)
            $queue_record->status = 'RR_CREATED';
            $queue_record->basic_sent_at = time();
            $queue_record->needs_enrichment = 0;
            $queue_record->needs_rr_check = 0;
            $queue_record->retry_count = 0;
            $queue_record->timecreated = time();
            $queue_record->timemodified = time();
            
            // Update existing queue record instead of creating new one
            $existing_queue = $DB->get_record('local_mzi_grade_queue', ['composite_key' => $rr_payload['composite_key']]);
            if ($existing_queue) {
                $queue_record->id = $existing_queue->id;
                $DB->update_record('local_mzi_grade_queue', $queue_record);
            } else {
                $DB->insert_record('local_mzi_grade_queue', $queue_record);
            }
            
            mtrace("✅ Created RR grade: {$student->firstname} {$student->lastname} - {$assignment->name}");
            
        } catch (\Exception $e) {
            mtrace("❌ Failed to create RR grade: " . $e->getMessage());
        }
    }
}
