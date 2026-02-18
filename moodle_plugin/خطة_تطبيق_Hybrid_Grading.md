# 📋 خطة تطبيق Hybrid Grading System

**التاريخ:** February 9, 2026  
**الهدف:** Observer خفيف + Scheduled Task شامل

---

## ✅ ما اتفقنا عليه:

### **1. Learning Outcomes:**
```
✅ موجودين أصلاً في: extract_btec_learning_outcomes()
✅ المصدر: gradingform_btec_criteria + gradingform_btec_fillings
✅ البيانات: code, level, description, score, feedback, achieved
✅ استخراجهم: ثقيل (joins + loops) → نتركهم للـ Scheduled Task
```

### **2. Zoho Structure:**
```
✅ Observer → ينشئ record في BTEC_Grades (basic data)
✅ Task → يحدّث نفس الـ record (enrichment)
✅ استخدام composite_key: {studentid}_{courseid}_{assignmentid}
```

### **3. Timing:**
```
✅ Task مرة باليوم (3 AM) → كافية
✅ Observer → فوري (< 0.5s)
```

### **4. Enrichment Priority:**

**Observer (Fast - Basic):**
- ✅ grade (P/M/D/R/RR/F)
- ✅ student (id, name, email)
- ✅ assignment (id, name)
- ✅ timestamp
- ✅ attempt_number
- ✅ grader info (name, role)
- ✅ feedback (text)

**Task (Slow - Enriched):**
- ✅ learning_outcomes (full BTEC breakdown)
- ✅ attempt_history (all previous attempts)
- ✅ grade_logic (R vs RR calculation)
- ✅ missing submissions (F grades)

### **5. Failure Handling:**
```
✅ إذا Task فشل:
   - يحاول 3 مرات
   - بعدها: alert للـ admin
   - البيانات الأساسية تبقى موجودة
```

---

## 🎯 منطق الدرجات (R, RR, F):

### **القواعد:**

```
┌──────────────────────┬─────────────────────────────────────┐
│ الحالة               │ الدرجة المرسلة لـ Zoho               │
├──────────────────────┼─────────────────────────────────────┤
│ لم يقدم submission  │ F (Fail - No Submission)            │
│ محاولة أولى: Refer  │ R (Refer - 1st Attempt)             │
│ محاولتين: كلهم Refer│ RR (Refer Refer - 2nd Attempt)     │
│ نجح بأي محاولة      │ P / M / D (Pass/Merit/Distinction)  │
└──────────────────────┴─────────────────────────────────────┘
```

### **التطبيق:**

#### **في Observer:**
```php
// Observer يرسل الدرجة الفورية فقط
if ($btec_result == 'Refer' && $attemptnumber == 0) {
    $grade = 'R';  // First attempt refer
} else {
    $grade = $btec_result;  // P, M, D, or Refer
}

// Queue for Task to check RR logic
queue_for_enrichment($grade_id);
```

#### **في Scheduled Task:**
```php
// Task يفحص المحاولات المتعددة
$attempts = get_all_attempts($student_id, $assignment_id);

if (count($attempts) >= 2) {
    $first = $attempts[0];
    $second = $attempts[1];
    
    if ($first['result'] == 'Refer' && $second['result'] == 'Refer') {
        update_grade_to_RR($grade_id);  // Update Zoho to RR
    }
}

// Check missing submissions
if (no_submission_before_deadline($student_id, $assignment_id)) {
    create_f_grade($student_id, $assignment_id);
}
```

---

## 📊 Database Structure:

### **جدول جديد: mdl_zoho_grade_queue**

```sql
CREATE TABLE mdl_zoho_grade_queue (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Moodle IDs
    grade_id BIGINT NOT NULL,
    student_id BIGINT NOT NULL,
    assignment_id BIGINT NOT NULL,
    course_id BIGINT NOT NULL,
    
    -- Zoho Integration
    zoho_record_id VARCHAR(50),
    composite_key VARCHAR(255),  -- studentid_courseid_assignmentid
    
    -- Status Tracking
    status VARCHAR(20) DEFAULT 'BASIC_SENT',
        -- BASIC_SENT: Observer sent basic data
        -- ENRICHED: Task added learning outcomes
        -- RR_UPDATED: Task updated R to RR
        -- FAILED: Error occurred
    
    -- Timestamps
    basic_sent_at BIGINT,
    enriched_at BIGINT,
    failed_at BIGINT,
    
    -- Flags
    needs_enrichment TINYINT DEFAULT 1,
    needs_rr_check TINYINT DEFAULT 0,  -- If grade is R, check for RR
    
    -- Error Handling
    error_message TEXT,
    retry_count INT DEFAULT 0,
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_needs_enrichment (needs_enrichment),
    INDEX idx_grade_id (grade_id),
    INDEX idx_student_assignment (student_id, assignment_id)
);
```

---

## 🏗️ الكود المقترح:

### **1. Observer (Modified - Lightweight)**

```php
public static function submission_graded(\mod_assign\event\submission_graded $event) {
    global $DB;
    
    $logfile = __DIR__ . '/../debug_log.txt';
    file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 🔵 submission_graded OBSERVER START\n", FILE_APPEND);
    
    // Check if enabled
    if (!get_config('local_moodle_zoho_sync', 'enable_grade_sync')) {
        return;
    }
    
    try {
        // ══════════════════════════════════════
        // BASIC DATA EXTRACTION (FAST)
        // ══════════════════════════════════════
        
        $grade = $DB->get_record('assign_grades', ['id' => $event->objectid]);
        if (!$grade) return;
        
        $assignment = $DB->get_record('assign', ['id' => $grade->assignment]);
        $course = $DB->get_record('course', ['id' => $assignment->course]);
        $student = $DB->get_record('user', ['id' => $grade->userid]);
        if (!$assignment || !$course || !$student) return;
        
        // Attempt number
        $attemptnumber = ($grade->attemptnumber ?? 0);
        
        // Quick BTEC grade conversion (NO learning outcomes yet)
        $btec_grade = self::quick_btec_conversion($grade);
        
        // Grader info
        $grader = $DB->get_record('user', ['id' => $event->userid]);
        $graderrole = self::detect_grader_role($grader->id, $course->id);
        
        // Feedback (quick)
        $feedback = self::get_quick_feedback($grade->id);
        
        // ══════════════════════════════════════
        // BUILD BASIC PAYLOAD
        // ══════════════════════════════════════
        
        $basic_payload = [
            'grade_id' => $grade->id,
            'student_id' => $student->id,
            'student_name' => fullname($student),
            'student_email' => $student->email,
            'assignment_id' => $assignment->id,
            'assignment_name' => $assignment->name,
            'course_id' => $course->id,
            'course_name' => $course->fullname,
            'grade' => $btec_grade,
            'attempt_number' => $attemptnumber + 1,
            'timestamp' => time(),
            'grader_name' => fullname($grader),
            'grader_role' => $graderrole,
            'feedback' => $feedback,
            'status' => 'PENDING_ENRICHMENT',
            'composite_key' => $student->id . '_' . $course->id . '_' . $assignment->id
        ];
        
        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] ℹ️ Basic payload: " . json_encode($basic_payload) . "\n", FILE_APPEND);
        
        // ══════════════════════════════════════
        // SEND TO ZOHO (CREATE RECORD)
        // ══════════════════════════════════════
        
        $sender = new webhook_sender();
        $context = webhook_sender::extract_context($basic_payload, 'grade_updated');
        $response = $sender->send_grade_updated($basic_payload, null, $context);
        
        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] ✅ Zoho response: " . json_encode($response) . "\n", FILE_APPEND);
        
        // ══════════════════════════════════════
        // QUEUE FOR SCHEDULED TASK
        // ══════════════════════════════════════
        
        $queue_record = [
            'grade_id' => $grade->id,
            'student_id' => $student->id,
            'assignment_id' => $assignment->id,
            'course_id' => $course->id,
            'zoho_record_id' => $response['zoho_id'] ?? null,
            'composite_key' => $basic_payload['composite_key'],
            'status' => 'BASIC_SENT',
            'basic_sent_at' => time(),
            'needs_enrichment' => 1,
            'needs_rr_check' => ($btec_grade == 'R') ? 1 : 0,  // Check if R → RR
            'retry_count' => 0
        ];
        
        $DB->insert_record('zoho_grade_queue', $queue_record);
        
        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] 📝 Queued for enrichment\n", FILE_APPEND);
        
        self::log_debug('Submission graded - basic sync complete', [
            'grade_id' => $grade->id,
            'grade' => $btec_grade,
            'attempt' => $attemptnumber + 1
        ]);
        
    } catch (\Exception $e) {
        file_put_contents($logfile, "[" . date('Y-m-d H:i:s') . "] ❌ ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        self::log_error('Observer error: ' . $e->getMessage());
    }
}

/**
 * Quick BTEC conversion (no DB joins)
 */
private static function quick_btec_conversion($grade) {
    $rawgrade = $grade->grade;
    
    if (is_null($rawgrade)) {
        return "R";  // Refer
    } elseif ($rawgrade >= 4) {
        return "D";  // Distinction
    } elseif ($rawgrade >= 3) {
        return "M";  // Merit
    } elseif ($rawgrade >= 2) {
        return "P";  // Pass
    } else {
        return "R";  // Refer
    }
}

/**
 * Quick feedback extraction
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
 * Detect grader role
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
```

---

### **2. Scheduled Task (Complete)**

```php
<?php
namespace local_moodle_zoho_sync\task;

class sync_missing_grades extends \core\task\scheduled_task {
    
    public function get_name() {
        return get_string('task_sync_missing_grades', 'local_moodle_zoho_sync');
    }
    
    public function execute() {
        global $DB;
        
        mtrace('========================================');
        mtrace('🔄 Starting Comprehensive Grade Sync');
        mtrace('========================================');
        
        $stats = [
            'enriched' => 0,
            'rr_updated' => 0,
            'f_created' => 0,
            'errors' => 0
        ];
        
        // ══════════════════════════════════════
        // PART 1: Enrich Pending Grades
        // ══════════════════════════════════════
        
        mtrace('');
        mtrace('📋 Part 1: Enriching pending grades with learning outcomes...');
        
        $pending = $DB->get_records('zoho_grade_queue', [
            'needs_enrichment' => 1,
            'status' => 'BASIC_SENT'
        ], '', '*', 0, 100);  // Process 100 at a time
        
        mtrace('Found ' . count($pending) . ' grades pending enrichment');
        
        foreach ($pending as $queue) {
            try {
                $this->enrich_grade($queue);
                $stats['enriched']++;
            } catch (\Exception $e) {
                mtrace('  ❌ Error enriching grade ' . $queue->grade_id . ': ' . $e->getMessage());
                $this->handle_enrichment_failure($queue, $e->getMessage());
                $stats['errors']++;
            }
        }
        
        // ══════════════════════════════════════
        // PART 2: Check for RR (Double Refer)
        // ══════════════════════════════════════
        
        mtrace('');
        mtrace('🔍 Part 2: Checking for double refer (RR) grades...');
        
        $rr_candidates = $DB->get_records('zoho_grade_queue', [
            'needs_rr_check' => 1
        ]);
        
        mtrace('Found ' . count($rr_candidates) . ' candidates for RR check');
        
        foreach ($rr_candidates as $queue) {
            try {
                if ($this->check_and_update_rr($queue)) {
                    $stats['rr_updated']++;
                }
            } catch (\Exception $e) {
                mtrace('  ❌ Error checking RR for grade ' . $queue->grade_id . ': ' . $e->getMessage());
                $stats['errors']++;
            }
        }
        
        // ══════════════════════════════════════
        // PART 3: Find Missing Submissions (F)
        // ══════════════════════════════════════
        
        mtrace('');
        mtrace('📝 Part 3: Finding missing submissions (F grades)...');
        
        $missing = $this->find_missing_submissions();
        
        mtrace('Found ' . count($missing) . ' missing submissions');
        
        foreach ($missing as $student) {
            try {
                $this->create_f_grade($student);
                $stats['f_created']++;
            } catch (\Exception $e) {
                mtrace('  ❌ Error creating F grade: ' . $e->getMessage());
                $stats['errors']++;
            }
        }
        
        // ══════════════════════════════════════
        // SUMMARY
        // ══════════════════════════════════════
        
        mtrace('');
        mtrace('========================================');
        mtrace('✅ Grade Sync Complete');
        mtrace('========================================');
        mtrace('Enriched: ' . $stats['enriched']);
        mtrace('RR Updated: ' . $stats['rr_updated']);
        mtrace('F Created: ' . $stats['f_created']);
        mtrace('Errors: ' . $stats['errors']);
        mtrace('========================================');
    }
    
    /**
     * Enrich grade with learning outcomes
     */
    private function enrich_grade($queue) {
        global $DB;
        
        $grade = $DB->get_record('assign_grades', ['id' => $queue->grade_id]);
        if (!$grade) {
            throw new \Exception('Grade not found');
        }
        
        mtrace('  → Enriching grade ' . $queue->grade_id . ' for student ' . $queue->student_id);
        
        // Extract learning outcomes (HEAVY)
        $extractor = new \local_moodle_zoho_sync\data_extractor();
        $learning_outcomes = $extractor->extract_btec_learning_outcomes($grade);
        
        // Get attempt history
        $attempt_history = $this->get_attempt_history($queue->student_id, $queue->assignment_id);
        
        // Build enriched data
        $enriched_data = [
            'grade_id' => $queue->grade_id,
            'composite_key' => $queue->composite_key,
            'learning_outcomes' => $learning_outcomes,
            'attempt_history' => $attempt_history,
            'status' => 'ENRICHED'
        ];
        
        // Update Zoho record
        $sender = new \local_moodle_zoho_sync\webhook_sender();
        $response = $sender->send_grade_enrichment($enriched_data, $queue->zoho_record_id);
        
        // Update queue
        $DB->update_record('zoho_grade_queue', [
            'id' => $queue->id,
            'status' => 'ENRICHED',
            'needs_enrichment' => 0,
            'enriched_at' => time()
        ]);
        
        mtrace('  ✅ Enriched successfully');
    }
    
    /**
     * Check if R → RR
     */
    private function check_and_update_rr($queue) {
        global $DB;
        
        $attempts = $DB->get_records('assign_grades', [
            'assignment' => $queue->assignment_id,
            'userid' => $queue->student_id
        ], 'attemptnumber ASC');
        
        if (count($attempts) < 2) {
            return false;  // Not enough attempts yet
        }
        
        // Check if both are Refer
        $all_refer = true;
        foreach ($attempts as $attempt) {
            $btec = $this->calculate_btec_result($attempt->id);
            if ($btec != 'R') {
                $all_refer = false;
                break;
            }
        }
        
        if (!$all_refer) {
            // Student passed on resubmission
            $DB->update_record('zoho_grade_queue', [
                'id' => $queue->id,
                'needs_rr_check' => 0
            ]);
            return false;
        }
        
        // Both are Refer → Update to RR
        mtrace('  → Updating grade ' . $queue->grade_id . ' from R to RR');
        
        $update_data = [
            'grade_id' => $queue->grade_id,
            'composite_key' => $queue->composite_key,
            'grade' => 'RR',
            'status' => 'DOUBLE_REFER'
        ];
        
        $sender = new \local_moodle_zoho_sync\webhook_sender();
        $sender->send_grade_update($update_data, $queue->zoho_record_id);
        
        $DB->update_record('zoho_grade_queue', [
            'id' => $queue->id,
            'status' => 'RR_UPDATED',
            'needs_rr_check' => 0
        ]);
        
        mtrace('  ✅ Updated to RR');
        return true;
    }
    
    /**
     * Find students who didn't submit
     */
    private function find_missing_submissions() {
        global $DB;
        
        $now = time();
        
        // Get assignments past deadline
        $sql = "SELECT a.id, a.name, a.course, a.duedate
                FROM {assign} a
                WHERE (a.duedate > 0 AND a.duedate < :now)
                  AND a.duedate > :weekago
                ORDER BY a.duedate DESC";
        
        $assignments = $DB->get_records_sql($sql, [
            'now' => $now,
            'weekago' => $now - (7 * 24 * 3600)  // Last week only
        ]);
        
        $missing = [];
        
        foreach ($assignments as $assignment) {
            $context = \context_course::instance($assignment->course);
            $enrolled = get_enrolled_users($context, 'mod/assign:submit');
            
            foreach ($enrolled as $student) {
                // Check if submitted
                $submission = $DB->get_record('assign_submission', [
                    'assignment' => $assignment->id,
                    'userid' => $student->id
                ]);
                
                $has_submission = ($submission && $submission->status == 'submitted');
                
                // Check if already graded
                $has_grade = $DB->record_exists('assign_grades', [
                    'assignment' => $assignment->id,
                    'userid' => $student->id
                ]);
                
                if (!$has_submission && !$has_grade) {
                    $missing[] = [
                        'student_id' => $student->id,
                        'student_name' => fullname($student),
                        'student_email' => $student->email,
                        'assignment_id' => $assignment->id,
                        'assignment_name' => $assignment->name,
                        'course_id' => $assignment->course,
                        'deadline' => $assignment->duedate
                    ];
                }
            }
        }
        
        return $missing;
    }
    
    /**
     * Create F grade for missing submission
     */
    private function create_f_grade($student) {
        global $DB;
        
        mtrace('  → Creating F grade for ' . $student['student_name'] . ' - ' . $student['assignment_name']);
        
        $course = $DB->get_record('course', ['id' => $student['course_id']]);
        
        $f_data = [
            'grade_id' => 'F_' . $student['student_id'] . '_' . $student['assignment_id'],
            'student_id' => $student['student_id'],
            'student_name' => $student['student_name'],
            'student_email' => $student['student_email'],
            'assignment_id' => $student['assignment_id'],
            'assignment_name' => $student['assignment_name'],
            'course_id' => $student['course_id'],
            'course_name' => $course->fullname,
            'grade' => 'F',
            'status' => 'NO_SUBMISSION',
            'timestamp' => time(),
            'deadline' => $student['deadline'],
            'reason' => 'No submission before deadline',
            'composite_key' => $student['student_id'] . '_' . $student['course_id'] . '_' . $student['assignment_id']
        ];
        
        $sender = new \local_moodle_zoho_sync\webhook_sender();
        $response = $sender->send_grade_updated($f_data);
        
        // Queue it
        $DB->insert_record('zoho_grade_queue', [
            'grade_id' => 0,  // No Moodle grade exists
            'student_id' => $student['student_id'],
            'assignment_id' => $student['assignment_id'],
            'course_id' => $student['course_id'],
            'zoho_record_id' => $response['zoho_id'] ?? null,
            'composite_key' => $f_data['composite_key'],
            'status' => 'F_CREATED',
            'basic_sent_at' => time(),
            'needs_enrichment' => 0,  // F grades don't need enrichment
            'needs_rr_check' => 0
        ]);
        
        mtrace('  ✅ F grade created');
    }
    
    /**
     * Get attempt history
     */
    private function get_attempt_history($student_id, $assignment_id) {
        global $DB;
        
        $attempts = $DB->get_records('assign_grades', [
            'assignment' => $assignment_id,
            'userid' => $student_id
        ], 'attemptnumber ASC');
        
        $history = [];
        foreach ($attempts as $attempt) {
            $history[] = [
                'attempt_number' => $attempt->attemptnumber + 1,
                'grade' => $this->calculate_btec_result($attempt->id),
                'date' => date('Y-m-d', $attempt->timemodified),
                'timestamp' => $attempt->timemodified
            ];
        }
        
        return $history;
    }
    
    /**
     * Calculate BTEC result from grade
     */
    private function calculate_btec_result($grade_id) {
        global $DB;
        
        // Use same logic as quick_btec_conversion
        $grade = $DB->get_record('assign_grades', ['id' => $grade_id]);
        
        $rawgrade = $grade->grade;
        
        if (is_null($rawgrade)) {
            return "R";
        } elseif ($rawgrade >= 4) {
            return "D";
        } elseif ($rawgrade >= 3) {
            return "M";
        } elseif ($rawgrade >= 2) {
            return "P";
        } else {
            return "R";
        }
    }
    
    /**
     * Handle enrichment failure
     */
    private function handle_enrichment_failure($queue, $error) {
        global $DB;
        
        $retry_count = $queue->retry_count + 1;
        
        $update = [
            'id' => $queue->id,
            'retry_count' => $retry_count,
            'error_message' => $error,
            'failed_at' => time()
        ];
        
        if ($retry_count >= 3) {
            // Max retries reached → alert admin
            $update['status'] = 'FAILED';
            $update['needs_enrichment'] = 0;
            
            $this->alert_admin($queue, $error);
        }
        
        $DB->update_record('zoho_grade_queue', $update);
    }
    
    /**
     * Alert admin about failure
     */
    private function alert_admin($queue, $error) {
        mtrace('  ⚠️ ALERT: Grade ' . $queue->grade_id . ' failed after 3 attempts!');
        mtrace('  Error: ' . $error);
        
        // TODO: Send email to admin or create admin notification
    }
}
```

---

## 🎨 واجهة Admin المقترحة:

### **صفحة جديدة: Grade Sync Monitor**

```
┌─────────────────────────────────────────────────────────────────┐
│ 📊 Grade Sync Monitor                                           │
│                                                                 │
│ 📈 Today's Statistics:                                          │
│ ├─ Basic Syncs: 45                                             │
│ ├─ Enriched: 38                                                │
│ ├─ Pending Enrichment: 7                                       │
│ ├─ RR Updates: 2                                               │
│ ├─ F Grades Created: 5                                         │
│ └─ Errors: 1 ⚠️                                                 │
│                                                                 │
│ ─────────────────────────────────────────────────────────────  │
│                                                                 │
│ 🔄 Queue Status:                                               │
│                                                                 │
│ ┌─────────────┬────────┬──────────┬────────────────────────┐  │
│ │ Status      │ Count  │ Oldest   │ Actions                │  │
│ ├─────────────┼────────┼──────────┼────────────────────────┤  │
│ │ BASIC_SENT  │ 7      │ 2h ago   │ [Run Enrichment Now]   │  │
│ │ ENRICHED    │ 38     │ 1h ago   │ ✅ Complete            │  │
│ │ RR_UPDATED  │ 2      │ 3h ago   │ ✅ Complete            │  │
│ │ FAILED      │ 1      │ 5h ago   │ [Retry] [View Error]   │  │
│ └─────────────┴────────┴──────────┴────────────────────────┘  │
│                                                                 │
│ ─────────────────────────────────────────────────────────────  │
│                                                                 │
│ ⏰ Next Scheduled Task: Tomorrow 03:00 AM                      │
│ [Run Task Now]  [View Full Log]  [Configure Settings]         │
│                                                                 │
│ ─────────────────────────────────────────────────────────────  │
│                                                                 │
│ 📋 Recent Activity:                                            │
│                                                                 │
│ [Feb 9, 14:30] Grade #12345 - Ahmed Mohamed - Merit (M)       │
│   ✅ Basic sync complete (0.3s)                                │
│   📝 Queued for enrichment                                     │
│                                                                 │
│ [Feb 9, 14:15] Grade #12344 - Sara Ali - Refer (R)            │
│   ✅ Basic sync complete (0.4s)                                │
│   🔍 Flagged for RR check                                      │
│                                                                 │
│ [Feb 9, 03:00] Scheduled Task Completed                       │
│   ✅ Enriched 42 grades                                        │
│   ✅ Updated 3 RR grades                                       │
│   ✅ Created 8 F grades                                        │
│   ⏱️ Duration: 2m 15s                                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## ✅ الخطوات التالية:

1. ✅ إنشاء جدول mdl_zoho_grade_queue
2. ✅ تعديل Observer (lightweight version)
3. ✅ إنشاء Scheduled Task (complete version)
4. ✅ إضافة واجهة Admin monitoring
5. ✅ اختبار مع بيانات حقيقية

---

**جاهز للتطبيق؟** 🚀
