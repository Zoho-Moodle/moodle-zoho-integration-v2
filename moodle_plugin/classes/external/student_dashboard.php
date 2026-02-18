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
            $record->nationality = $data['nationality'] ?? '';
            $record->date_of_birth = !empty($data['date_of_birth']) ? strtotime($data['date_of_birth']) : null;
            $record->gender = $data['gender'] ?? '';
            $record->emergency_contact_name = $data['emergency_contact_name'] ?? '';
            $record->emergency_contact_phone = $data['emergency_contact_phone'] ?? '';
            $record->status = $data['status'] ?? 'Active';
            
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
            $record->zoho_student_id = $data['zoho_student_id'] ?? '';
            $record->program = $data['program'] ?? '';
            $record->registration_date = !empty($data['registration_date']) ? strtotime($data['registration_date']) : time();
            $record->status = $data['status'] ?? 'Pending';
            $record->total_fees = $data['total_fees'] ?? 0;
            $record->created_at = time();
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->insert_record('local_mzi_registrations', $record);
            
            self::log_webhook('registration_created', $data['zoho_registration_id'], 'success', null);
            
            return [
                'success' => true,
                'registration_id' => $data['zoho_registration_id'],
                'message' => 'Registration created successfully'
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
            $record->registration_id = $registration->id;
            $record->zoho_payment_id = $data['zoho_payment_id'];
            $record->zoho_registration_id = $data['zoho_registration_id'] ?? '';
            $record->amount = $data['amount'] ?? 0;
            $record->payment_date = !empty($data['payment_date']) ? strtotime($data['payment_date']) : time();
            $record->payment_method = $data['payment_method'] ?? '';
            $record->status = $data['status'] ?? 'Completed';
            $record->created_at = time();
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->insert_record('local_mzi_payments', $record);
            
            self::log_webhook('payment_recorded', $data['zoho_payment_id'], 'success', null);
            
            return [
                'success' => true,
                'payment_id' => $data['zoho_payment_id'],
                'message' => 'Payment recorded successfully'
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
            $record->zoho_class_id = $data['zoho_class_id'];
            $record->class_name = $data['class_name'] ?? '';
            $record->program = $data['program'] ?? '';
            $record->instructor = $data['instructor'] ?? '';
            $record->start_date = !empty($data['start_date']) ? strtotime($data['start_date']) : null;
            $record->end_date = !empty($data['end_date']) ? strtotime($data['end_date']) : null;
            $record->status = $data['status'] ?? 'Scheduled';
            $record->created_at = time();
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->insert_record('local_mzi_classes', $record);
            
            self::log_webhook('class_created', $data['zoho_class_id'], 'success', null);
            
            return [
                'success' => true,
                'class_id' => $data['zoho_class_id'],
                'message' => 'Class created successfully'
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
     * Update enrollment
     */
    public static function update_enrollment($enrollmentdata) {
        global $DB;
        
        $params = self::validate_parameters(self::update_enrollment_parameters(), [
            'enrollmentdata' => $enrollmentdata
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $data = json_decode($params['enrollmentdata'], true);
            if (!$data) {
                throw new \invalid_parameter_exception('Invalid JSON data');
            }
            
            // Get student_id from zoho_student_id
            $student = $DB->get_record('local_mzi_students', ['zoho_student_id' => $data['zoho_student_id']], 'id');
            if (!$student) {
                throw new \invalid_parameter_exception("Student with zoho_student_id {$data['zoho_student_id']} not found");
            }
            
            // Get class_id from zoho_class_id
            $class = $DB->get_record('local_mzi_classes', ['zoho_class_id' => $data['zoho_class_id']], 'id');
            if (!$class) {
                throw new \invalid_parameter_exception("Class with zoho_class_id {$data['zoho_class_id']} not found");
            }
            
            $existing = $DB->get_record('local_mzi_enrollments', ['zoho_enrollment_id' => $data['zoho_enrollment_id']]);
            
            $record = new \stdClass();
            $record->student_id = $student->id;
            $record->class_id = $class->id;
            $record->zoho_enrollment_id = $data['zoho_enrollment_id'];
            $record->zoho_student_id = $data['zoho_student_id'] ?? '';
            $record->zoho_class_id = $data['zoho_class_id'] ?? '';
            $record->enrollment_date = !empty($data['enrollment_date']) ? strtotime($data['enrollment_date']) : time();
            $record->status = $data['status'] ?? 'Active';
            $record->updated_at = time();
            $record->synced_at = time();
            
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('local_mzi_enrollments', $record);
                $action = 'updated';
            } else {
                $record->created_at = time();
                $DB->insert_record('local_mzi_enrollments', $record);
                $action = 'created';
            }
            
            self::log_webhook('enrollment_updated', $data['zoho_enrollment_id'], 'success', null);
            
            return [
                'success' => true,
                'action' => $action,
                'enrollment_id' => $data['zoho_enrollment_id'],
                'message' => "Enrollment {$action} successfully"
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('enrollment_updated', $data['zoho_enrollment_id'] ?? 'unknown', 'error', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Returns description of method result value for update_enrollment
     */
    public static function update_enrollment_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation success status'),
            'action' => new external_value(PARAM_TEXT, 'Action performed'),
            'enrollment_id' => new external_value(PARAM_TEXT, 'Zoho enrollment ID'),
            'message' => new external_value(PARAM_TEXT, 'Result message')
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
            
            // Get class_id from zoho_class_id
            $class = $DB->get_record('local_mzi_classes', ['zoho_class_id' => $data['zoho_class_id']], 'id');
            if (!$class) {
                throw new \invalid_parameter_exception("Class with zoho_class_id {$data['zoho_class_id']} not found");
            }
            
            $record = new \stdClass();
            $record->student_id = $student->id;
            $record->class_id = $class->id;
            $record->zoho_grade_id = $data['zoho_grade_id'];
            $record->zoho_student_id = $data['zoho_student_id'] ?? '';
            $record->zoho_class_id = $data['zoho_class_id'] ?? '';
            $record->unit = $data['unit'] ?? '';
            $record->grade = $data['grade'] ?? '';
            $record->grade_date = !empty($data['grade_date']) ? strtotime($data['grade_date']) : time();
            $record->created_at = time();
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->insert_record('local_mzi_grades', $record);
            
            self::log_webhook('grade_submitted', $data['zoho_grade_id'], 'success', null);
            
            return [
                'success' => true,
                'grade_id' => $data['zoho_grade_id'],
                'message' => 'Grade submitted successfully'
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
            $record->student_id = $student->id;
            $record->zoho_request_id = $data['zoho_request_id'];
            $record->zoho_student_id = $data['zoho_student_id'] ?? '';
            $record->request_type = $data['request_type'] ?? '';
            $record->description = $data['description'] ?? '';
            $record->status = $data['status'] ?? 'Pending';
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
            $log->event_type = $event_type;
            $log->zoho_id = $zoho_id;
            $log->status = $status;
            $log->error_message = $error_message;
            $log->created_at = time();
            
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
            $record->status = 'Cancelled';
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
            $record->status = 'Voided';
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
            $record->status = 'Cancelled';
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
     * Soft delete enrollment (mark as Withdrawn)
     */
    public static function delete_enrollment($zoho_enrollment_id) {
        global $DB;
        
        $params = self::validate_parameters(self::delete_enrollment_parameters(), [
            'zoho_enrollment_id' => $zoho_enrollment_id
        ]);
        
        $context = context_system::instance();
        require_capability('moodle/site:config', $context);
        
        try {
            $existing = $DB->get_record('local_mzi_enrollments', ['zoho_enrollment_id' => $params['zoho_enrollment_id']]);
            
            if (!$existing) {
                throw new \invalid_parameter_exception("Enrollment with zoho_enrollment_id {$params['zoho_enrollment_id']} not found");
            }
            
            $record = new \stdClass();
            $record->id = $existing->id;
            $record->status = 'Withdrawn';
            $record->updated_at = time();
            $record->synced_at = time();
            
            $DB->update_record('local_mzi_enrollments', $record);
            
            self::log_webhook('enrollment_deleted', $params['zoho_enrollment_id'], 'success', null);
            
            return [
                'success' => true,
                'enrollment_id' => $params['zoho_enrollment_id'],
                'message' => 'Enrollment marked as withdrawn'
            ];
            
        } catch (\Exception $e) {
            self::log_webhook('enrollment_deleted', $params['zoho_enrollment_id'] ?? 'unknown', 'error', $e->getMessage());
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
            $record->status = 'Cancelled';
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
}
