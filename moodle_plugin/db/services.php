<?php
/**
 * External functions and service definitions for Moodle-Zoho Integration
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    // BTEC Template Creation
    'local_moodle_zoho_sync_create_btec_definition' => [
        'classname'   => 'local_moodle_zoho_sync\external\create_btec_definition',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Create BTEC grading definition from Zoho template',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'local/moodle_zoho_sync:manage',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],
    
    // Student Dashboard External Functions
    'local_mzi_update_student' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'update_student',
        'classpath'   => '',
        'description' => 'Update or create student record from Zoho CRM',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_create_registration' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'create_registration',
        'classpath'   => '',
        'description' => 'Create new student registration from Zoho CRM',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_record_payment' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'record_payment',
        'classpath'   => '',
        'description' => 'Record student payment from Zoho CRM',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_create_class' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'create_class',
        'classpath'   => '',
        'description' => 'Create new class from Zoho CRM',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_update_enrollment' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'update_enrollment',
        'classpath'   => '',
        'description' => 'Update student enrollment from Zoho CRM',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_submit_grade' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'submit_grade',
        'classpath'   => '',
        'description' => 'Submit student grade from Zoho CRM',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_update_request_status' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'update_request_status',
        'classpath'   => '',
        'description' => 'Update student request status from Zoho CRM',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    // DELETE Functions
    'local_mzi_delete_student' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'delete_student',
        'classpath'   => '',
        'description' => 'Soft delete student record (mark as Deleted)',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_delete_registration' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'delete_registration',
        'classpath'   => '',
        'description' => 'Soft delete registration (mark as Cancelled)',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_delete_payment' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'delete_payment',
        'classpath'   => '',
        'description' => 'Soft delete payment (mark as Voided)',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_delete_class' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'delete_class',
        'classpath'   => '',
        'description' => 'Soft delete class (mark as Cancelled)',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_delete_enrollment' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'delete_enrollment',
        'classpath'   => '',
        'description' => 'Soft delete enrollment (mark as Withdrawn)',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_delete_grade' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'delete_grade',
        'classpath'   => '',
        'description' => 'Delete grade record',
        'type'        => 'write',
        'ajax'        => true,
    ],
    
    'local_mzi_delete_request' => [
        'classname'   => 'local_moodle_zoho_sync\external\student_dashboard',
        'methodname'  => 'delete_request',
        'classpath'   => '',
        'description' => 'Soft delete request (mark as Cancelled)',
        'type'        => 'write',
        'ajax'        => true,
    ],
];

$services = [
    'Moodle-Zoho Integration Service' => [
        'functions' => [
            'local_moodle_zoho_sync_create_btec_definition',
            'local_mzi_update_student',
            'local_mzi_create_registration',
            'local_mzi_record_payment',
            'local_mzi_create_class',
            'local_mzi_update_enrollment',
            'local_mzi_submit_grade',
            'local_mzi_update_request_status',
            'local_mzi_delete_student',
            'local_mzi_delete_registration',
            'local_mzi_delete_payment',
            'local_mzi_delete_class',
            'local_mzi_delete_enrollment',
            'local_mzi_delete_grade',
            'local_mzi_delete_request',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'moodle_zoho_sync',
        'downloadfiles' => 0,
        'uploadfiles' => 0
    ]
];
