<?php
/**
 * Student Dashboard External API
 * 
 * @package    local_moodle_zoho_sync
 * @copyright  2025 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

/**
 * Student Dashboard External Functions
 */
class student_dashboard extends external_api {

    /**
     * Parse a monetary value from Zoho — strips currency symbols, commas, spaces.
     * e.g. '4400$' → 4400.0 | '1,500.50 USD' → 1500.5 | '' → 0.0
     */
    private static function parse_money($value): float {
        if ($value === null || $value === '') {
            return 0.0;
        }
        // Remove everything except digits, dot, minus
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string)$value);
        return $cleaned !== '' ? (float)$cleaned : 0.0;
    }
    
    /**
     * Returns description of method parameters for update_student
     * @return external_function_parameters
     */
    public static function update_student_parameters() {
        return new external_function_parameters([
            'studentdata' => new external_value(PARAM_RAW, 'JSON string of student data from Zoho')
        ]);
    }
    
    /**
     * Update or create student record
     * @param string $studentdata JSON string from Zoho webhook
     * @return array Result with success status
     */
    public static function update_student($studentdata) {
        global $DB;
        
        // Validate parameters
        $params = self::validate_parameters(self::update_student_parameters(), [
            'studentdata' => $studentdata
        ]);
        
        // Check capability
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            // Parse JSON data
            $data = json_decode($params['studentdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }
            
            // Check if student exists by zoho_student_id
            $existing = $DB->get_record('local_mzi_students', ['zoho_student_id' => $data['zoho_student_id']]);
            
            $record = new \stdClass();
            $record->zoho_student_id = $data['zoho_student_id'];
            $record->student_id = $data['student_id'] ?? '';  // Text Student ID (A01B3660C)
            $record->first_name = $data['first_name'] ?? '';
            $record->last_name = $data['last_name'] ?? '';
            $record->email = $data['email'] ?? '';
            $record->phone_number = $data['phone_number'] ?? '';
            $record->address = $data['address'] ?? '';
            $record->city = $data['city'] ?? '';
            $record->nationality = $data['nationality'] ?? '';
            $record->national_id = $data['national_id'] ?? '';  // National_Number from Zoho
            $record->date_of_birth = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;  // char column — keep as string
            $record->gender = $data['gender'] ?? '';
            $record->emergency_contact_name = $data['emergency_contact_name'] ?? '';
            $record->emergency_contact_phone = $data['emergency_contact_phone'] ?? '';
            $record->status = $data['status'] ?? 'Active';
            $record->academic_email = $data['academic_email'] ?? '';
            $record->major = $data['major'] ?? '';
            $record->sub_major = $data['sub_major'] ?? '';
            
            // Validate moodle_user_id exists before using it
            if (!empty($data['moodle_user_id'])) {
                $user_exists = $DB->record_exists('user', ['id' => $data['moodle_user_id']]);
                $record->moodle_user_id = $user_exists ? $data['moodle_user_id'] : null;
            } else {
                $record->moodle_user_id = null;
            }
            
            $record->updated_at = time();
            $record->synced_at = time();
            
            // Handle photo upload if provided
            $photo_saved = false;
            if (!empty($data['photo_data']) && !empty($data['photo_filename'])) {
                try {
                    global $CFG;
                    
                    // Create directory if not exists
                    $photodir = $CFG->dataroot . '/student_photos';
                    if (!is_dir($photodir)) {
                        mkdir($photodir, 0755, true);
                    }
                    
                    // Decode and save photo
                    $photo_binary = base64_decode($data['photo_data']);
                    $filepath = $photodir . '/' . $data['photo_filename'];
                    
                    if (file_put_contents($filepath, $photo_binary)) {
                        $record->photo_url = '/student_photos/' . $data['photo_filename'];
                        $photo_saved = true;
                    }
                } catch (\Exception $e) {
                    // Log but don't fail the whole operation
                    error_log("Failed to save student photo: " . $e->getMessage());
                }
            } elseif (!empty($data['photo_url'])) {
                // Use provided photo URL
                $record->photo_url = $data['photo_url'];
            }
            
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_students', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_students', $record);
                $action = 'created';
            }
            
            // Log webhook
            self::log_webhook('student_updated', $data['zoho_student_id'], 'success', null);
            
            $message = "Student {$action} successfully";
            if ($photo_saved) {
                $message .= " (photo saved)";
            }
            
            return [
                'success' => true,
                'action' => $action,
                'student_id' => $data['zoho_student_id'],
                'message' => $message
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('student_updated', $data['zoho_student_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for update_student
     * @return external_single_structure
     */
    public static function update_student_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'action' => new external_value(PARAM_TEXT, 'Action performed (created/updated)'),
            'student_id' => new external_value(PARAM_TEXT, 'Zoho student ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for create_registration
     */
    public static function create_registration_parameters() {
        return new external_function_parameters([
            'registrationdata' => new external_value(PARAM_RAW, 'JSON string of registration data from Zoho')
        ]);
    }
    
    /**
     * Create new registration record
     */
    public static function create_registration($registrationdata) {
        global $DB;
        
        $params = self::validate_parameters(self::create_registration_parameters(), [
            'registrationdata' => $registrationdata
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $data = json_decode($params['registrationdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }
            
            // Get student_id from zoho_student_id
            $student = $DB->get_record('local_mzi_students', ['zoho_student_id' => $data['zoho_student_id']], 'id');
            if (!$student) {
                throw new \invalid_parameter_exception("Student with zoho_student_id {$data['zoho_student_id']} not found");
            }
            
            $record = new \stdClass();
            $record->student_id = $student->id;
            $record->zoho_registration_id = $data['zoho_registration_id'];
            $record->zoho_student_id      = $data['zoho_student_id'] ?? '';
            $record->registration_number  = $data['registration_number'] ?? '';
            $record->program_name         = $data['program_name'] ?? $data['program'] ?? '';
            $record->program_level        = $data['program_level'] ?? '';
            $record->registration_date    = $data['registration_date'] ?? '';
            $record->expected_graduation  = $data['expected_graduation'] ?? '';
            $record->registration_status  = $data['registration_status'] ?? $data['status'] ?? 'Pending';
            $record->total_fees           = self::parse_money($data['total_fees'] ?? $data['program_price'] ?? 0);
            $record->paid_amount          = self::parse_money($data['paid_amount'] ?? 0);
            $record->remaining_amount     = self::parse_money($data['remaining_amount'] ?? 0);
            $record->currency             = $data['currency'] ?? '';
            $record->payment_plan         = $data['payment_plan'] ?? '';
            $record->study_mode           = $data['study_mode'] ?? '';
            $record->number_of_installments = isset($data['number_of_installments']) ? (int)$data['number_of_installments'] : null;
            $record->updated_at           = time();
            $record->synced_at            = time();

            // UPSERT: update if exists, insert if new
            $existing = $DB->get_record('local_mzi_registrations',
                ['zoho_registration_id' => $data['zoho_registration_id']], 'id');
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_registrations', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_registrations', $record);
                $action = 'created';
            }

            self::log_webhook('registration_' . $action, $data['zoho_registration_id'], 'success', null);

            return [
                'success' => true,
                'registration_id' => $data['zoho_registration_id'],
                'message' => 'Registration ' . $action . ' successfully'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('registration_created', $data['zoho_registration_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for create_registration
     */
    public static function create_registration_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'registration_id' => new external_value(PARAM_TEXT, 'Zoho registration ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for record_payment
     */
    public static function record_payment_parameters() {
        return new external_function_parameters([
            'paymentdata' => new external_value(PARAM_RAW, 'JSON string of payment data from Zoho')
        ]);
    }
    
    /**
     * Record payment
     */
    public static function record_payment($paymentdata) {
        global $DB;
        
        $params = self::validate_parameters(self::record_payment_parameters(), [
            'paymentdata' => $paymentdata
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $data = json_decode($params['paymentdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }
            
            // Get registration_id from zoho_registration_id
            $registration = $DB->get_record('local_mzi_registrations', ['zoho_registration_id' => $data['zoho_registration_id']], 'id');
            if (!$registration) {
                throw new \invalid_parameter_exception("Registration with zoho_registration_id {$data['zoho_registration_id']} not found");
            }
            
            $record = new \stdClass();
            $record->registration_id     = $registration->id;
            $record->zoho_payment_id     = $data['zoho_payment_id'];
            $record->zoho_registration_id= $data['zoho_registration_id'] ?? '';
            $record->payment_number      = $data['payment_number'] ?? '';
            $record->payment_amount      = self::parse_money($data['payment_amount'] ?? $data['amount'] ?? 0);
            $record->payment_date        = $data['payment_date'] ?? '';
            $record->payment_method      = $data['payment_method'] ?? '';
            $record->payment_status      = $data['payment_status'] ?? $data['status'] ?? 'Confirmed';
            $record->voucher_number      = $data['voucher_number'] ?? '';
            $record->receipt_number      = $data['receipt_number'] ?? '';
            $record->bank_name           = $data['bank_name'] ?? '';
            $record->payment_notes       = $data['payment_notes'] ?? $data['notes'] ?? '';
            $record->updated_at          = time();
            $record->synced_at           = time();

            // UPSERT
            $existing = $DB->get_record('local_mzi_payments',
                ['zoho_payment_id' => $data['zoho_payment_id']], '*');
            if ($existing) {
                $record->id = $existing->id;
                // Preserve existing payment_date if the incoming sync has no date
                // (Zoho sometimes omits Payment_Date on status-only updates)
                if (empty($record->payment_date) && !empty($existing->payment_date)) {
                    $record->payment_date = $existing->payment_date;
                }
                $DB->update_record('local_mzi_payments', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_payments', $record);
                $action = 'created';
            }

            self::log_webhook('payment_' . $action, $data['zoho_payment_id'], 'success', null);

            return [
                'success' => true,
                'payment_id' => $data['zoho_payment_id'],
                'message' => 'Payment ' . $action . ' successfully'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('payment_recorded', $data['zoho_payment_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for record_payment
     */
    public static function record_payment_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'payment_id' => new external_value(PARAM_TEXT, 'Zoho payment ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }


    // =========================================================================
    // INSTALLMENTS
    // =========================================================================

    /**
     * Returns description of method parameters for sync_installments.
     */
    public static function sync_installments_parameters() {
        return new external_function_parameters([
            'zoho_registration_id' => new external_value(PARAM_TEXT, 'Zoho registration ID'),
            'installmentsdata'     => new external_value(PARAM_RAW,  'JSON array of installment objects'),
        ]);
    }

    /**
     * Replace all installments for a registration.
     * Deletes existing rows then inserts the provided list.
     * Idempotent — safe to call on every registration_created/updated event.
     */
    public static function sync_installments($zoho_registration_id, $installmentsdata) {
        global $DB;

        $params = self::validate_parameters(self::sync_installments_parameters(), [
            'zoho_registration_id' => $zoho_registration_id,
            'installmentsdata'     => $installmentsdata,
        ]);

        $context = context_system::instance();
        require_capability('moodle/site:config', $context);

        try {
            $items = json_decode($params['installmentsdata'], true);
            if (!is_array($items)) {
                throw new \invalid_parameter_exception('installmentsdata must be a JSON array');
            }

            $registration = $DB->get_record('local_mzi_registrations',
                ['zoho_registration_id' => $params['zoho_registration_id']], 'id');
            if (!$registration) {
                throw new \invalid_parameter_exception(
                    "Registration not found: {$params['zoho_registration_id']}"
                );
            }

            // Replace existing installments for this registration
            $DB->delete_records('local_mzi_installments', ['registration_id' => $registration->id]);

            $now = time();
            foreach ($items as $item) {
                $row = new \stdClass();
                $row->registration_id     = $registration->id;
                $row->installment_number  = isset($item['installment_number']) ? (int)$item['installment_number'] : 0;
                $row->due_date            = $item['due_date']   ?? '';
                $row->amount              = self::parse_money($item['amount']   ?? 0);
                $row->status              = $item['status']     ?? 'Pending';
                $row->paid_date           = $item['paid_date']  ?? null;
                $row->created_at          = $now;
                $row->updated_at          = $now;
                $DB->insert_record('local_mzi_installments', $row);
            }

            self::log_webhook('installments_synced', $params['zoho_registration_id'], 'success', null);

            return [
                'success' => true,
                'count'   => count($items),
                'message' => 'Synced ' . count($items) . ' installments for ' . $params['zoho_registration_id'],
            ];

        } catch (\Exception $e) {
            self::log_webhook('installments_synced', $zoho_registration_id, 'error', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Returns description of method result value for sync_installments.
     */
    public static function sync_installments_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'count'   => new external_value(PARAM_INT,  'Number of installments synced'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }


    // =========================================================================
    // BTEC TEACHERS
    // =========================================================================

    /**
     * Returns description of method parameters for sync_teacher.
     */
    public static function sync_teacher_parameters() {
        return new external_function_parameters([
            'teacherdata' => new external_value(PARAM_RAW, 'JSON string of teacher data from Zoho BTEC_Teachers module')
        ]);
    }

    /**
     * Create or update a teacher record from Zoho BTEC_Teachers.
     * Resolves the Moodle user account by matching academic_email (fallback: email).
     */
    public static function sync_teacher($teacherdata) {
        global $DB;

        $params = self::validate_parameters(self::sync_teacher_parameters(), [
            'teacherdata' => $teacherdata
        ]);

        $context = context_system::instance();
        require_capability('moodle/site:config', $context);

        try {
            $data = json_decode($params['teacherdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }

            // Resolve Moodle user: try academic_email first, then email.
            $moodle_user_id = null;
            $lookup_email   = $data['academic_email'] ?? ($data['email'] ?? '');
            if ($lookup_email) {
                $user = $DB->get_record('user', ['email' => $lookup_email, 'deleted' => 0], 'id', IGNORE_MISSING);
                if ($user) {
                    $moodle_user_id = (int)$user->id;
                }
            }

            $record = new \stdClass();
            $record->zoho_teacher_id    = $data['zoho_teacher_id'];
            $record->moodle_user_id     = $moodle_user_id;
            $record->teacher_name       = $data['teacher_name']    ?? '';
            $record->email              = $data['email']           ?? '';
            $record->academic_email     = $data['academic_email']  ?? '';
            $record->phone_number       = $data['phone_number']    ?? '';
            $record->updated_at         = time();
            $record->synced_at          = time();
            $record->zoho_modified_time = $data['zoho_modified_time'] ?? '';

            $existing = $DB->get_record('local_mzi_teachers',
                ['zoho_teacher_id' => $data['zoho_teacher_id']], 'id');
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_teachers', $record);
                $action = 'updated';
            } else {
                $record->created_at        = time();
                $record->zoho_created_time = $data['zoho_created_time'] ?? '';
                $DB->insert_record('local_mzi_teachers', $record);
                $action = 'created';
            }

            self::log_webhook('teacher_' . $action, $data['zoho_teacher_id'], 'success', null);

            return [
                'success'        => true,
                'teacher_id'     => $data['zoho_teacher_id'],
                'moodle_user_id' => $moodle_user_id ?? 0,
                'message'        => 'Teacher ' . $action . ' successfully'
                    . ($moodle_user_id ? " (linked to Moodle user {$moodle_user_id})" : ' (no Moodle account found)'),
            ];

        } catch (\Exception $e) {
            self::log_webhook('teacher_sync', $data['zoho_teacher_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Returns description of sync_teacher result.
     */
    public static function sync_teacher_returns() {
        return new external_single_structure([
            'success'        => new external_value(PARAM_BOOL, 'Operation success status'),
            'teacher_id'     => new external_value(PARAM_TEXT, 'Zoho teacher ID'),
            'moodle_user_id' => new external_value(PARAM_INT,  'Resolved Moodle user ID (0 if not found)'),
            'message'        => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }


    /**
     * Returns description of method parameters for create_class
     */
    public static function create_class_parameters() {
        return new external_function_parameters([
            'classdata' => new external_value(PARAM_RAW, 'JSON string of class data from Zoho')
        ]);
    }

    /**
     * Create class
     */
    public static function create_class($classdata) {
        global $DB;
        
        $params = self::validate_parameters(self::create_class_parameters(), [
            'classdata' => $classdata
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $data = json_decode($params['classdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }
            
            $record = new \stdClass();
            $record->zoho_class_id    = $data['zoho_class_id'];
            $record->class_name       = $data['class_name'] ?? '';
            $record->class_short_name = $data['class_short_name'] ?? '';
            $record->program_level    = $data['program_level'] ?? $data['program'] ?? '';
            $record->teacher_name     = $data['teacher_name'] ?? $data['instructor'] ?? '';
            $record->teacher_zoho_id  = $data['teacher_zoho_id'] ?? '';
            $record->unit_name        = $data['unit_name'] ?? $data['unit'] ?? '';
            $record->unit_zoho_id     = $data['unit_zoho_id'] ?? '';
            $record->program_zoho_id  = $data['program_zoho_id'] ?? '';
            $record->start_date       = $data['start_date'] ?? '';
            $record->end_date         = $data['end_date'] ?? '';
            $record->class_status     = $data['class_status'] ?? $data['status'] ?? 'Scheduled';
            $record->moodle_class_id  = $data['moodle_class_id'] ?? null;
            $record->updated_at       = time();
            $record->synced_at        = time();

            // UPSERT
            $existing = $DB->get_record('local_mzi_classes',
                ['zoho_class_id' => $data['zoho_class_id']], 'id');
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_classes', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_classes', $record);
                $action = 'created';
            }

            self::log_webhook('class_' . $action, $data['zoho_class_id'], 'success', null);

            return [
                'success' => true,
                'class_id' => $data['zoho_class_id'],
                'message'  => 'Class ' . $action . ' successfully'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('class_created', $data['zoho_class_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for create_class
     */
    public static function create_class_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'class_id' => new external_value(PARAM_TEXT, 'Zoho class ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for update_enrollment
     */
    public static function update_enrollment_parameters() {
        return new external_function_parameters([
            'enrollmentdata' => new external_value(PARAM_RAW, 'JSON string of enrollment data from Zoho')
        ]);
    }
    
    /**
     * Update enrollment — upserts local_mzi_enrollments AND enrols the student in the
     * Moodle course using the internal enrol_get_plugin('manual') API.
     * No external enrol_manual_enrol_users WS call is needed.
     *
     * moodle_user_id resolution order (never trusts Zoho Student_Moodle_ID directly):
     *   1. local_mzi_students.moodle_user_id  (already cached)
     *   2. mdl_user.email = local_mzi_students.academic_email
     *   3. mdl_user.username = lower(local_mzi_students.student_id)
     * When resolved via fallback the value is written back so next call is instant.
     *
     * moodle_course_id resolution order:
     *   1. moodle_course_id field in passed JSON data  (set by backend when known)
     *   2. local_mzi_classes.moodle_class_id  where zoho_class_id = ?
     */
    public static function update_enrollment($enrollmentdata) {
        global $DB;

        $params = self::validate_parameters(self::update_enrollment_parameters(), [
            'enrollmentdata' => $enrollmentdata,
        ]);

        $context = context_system::instance();
        require_capability('moodle/site:config', $context);

        try {
            $data = json_decode($params['enrollmentdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }

            // ---- Resolve student (required) ----------------------------------------
            $student = $DB->get_record(
                'local_mzi_students',
                ['zoho_student_id' => $data['zoho_student_id']],
                'id, moodle_user_id, academic_email, student_id'
            );
            if (!$student) {
                throw new \invalid_parameter_exception(
                    "Student with zoho_student_id {$data['zoho_student_id']} not found"
                );
            }

            // ---- Resolve class (non-fatal — may not exist yet in class_created path) --
            $class = $DB->get_record(
                'local_mzi_classes',
                ['zoho_class_id' => $data['zoho_class_id']],
                'id, moodle_class_id',
                IGNORE_MISSING
            );

            // ---- Build DB record ---------------------------------------------------
            $existing = $DB->get_record(
                'local_mzi_enrollments',
                ['zoho_enrollment_id' => $data['zoho_enrollment_id']]
            );

            $record             = new \stdClass();
            $record->student_id = $student->id;
            $record->class_id   = $class ? (int)$class->id : 0; // 0 if class not synced yet
            $record->zoho_enrollment_id = $data['zoho_enrollment_id'];
            $record->zoho_student_id    = $data['zoho_student_id']   ?? '';
            $record->zoho_class_id      = $data['zoho_class_id']     ?? '';
            $record->enrollment_date    = $data['enrollment_date']   ?? '';
            $record->end_date           = $data['end_date']          ?? '';
            $record->enrollment_type    = $data['enrollment_type']   ?? '';
            $record->enrollment_status  = $data['enrollment_status'] ?? $data['status'] ?? 'Active';
            $record->student_name       = $data['student_name']      ?? '';
            $record->class_name         = $data['class_name']        ?? '';
            $record->enrolled_program   = $data['enrolled_program']  ?? '';
            $record->moodle_course_id   = $data['moodle_course_id']  ?? '';
            $record->synced_to_moodle   = isset($data['synced_to_moodle']) ? (int)$data['synced_to_moodle'] : 0;
            $record->updated_at         = time();
            $record->synced_at          = time();

            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_enrollments', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_enrollments', $record);
                $action = 'created';
            }

            // ---- Resolve moodle_user_id with fallbacks -----------------------------
            $moodle_user_id = (int)($student->moodle_user_id ?? 0);

            if (!$moodle_user_id && !empty($student->academic_email)) {
                $u = $DB->get_record(
                    'user',
                    ['email' => $student->academic_email, 'deleted' => 0],
                    'id',
                    IGNORE_MISSING
                );
                if ($u) {
                    $moodle_user_id = (int)$u->id;
                    $DB->set_field('local_mzi_students', 'moodle_user_id',
                                   $moodle_user_id, ['id' => $student->id]);
                }
            }

            if (!$moodle_user_id && !empty($student->student_id)) {
                $u = $DB->get_record(
                    'user',
                    ['username' => strtolower($student->student_id), 'deleted' => 0],
                    'id',
                    IGNORE_MISSING
                );
                if ($u) {
                    $moodle_user_id = (int)$u->id;
                    $DB->set_field('local_mzi_students', 'moodle_user_id',
                                   $moodle_user_id, ['id' => $student->id]);
                }
            }

            // ---- Resolve moodle_course_id ------------------------------------------
            $moodle_course_id = 0;
            if (!empty($data['moodle_course_id'])) {
                $moodle_course_id = (int)$data['moodle_course_id']; // passed by backend
            } elseif ($class && !empty($class->moodle_class_id)) {
                $moodle_course_id = (int)$class->moodle_class_id;   // from DB
            }

            // ---- Enrol student in Moodle course ------------------------------------
            $enrol_status = 'skipped';
            if ($moodle_user_id && $moodle_course_id) {
                $enrolplugin = enrol_get_plugin('manual');
                if ($enrolplugin) {
                    $instance = $DB->get_record(
                        'enrol',
                        ['courseid' => $moodle_course_id, 'enrol' => 'manual'],
                        '*',
                        IGNORE_MISSING
                    );
                    if (!$instance) {
                        // Create a manual enrolment instance if one doesn't exist yet
                        $course = $DB->get_record(
                            'course', ['id' => $moodle_course_id], '*', IGNORE_MISSING
                        );
                        if ($course) {
                            $newid    = $enrolplugin->add_instance($course);
                            $instance = $DB->get_record(
                                'enrol', ['id' => $newid], '*', IGNORE_MISSING
                            );
                        }
                    }
                    if ($instance) {
                        $enrolplugin->enrol_user($instance, $moodle_user_id, 5); // 5 = student
                        $enrol_status = 'enrolled';
                        // Mark as synced
                        $DB->set_field('local_mzi_enrollments', 'synced_to_moodle', 1,
                                       ['zoho_enrollment_id' => $data['zoho_enrollment_id']]);
                        $DB->set_field('local_mzi_enrollments', 'moodle_course_id',
                                       (string)$moodle_course_id,
                                       ['zoho_enrollment_id' => $data['zoho_enrollment_id']]);
                    } else {
                        $enrol_status = 'no_instance';
                    }
                }
            } elseif (!$moodle_user_id) {
                $enrol_status = 'user_not_found';
            } elseif (!$moodle_course_id) {
                $enrol_status = 'course_not_found';
            }

            self::log_webhook('enrollment_updated', $data['zoho_enrollment_id'], 'success', null);

            return [
                'success'        => true,
                'action'         => $action,
                'enrollment_id'  => $data['zoho_enrollment_id'],
                'enrol_status'   => $enrol_status,
                'moodle_user_id' => $moodle_user_id,
                'message'        => "Enrollment {$action}; enrol={$enrol_status}",
            ];

        } catch (\Exception $e) {
            self::log_webhook('enrollment_updated', $data['zoho_enrollment_id'] ?? 'unknown',
                              'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for update_enrollment
     */
    public static function update_enrollment_returns() {
        return new external_single_structure([
            'success'        => new external_value(PARAM_BOOL, 'Operation success status'),
            'action'         => new external_value(PARAM_TEXT, 'DB action: created or updated'),
            'enrollment_id'  => new external_value(PARAM_TEXT, 'Zoho enrollment ID'),
            'enrol_status'   => new external_value(PARAM_TEXT,
                'Moodle enrolment result: enrolled | skipped | user_not_found | course_not_found | no_instance'),
            'moodle_user_id' => new external_value(PARAM_INT,
                'Resolved Moodle user ID (0 if not found)'),
            'message'        => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }
    
    
    /**
     * Returns description of method parameters for submit_grade
     */
    public static function submit_grade_parameters() {
        return new external_function_parameters([
            'gradedata' => new external_value(PARAM_RAW, 'JSON string of grade data from Zoho')
        ]);
    }
    
    /**
     * Submit grade
     */
    public static function submit_grade($gradedata) {
        global $DB;
        
        $params = self::validate_parameters(self::submit_grade_parameters(), [
            'gradedata' => $gradedata
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $data = json_decode($params['gradedata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }
            
            // Get student_id from zoho_student_id
            $student = $DB->get_record('local_mzi_students', ['zoho_student_id' => $data['zoho_student_id']], 'id');
            if (!$student) {
                throw new \invalid_parameter_exception("Student with zoho_student_id {$data['zoho_student_id']} not found");
            }
            
            // Get class_id from zoho_class_id (optional — may not be synced yet, store null)
            $class_id = null;
            if (!empty($data['zoho_class_id'])) {
                $class = $DB->get_record('local_mzi_classes', ['zoho_class_id' => $data['zoho_class_id']], 'id');
                $class_id = $class ? $class->id : null;
                if (!$class) {
                    debugging("submit_grade: zoho_class_id={$data['zoho_class_id']} not in local_mzi_classes yet — storing grade without class FK.", DEBUG_DEVELOPER);
                }
            }

            $record = new \stdClass();
            $record->student_id      = $student->id;
            $record->class_id        = $class_id;
            $record->zoho_grade_id   = $data['zoho_grade_id'];
            $record->zoho_student_id = $data['zoho_student_id'] ?? '';
            $record->zoho_class_id   = $data['zoho_class_id'] ?? '';
            $record->unit_name       = $data['unit_name'] ?? '';
            $record->assignment_name = $data['assignment_name'] ?? '';
            $record->btec_grade_name = $data['btec_grade_name'] ?? '';
            $record->numeric_grade   = isset($data['numeric_grade']) ? (float)$data['numeric_grade'] : null;
            $record->attempt_number  = isset($data['attempt_number']) ? (int)$data['attempt_number'] : null;
            $record->feedback          = $data['feedback'] ?? '';
            $record->learning_outcomes = $data['learning_outcomes'] ?? null;
            $record->grade_status      = $data['grade_status'] ?? '';
            $record->grade_date        = $data['grade_date'] ?? '';
            $record->updated_at      = time();
            $record->synced_at       = time();

            // UPSERT
            $existing = $DB->get_record('local_mzi_grades',
                ['zoho_grade_id' => $data['zoho_grade_id']], 'id');
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_grades', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_grades', $record);
                $action = 'created';
            }

            self::log_webhook('grade_' . $action, $data['zoho_grade_id'], 'success', null);

            return [
                'success' => true,
                'grade_id' => $data['zoho_grade_id'],
                'message'  => 'Grade ' . $action . ' successfully'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('grade_submitted', $data['zoho_grade_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for submit_grade
     */
    public static function submit_grade_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'grade_id' => new external_value(PARAM_TEXT, 'Zoho grade ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for update_request_status
     */
    public static function update_request_status_parameters() {
        return new external_function_parameters([
            'requestdata' => new external_value(PARAM_RAW, 'JSON string of request data from Zoho')
        ]);
    }
    
    /**
     * Update request status
     */
    public static function update_request_status($requestdata) {
        global $DB;
        
        $params = self::validate_parameters(self::update_request_status_parameters(), [
            'requestdata' => $requestdata
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $data = json_decode($params['requestdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }
            
            // Get student_id from zoho_student_id
            $student = $DB->get_record('local_mzi_students', ['zoho_student_id' => $data['zoho_student_id']], 'id');
            if (!$student) {
                throw new \invalid_parameter_exception("Student with zoho_student_id {$data['zoho_student_id']} not found");
            }
            
            $existing = $DB->get_record('local_mzi_requests', ['zoho_request_id' => $data['zoho_request_id']]);
            
            $record = new \stdClass();
            $record->student_id      = $student->id;
            $record->zoho_request_id = $data['zoho_request_id'];
            $record->request_type    = $data['request_type'] ?? $data['Request_Type'] ?? '';
            $record->description = $data['description'] ?? $data['Reason'] ?? '';
            $record->request_status = $data['request_status'] ?? $data['status'] ?? $data['Status'] ?? 'Pending';
            $record->request_date = $data['request_date'] ?? '';
            $record->updated_at = time();
            $record->synced_at = time();
            
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_requests', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_requests', $record);
                $action = 'created';
            }
            
            self::log_webhook('request_status_changed', $data['zoho_request_id'], 'success', null);
            
            return [
                'success' => true,
                'action' => $action,
                'request_id' => $data['zoho_request_id'],
                'message' => "Request {$action} successfully"
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('request_status_changed', $data['zoho_request_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for update_request_status
     */
    public static function update_request_status_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'action' => new external_value(PARAM_TEXT, 'Action performed'),
            'request_id' => new external_value(PARAM_TEXT, 'Zoho request ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Helper function to log webhook events
     * @param string $event_type
     * @param string $zoho_id
     * @param string $status
     * @param string|null $error_message
     */
    private static function log_webhook($event_type, $zoho_id, $status, $error_message) {
        global $DB;
        
        try {
            $log = new \stdClass();
            $log->module         = $event_type;   // e.g. 'payment_created', 'teacher_updated'
            $log->zoho_record_id = $zoho_id;
            $log->operation      = (strpos($event_type, 'delete') !== false) ? 'delete' : 'sync';
            $log->processed      = ($status === 'success') ? 1 : 2;  // 1=success, 2=error
            $log->error_message  = $error_message;
            $log->created_at     = time();
            
            $DB->insert_record('local_mzi_webhook_logs', $log);
        } catch (\Exception $e) {
            // Silent fail for logging
            error_log("Failed to log webhook: " . $e->getMessage());
        }
    }
    
    
    // ==================== DELETE METHODS ====================
    
    /**
     * Returns description of method parameters for delete_student
     */
    public static function delete_student_parameters() {
        return new external_function_parameters([
            'zoho_student_id' => new external_value(PARAM_TEXT, 'Zoho student ID to delete')
        ]);
    }
    
    /**
     * Soft delete student (mark as Deleted)
     */
    public static function delete_student($zoho_student_id) {
        global $DB;
        
        $params = self::validate_parameters(self::delete_student_parameters(), [
            'zoho_student_id' => $zoho_student_id
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $existing = $DB->get_record('local_mzi_students', ['zoho_student_id' => $params['zoho_student_id']]);
            
            if (!$existing) {
                throw new \invalid_parameter_exception("Student with zoho_student_id {$params['zoho_student_id']} not found");
            }
            
            // Soft delete - mark as Deleted
            $record = new \stdClass();
            $record->id = $existing->id;
            $record->status = 'Deleted';
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->update_record('local_mzi_students', $record);
            
            self::log_webhook('student_deleted', $params['zoho_student_id'], 'success', null);
            
            return [
                'success' => true,
                'student_id' => $params['zoho_student_id'],
                'message' => 'Student marked as deleted'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('student_deleted', $params['zoho_student_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for delete_student
     */
    public static function delete_student_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'student_id' => new external_value(PARAM_TEXT, 'Zoho student ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for delete_registration
     */
    public static function delete_registration_parameters() {
        return new external_function_parameters([
            'zoho_registration_id' => new external_value(PARAM_TEXT, 'Zoho registration ID to delete')
        ]);
    }
    
    /**
     * Soft delete registration (mark as Cancelled)
     */
    public static function delete_registration($zoho_registration_id) {
        global $DB;
        
        $params = self::validate_parameters(self::delete_registration_parameters(), [
            'zoho_registration_id' => $zoho_registration_id
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $existing = $DB->get_record('local_mzi_registrations', ['zoho_registration_id' => $params['zoho_registration_id']]);
            
            if (!$existing) {
                throw new \invalid_parameter_exception("Registration with zoho_registration_id {$params['zoho_registration_id']} not found");
            }
            
            $record = new \stdClass();
            $record->id = $existing->id;
            $record->registration_status = 'Cancelled';
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->update_record('local_mzi_registrations', $record);
            
            self::log_webhook('registration_deleted', $params['zoho_registration_id'], 'success', null);
            
            return [
                'success' => true,
                'registration_id' => $params['zoho_registration_id'],
                'message' => 'Registration marked as cancelled'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('registration_deleted', $params['zoho_registration_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for delete_registration
     */
    public static function delete_registration_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'registration_id' => new external_value(PARAM_TEXT, 'Zoho registration ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for delete_payment
     */
    public static function delete_payment_parameters() {
        return new external_function_parameters([
            'zoho_payment_id' => new external_value(PARAM_TEXT, 'Zoho payment ID to delete')
        ]);
    }
    
    /**
     * Soft delete payment (mark as Voided)
     */
    public static function delete_payment($zoho_payment_id) {
        global $DB;
        
        $params = self::validate_parameters(self::delete_payment_parameters(), [
            'zoho_payment_id' => $zoho_payment_id
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $existing = $DB->get_record('local_mzi_payments', ['zoho_payment_id' => $params['zoho_payment_id']]);
            
            if (!$existing) {
                throw new \invalid_parameter_exception("Payment with zoho_payment_id {$params['zoho_payment_id']} not found");
            }
            
            $record = new \stdClass();
            $record->id = $existing->id;
            $record->payment_status = 'Voided';
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->update_record('local_mzi_payments', $record);
            
            self::log_webhook('payment_deleted', $params['zoho_payment_id'], 'success', null);
            
            return [
                'success' => true,
                'payment_id' => $params['zoho_payment_id'],
                'message' => 'Payment marked as voided'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('payment_deleted', $params['zoho_payment_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for delete_payment
     */
    public static function delete_payment_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'payment_id' => new external_value(PARAM_TEXT, 'Zoho payment ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for delete_class
     */
    public static function delete_class_parameters() {
        return new external_function_parameters([
            'zoho_class_id' => new external_value(PARAM_TEXT, 'Zoho class ID to delete')
        ]);
    }
    
    /**
     * Soft delete class (mark as Cancelled)
     */
    public static function delete_class($zoho_class_id) {
        global $DB;
        
        $params = self::validate_parameters(self::delete_class_parameters(), [
            'zoho_class_id' => $zoho_class_id
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $existing = $DB->get_record('local_mzi_classes', ['zoho_class_id' => $params['zoho_class_id']]);
            
            if (!$existing) {
                throw new \invalid_parameter_exception("Class with zoho_class_id {$params['zoho_class_id']} not found");
            }
            
            $record = new \stdClass();
            $record->id = $existing->id;
            $record->class_status = 'Cancelled';
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->update_record('local_mzi_classes', $record);
            
            self::log_webhook('class_deleted', $params['zoho_class_id'], 'success', null);
            
            return [
                'success' => true,
                'class_id' => $params['zoho_class_id'],
                'message' => 'Class marked as cancelled'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('class_deleted', $params['zoho_class_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for delete_class
     */
    public static function delete_class_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'class_id' => new external_value(PARAM_TEXT, 'Zoho class ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for delete_enrollment
     */
    public static function delete_enrollment_parameters() {
        return new external_function_parameters([
            'zoho_enrollment_id' => new external_value(PARAM_TEXT, 'Zoho enrollment ID to delete')
        ]);
    }
    
    /**
     * Soft delete enrollment — marks as Withdrawn in local_mzi_enrollments AND
     * unenrols the student from the Moodle course via enrol_get_plugin('manual').
     * No external enrol_manual_unenrol_users WS call is needed.
     */
    public static function delete_enrollment($zoho_enrollment_id) {
        global $DB;

        $params = self::validate_parameters(self::delete_enrollment_parameters(), [
            'zoho_enrollment_id' => $zoho_enrollment_id,
        ]);

        $context = context_system::instance();
        require_capability('moodle/site:config', $context);

        try {
            $existing = $DB->get_record(
                'local_mzi_enrollments',
                ['zoho_enrollment_id' => $params['zoho_enrollment_id']]
            );

            if (!$existing) {
                throw new \invalid_parameter_exception(
                    "Enrollment with zoho_enrollment_id {$params['zoho_enrollment_id']} not found"
                );
            }

            // ---- Mark as Withdrawn in DB -------------------------------------------
            $record                    = new \stdClass();
            $record->id                = $existing->id;
            $record->enrollment_status = 'Withdrawn';
            $record->synced_to_moodle  = 0;
            $record->updated_at        = time();
            $record->synced_at         = time();
            $DB->update_record('local_mzi_enrollments', $record);

            // ---- Unenrol student from Moodle course --------------------------------
            $unenrol_status = 'skipped';

            // Resolve moodle_user_id
            $moodle_user_id = 0;
            if ($existing->student_id) {
                $student = $DB->get_record(
                    'local_mzi_students',
                    ['id' => $existing->student_id],
                    'id, moodle_user_id, academic_email, student_id',
                    IGNORE_MISSING
                );
                if ($student) {
                    $moodle_user_id = (int)($student->moodle_user_id ?? 0);
                    if (!$moodle_user_id && !empty($student->academic_email)) {
                        $u = $DB->get_record(
                            'user',
                            ['email' => $student->academic_email, 'deleted' => 0],
                            'id', IGNORE_MISSING
                        );
                        if ($u) { $moodle_user_id = (int)$u->id; }
                    }
                    if (!$moodle_user_id && !empty($student->student_id)) {
                        $u = $DB->get_record(
                            'user',
                            ['username' => strtolower($student->student_id), 'deleted' => 0],
                            'id', IGNORE_MISSING
                        );
                        if ($u) { $moodle_user_id = (int)$u->id; }
                    }
                }
            }

            // Resolve moodle_course_id
            $moodle_course_id = 0;
            if (!empty($existing->moodle_course_id)) {
                $moodle_course_id = (int)$existing->moodle_course_id;
            } elseif ($existing->class_id) {
                $cls = $DB->get_record(
                    'local_mzi_classes', ['id' => $existing->class_id],
                    'moodle_class_id', IGNORE_MISSING
                );
                if ($cls && !empty($cls->moodle_class_id)) {
                    $moodle_course_id = (int)$cls->moodle_class_id;
                }
            }

            if ($moodle_user_id && $moodle_course_id) {
                $enrolplugin = enrol_get_plugin('manual');
                if ($enrolplugin) {
                    $instance = $DB->get_record(
                        'enrol',
                        ['courseid' => $moodle_course_id, 'enrol' => 'manual'],
                        '*', IGNORE_MISSING
                    );
                    if ($instance) {
                        $enrolplugin->unenrol_user($instance, $moodle_user_id);
                        $unenrol_status = 'unenrolled';
                    } else {
                        $unenrol_status = 'no_instance';
                    }
                }
            } elseif (!$moodle_user_id) {
                $unenrol_status = 'user_not_found';
            } elseif (!$moodle_course_id) {
                $unenrol_status = 'course_not_found';
            }

            self::log_webhook('enrollment_deleted', $params['zoho_enrollment_id'], 'success', null);

            return [
                'success'       => true,
                'enrollment_id' => $params['zoho_enrollment_id'],
                'message'       => "Enrollment withdrawn; unenrol={$unenrol_status}",
            ];

        } catch (\Exception $e) {
            self::log_webhook('enrollment_deleted', $params['zoho_enrollment_id'] ?? 'unknown',
                              'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for delete_enrollment
     */
    public static function delete_enrollment_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'enrollment_id' => new external_value(PARAM_TEXT, 'Zoho enrollment ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for delete_grade
     */
    public static function delete_grade_parameters() {
        return new external_function_parameters([
            'zoho_grade_id' => new external_value(PARAM_TEXT, 'Zoho grade ID to delete')
        ]);
    }
    
    /**
     * Delete grade record (hard delete)
     */
    public static function delete_grade($zoho_grade_id) {
        global $DB;
        
        $params = self::validate_parameters(self::delete_grade_parameters(), [
            'zoho_grade_id' => $zoho_grade_id
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $existing = $DB->get_record('local_mzi_grades', ['zoho_grade_id' => $params['zoho_grade_id']]);
            
            if (!$existing) {
                throw new \invalid_parameter_exception("Grade with zoho_grade_id {$params['zoho_grade_id']} not found");
            }
            
            // Hard delete for grades
            $DB->delete_records('local_mzi_grades', ['id' => $existing->id]);
            
            self::log_webhook('grade_deleted', $params['zoho_grade_id'], 'success', null);
            
            return [
                'success' => true,
                'grade_id' => $params['zoho_grade_id'],
                'message' => 'Grade deleted successfully'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('grade_deleted', $params['zoho_grade_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for delete_grade
     */
    public static function delete_grade_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'grade_id' => new external_value(PARAM_TEXT, 'Zoho grade ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }
    
    
    /**
     * Returns description of method parameters for delete_request
     */
    public static function delete_request_parameters() {
        return new external_function_parameters([
            'zoho_request_id' => new external_value(PARAM_TEXT, 'Zoho request ID to delete')
        ]);
    }
    
    /**
     * Soft delete request (mark as Cancelled)
     */
    public static function delete_request($zoho_request_id) {
        global $DB;
        
        $params = self::validate_parameters(self::delete_request_parameters(), [
            'zoho_request_id' => $zoho_request_id
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $existing = $DB->get_record('local_mzi_requests', ['zoho_request_id' => $params['zoho_request_id']]);
            
            if (!$existing) {
                throw new \invalid_parameter_exception("Request with zoho_request_id {$params['zoho_request_id']} not found");
            }
            
            $record = new \stdClass();
            $record->id = $existing->id;
            $record->request_status = 'Cancelled';
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->update_record('local_mzi_requests', $record);
            
            self::log_webhook('request_deleted', $params['zoho_request_id'], 'success', null);
            
            return [
                'success' => true,
                'request_id' => $params['zoho_request_id'],
                'message' => 'Request marked as cancelled'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('request_deleted', $params['zoho_request_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for delete_request
     */
    public static function delete_request_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'request_id' => new external_value(PARAM_TEXT, 'Zoho request ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
        ]);
    }

    // -------------------------------------------------------------------------
    // get_moodle_ids — resolve Zoho IDs → Moodle user/course IDs
    // Used by the backend before calling enrol_manual_enrol_users
    // -------------------------------------------------------------------------

    /**
     * Parameters for get_moodle_ids.
     */
    public static function get_moodle_ids_parameters() {
        return new external_function_parameters([
            'zoho_student_id' => new external_value(PARAM_TEXT, 'Zoho student record ID'),
            'zoho_class_id'   => new external_value(PARAM_TEXT, 'Zoho class record ID'),
        ]);
    }

    /**
     * Return moodle_user_id and moodle_course_id for a Zoho student/class pair.
     *
     * Resolution order for moodle_user_id (never uses Student_Moodle_ID from Zoho):
     *   1. local_mzi_students.moodle_user_id  where zoho_student_id = ?
     *   2. mdl_user.id  where email = local_mzi_students.academic_email  (Moodle login email)
     *   3. mdl_user.id  where username = lower(local_mzi_students.student_id)  (student number)
     * When found via fallback, moodle_user_id is written back to local_mzi_students.
     *
     * Resolution for moodle_course_id:
     *   local_mzi_classes.moodle_class_id  where zoho_class_id = ?
     *   (may return '' if local_mzi_classes has not been upserted yet)
     *
     * @param string $zoho_student_id
     * @param string $zoho_class_id
     * @return array
     */
    public static function get_moodle_ids($zoho_student_id, $zoho_class_id) {
        global $DB;

        $params = self::validate_parameters(self::get_moodle_ids_parameters(), [
            'zoho_student_id' => $zoho_student_id,
            'zoho_class_id'   => $zoho_class_id,
        ]);

        $context = context_system::instance();
        require_capability('moodle/site:config', $context);

        // ---- Resolve moodle_user_id ----------------------------------------
        $moodle_user_id = 0;

        $student = $DB->get_record(
            'local_mzi_students',
            ['zoho_student_id' => $params['zoho_student_id']],
            'id, moodle_user_id, academic_email, student_id',
            IGNORE_MISSING
        );

        if ($student) {
            $moodle_user_id = (int)($student->moodle_user_id ?? 0);

            // Fallback 1: resolve via academic_email → mdl_user.email
            if (!$moodle_user_id && !empty($student->academic_email)) {
                $u = $DB->get_record(
                    'user',
                    ['email' => $student->academic_email, 'deleted' => 0],
                    'id',
                    IGNORE_MISSING
                );
                if ($u) {
                    $moodle_user_id = (int)$u->id;
                    // Cache result so next call is immediate
                    $DB->set_field('local_mzi_students', 'moodle_user_id', $moodle_user_id,
                                   ['id' => $student->id]);
                }
            }

            // Fallback 2: resolve via student_id (Zoho Name) → mdl_user.username
            if (!$moodle_user_id && !empty($student->student_id)) {
                $u = $DB->get_record(
                    'user',
                    ['username' => strtolower($student->student_id), 'deleted' => 0],
                    'id',
                    IGNORE_MISSING
                );
                if ($u) {
                    $moodle_user_id = (int)$u->id;
                    $DB->set_field('local_mzi_students', 'moodle_user_id', $moodle_user_id,
                                   ['id' => $student->id]);
                }
            }
        }

        // ---- Resolve moodle_course_id --------------------------------------
        $class = $DB->get_record(
            'local_mzi_classes',
            ['zoho_class_id' => $params['zoho_class_id']],
            'id, moodle_class_id',
            IGNORE_MISSING
        );

        return [
            'moodle_user_id'   => $moodle_user_id,
            'moodle_course_id' => $class ? ($class->moodle_class_id ?? '') : '',
        ];
    }

    /**
     * Returns description of get_moodle_ids result.
     */
    public static function get_moodle_ids_returns() {
        return new external_single_structure([
            'moodle_user_id'   => new external_value(PARAM_INT,  'Moodle user ID (0 = not found)'),
            'moodle_course_id' => new external_value(PARAM_TEXT, 'Moodle course ID (empty = not created yet)'),
        ]);
    }

    // -------------------------------------------------------------------------
    // enrol_users_to_course — enrol an arbitrary list of {userid, roleid} into
    // a Moodle course using enrol_get_plugin('manual') internally.
    // No "enrol_manual_enrol_users" WS permission is required.
    // Used for teacher + default manager enrolment after course creation.
    // -------------------------------------------------------------------------

    public static function enrol_users_to_course_parameters() {
        return new external_function_parameters([
            'courseid'       => new external_value(PARAM_INT, 'Moodle course ID'),
            'enrolmentsdata' => new external_value(PARAM_RAW,
                'JSON array of {"userid": int, "roleid": int} objects'),
        ]);
    }

    /**
     * Enrol a list of users into a course via the internal manual enrol plugin.
     *
     * @param int    $courseid       Moodle course ID
     * @param string $enrolmentsdata JSON: [{"userid":123,"roleid":3}, ...]
     * @return array
     */
    public static function enrol_users_to_course($courseid, $enrolmentsdata) {
        global $DB;

        $params = self::validate_parameters(self::enrol_users_to_course_parameters(), [
            'courseid'       => $courseid,
            'enrolmentsdata' => $enrolmentsdata,
        ]);

        $context = context_system::instance();
        require_capability('moodle/site:config', $context);

        $enrolments = json_decode($params['enrolmentsdata'], true);
        if (!is_array($enrolments)) {
            throw new \invalid_parameter_exception('enrolmentsdata must be a JSON array');
        }

        $enrolplugin = enrol_get_plugin('manual');
        if (!$enrolplugin) {
            return [
                'enrolled' => 0,
                'skipped'  => count($enrolments),
                'message'  => 'manual enrol plugin not available',
            ];
        }

        // Get or create the manual enrolment instance for this course
        $instance = $DB->get_record(
            'enrol',
            ['courseid' => $params['courseid'], 'enrol' => 'manual'],
            '*',
            IGNORE_MISSING
        );
        if (!$instance) {
            $course = $DB->get_record(
                'course', ['id' => $params['courseid']], '*', IGNORE_MISSING
            );
            if (!$course) {
                return [
                    'enrolled' => 0,
                    'skipped'  => count($enrolments),
                    'message'  => 'course not found',
                ];
            }
            $newid    = $enrolplugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $newid], '*', IGNORE_MISSING);
        }

        $enrolled = 0;
        $skipped  = 0;
        foreach ($enrolments as $e) {
            $userid = (int)($e['userid'] ?? 0);
            $roleid = (int)($e['roleid'] ?? 5);
            if (!$userid) {
                $skipped++;
                continue;
            }
            // Verify user exists before enrolling (prevents TypeError crash)
            $userexists = $DB->record_exists('user', ['id' => $userid, 'deleted' => 0]);
            if (!$userexists) {
                $skipped++;
                continue;
            }
            $enrolplugin->enrol_user($instance, $userid, $roleid);
            $enrolled++;
        }

        return [
            'enrolled' => $enrolled,
            'skipped'  => $skipped,
            'message'  => "Enrolled {$enrolled}, skipped {$skipped}",
        ];
    }

    public static function enrol_users_to_course_returns() {
        return new external_single_structure([
            'enrolled' => new external_value(PARAM_INT,  'Number of users enrolled'),
            'skipped'  => new external_value(PARAM_INT,  'Number of users skipped'),
            'message'  => new external_value(PARAM_TEXT, 'Result summary'),
        ]);
    }

    // =========================================================================
    // approve_photo  –  called by backend webhook when Zoho approves / rejects
    //                   a "Photo Update Request"
    // =========================================================================

    /**
     * Parameters for approve_photo
     */
    public static function approve_photo_parameters() {
        return new external_function_parameters([
            'moodle_user_id' => new external_value(PARAM_INT,  'Moodle user ID of the student'),
            'approved'       => new external_value(PARAM_BOOL, 'true = approved, false = rejected'),
        ]);
    }

    /**
     * Approve or reject a pending student photo.
     *
     * Approved: pending file becomes the live photo_url; pending fields cleared.
     * Rejected: pending file deleted; photo_url unchanged; status set to rejected.
     */
    public static function approve_photo($moodle_user_id, $approved) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::approve_photo_parameters(), [
            'moodle_user_id' => $moodle_user_id,
            'approved'       => $approved,
        ]);

        $context = context_system::instance();
        require_capability('moodle/site:config', $context);

        $student = $DB->get_record('local_mzi_students', ['moodle_user_id' => (int)$params['moodle_user_id']]);
        if (!$student) {
            throw new \invalid_parameter_exception("No student found for moodle_user_id={$params['moodle_user_id']}");
        }

        $pending_url = $student->photo_pending_url ?? '';
        if (empty($pending_url)) {
            return [
                'success' => false,
                'action'  => 'no_pending',
                'message' => 'No pending photo found for this student',
            ];
        }

        $photo_dir    = rtrim($CFG->dataroot, '/\\') . DIRECTORY_SEPARATOR . 'student_photos';
        $pending_rel  = ltrim(str_replace('student_photos/', '', $pending_url), '/\\');
        $pending_path = $photo_dir . DIRECTORY_SEPARATOR . $pending_rel;

        if ($params['approved']) {
            // ── Approve: move pending → live ──────────────────────────────────
            // Derive live filename: replace "_pending" suffix with nothing
            $live_rel  = str_replace('_pending.', '.', $pending_rel);
            $live_path = $photo_dir . DIRECTORY_SEPARATOR . $live_rel;
            $live_url  = 'student_photos/' . $live_rel;

            // Remove old live file if it exists
            if (file_exists($live_path)) {
                @unlink($live_path);
            }

            if (file_exists($pending_path)) {
                rename($pending_path, $live_path);
            }

            $record = new \stdClass();
            $record->id                  = $student->id;
            $record->photo_url           = $live_url;
            $record->photo_pending_url   = null;
            $record->photo_pending_status = 'approved';
            $DB->update_record('local_mzi_students', $record);

            self::log_webhook('photo_approved', (string)$params['moodle_user_id'], 'success', null);

            return [
                'success' => true,
                'action'  => 'approved',
                'message' => "Photo approved and set as live for user {$params['moodle_user_id']}",
            ];

        } else {
            // ── Reject: delete pending file, keep old photo_url ───────────────
            if (file_exists($pending_path)) {
                @unlink($pending_path);
            }

            $record = new \stdClass();
            $record->id                   = $student->id;
            $record->photo_pending_url    = null;
            $record->photo_pending_status = 'rejected';
            $DB->update_record('local_mzi_students', $record);

            self::log_webhook('photo_rejected', (string)$params['moodle_user_id'], 'success', null);

            return [
                'success' => true,
                'action'  => 'rejected',
                'message' => "Photo rejected for user {$params['moodle_user_id']}",
            ];
        }
    }

    /**
     * Return values for approve_photo
     */
    public static function approve_photo_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success'),
            'action'  => new external_value(PARAM_TEXT, 'approved / rejected / no_pending'),
            'message' => new external_value(PARAM_TEXT, 'Result message'),
        ]);
    }
}
