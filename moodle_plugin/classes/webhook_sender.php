<?php
/**
 * Webhook Sender for Moodle-Zoho Integration
 * Sends HTTP POST requests to Backend API
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 ABC Horizon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/event_logger.php');

use local_moodle_zoho_sync\event_logger;

class webhook_sender {

    private $backend_url;
    private $api_token;
    private $ssl_verify;
    private $max_retries = 3;
    private $retry_delay = 2; // seconds

    /**
     * Constructor
     */
    public function __construct() {
        $this->backend_url = get_config('local_moodle_zoho_sync', 'backend_url');
        $this->api_token = get_config('local_moodle_zoho_sync', 'api_token');
        $this->ssl_verify = get_config('local_moodle_zoho_sync', 'ssl_verify');
    }

    /**
     * Send user created webhook
     * 
     * @param array $user_data
     * @param int $moodleeventid Optional Moodle event ID for tracking
     * @param array $context Optional context for enhanced logging
     * @return array Response with event_id, success status
     */
    public function send_user_created($user_data, $moodleeventid = null, $context = []) {
        return $this->send_webhook_with_logging('/api/v1/webhooks', 'user_created', $user_data, $moodleeventid, $context);
    }

    /**
     * Send user updated webhook
     * 
     * @param array $user_data
     * @param int $moodleeventid Optional Moodle event ID for tracking
     * @param array $context Optional context for enhanced logging
     * @return array Response with event_id, success status
     */
    public function send_user_updated($user_data, $moodleeventid = null, $context = []) {
        return $this->send_webhook_with_logging('/api/v1/webhooks', 'user_updated', $user_data, $moodleeventid, $context);
    }

    /**
     * Send enrollment created webhook
     * 
     * @param array $enrollment_data
     * @param int $moodleeventid Optional Moodle event ID for tracking
     * @param array $context Optional context for enhanced logging
     * @return array Response with event_id, success status
     */
    public function send_enrollment_created($enrollment_data, $moodleeventid = null, $context = []) {
        return $this->send_webhook_with_logging('/api/v1/webhooks', 'enrollment_created', $enrollment_data, $moodleeventid, $context);
    }

    /**
     * Send enrollment deleted webhook (unenrolment)
     * 
     * @param array $enrollment_data
     * @param int $moodleeventid Optional Moodle event ID for tracking
     * @param array $context Optional context for enhanced logging
     * @return array Response with event_id, success status
     */
    public function send_enrollment_deleted($enrollment_data, $moodleeventid = null, $context = []) {
        return $this->send_webhook_with_logging('/api/v1/webhooks', 'enrollment_deleted', $enrollment_data, $moodleeventid, $context);
    }

    /**
     * Send grade created webhook (new grade)
     * 
     * @param array $grade_data
     * @param int $moodleeventid Optional Moodle event ID for tracking
     * @param array $context Optional context for enhanced logging
     * @return array Response with event_id, success status
     */
    public function send_grade_created($grade_data, $moodleeventid = null, $context = []) {
        return $this->send_webhook_with_logging('/api/v1/webhooks', 'grade_created', $grade_data, $moodleeventid, $context);
    }

    /**
     * Send grade updated webhook (modified grade)
     * 
     * @param array $grade_data
     * @param int $moodleeventid Optional Moodle event ID for tracking
     * @param array $context Optional context for enhanced logging
     * @return array Response with event_id, success status
     */
    public function send_grade_updated($grade_data, $moodleeventid = null, $context = []) {
        return $this->send_webhook_with_logging('/api/v1/webhooks', 'grade_updated', $grade_data, $moodleeventid, $context);
    }
    
    /**
     * Send webhook with full event logging (SINGLE SOURCE OF TRUTH for UUID)
     * 
     * @param string $endpoint API endpoint path
     * @param string $eventtype Event type for logging
     * @param array $data Payload data
     * @param int $moodleeventid Optional Moodle event ID
     * @param array $context Optional context for enhanced logging (student_name, course_name, etc.)
     * @return array Response with event_id and status
     */
    private function send_webhook_with_logging($endpoint, $eventtype, $data, $moodleeventid = null, $context = []) {
        // Generate UUID EXACTLY ONCE - this is the single source of truth
        $eventid = event_logger::generate_uuid();
        
        // Log event to database BEFORE sending (for idempotency and failure tracking)
        event_logger::log_event($eventtype, $data, $moodleeventid, $eventid, $context);
        
        // Build webhook payload in the format expected by Backend
        $payload = [
            'event_id' => $eventid,
            'event_type' => $eventtype,
            'event_data' => $data,
            'moodle_event_id' => $moodleeventid,
            'timestamp' => time()
        ];
        
        try {
            $response = $this->send_webhook($endpoint, $payload);
            
            // Extract action from backend response
            $action = null;
            if ($response['success'] && !empty($response['body'])) {
                $body = json_decode($response['body'], true);
                if (isset($body['action'])) {
                    $action = $body['action']; // 'created', 'updated', 'deleted'
                }
            }
            
            if ($response['success']) {
                // Update status to 'sent' with action
                event_logger::update_event_status($eventid, 'sent', $response['status'], null, $action);
            } else {
                // Update status to 'failed'
                event_logger::update_event_status($eventid, 'failed', $response['status'] ?? null, $response['error'] ?? 'Unknown error');
            }
            
            return [
                'event_id' => $eventid,
                'success' => $response['success'],
                'status' => $response['status'] ?? null,
                'body' => $response['body'] ?? null
            ];
            
        } catch (\Exception $e) {
            // Log failure even if exception occurs BEFORE webhook send
            event_logger::update_event_status($eventid, 'failed', null, $e->getMessage());
            
            return [
                'event_id' => $eventid,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send webhook with retry logic
     * 
     * @param string $endpoint API endpoint path
     * @param array $data Payload data
     * @return array Response from backend
     */
    private function send_webhook($endpoint, $data) {
        $url = rtrim($this->backend_url, '/') . $endpoint;
        $attempt = 0;
        $last_error = null;

        while ($attempt < $this->max_retries) {
            $attempt++;

            try {
                $response = $this->make_http_request($url, $data);
                
                // Success
                if ($response['status'] >= 200 && $response['status'] < 300) {
                    return [
                        'success' => true,
                        'status' => $response['status'],
                        'body' => $response['body'],
                        'attempt' => $attempt
                    ];
                }

                // Server error - retry
                if ($response['status'] >= 500) {
                    $last_error = "Server error: {$response['status']}";
                    $this->log_warning("Attempt $attempt failed: $last_error. Retrying...");
                    sleep($this->retry_delay * $attempt);
                    continue;
                }

                // Client error - don't retry
                return [
                    'success' => false,
                    'status' => $response['status'],
                    'body' => $response['body'],
                    'error' => 'Client error: ' . $response['status']
                ];

            } catch (\Exception $e) {
                $last_error = $e->getMessage();
                $this->log_warning("Attempt $attempt failed: $last_error. Retrying...");
                sleep($this->retry_delay * $attempt);
            }
        }

        // All retries failed
        return [
            'success' => false,
            'error' => "Failed after $this->max_retries attempts. Last error: $last_error"
        ];
    }

    /**
     * Make HTTP POST request using cURL
     * 
     * @param string $url Full URL
     * @param array $data Payload data
     * @return array ['status' => int, 'body' => string]
     */
    private function make_http_request($url, $data) {
        $ch = curl_init($url);

        // Request headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // Add API token if configured
        if (!empty($this->api_token)) {
            $headers[] = 'Authorization: Bearer ' . $this->api_token;
        }

        // cURL options
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->ssl_verify ? true : false,
            CURLOPT_SSL_VERIFYHOST => $this->ssl_verify ? 2 : 0,
        ]);

        // Execute request
        $response_body = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: $error");
        }

        return [
            'status' => $status_code,
            'body' => $response_body
        ];
    }
    
    /**
     * Extract context details from event data for enhanced logging
     * 
     * @param array $data Event data
     * @param string $eventtype Event type
     * @return array Context details
     */
    public static function extract_context($data, $eventtype) {
        global $DB;
        $context = [];
        
        try {
            // Extract student name - check multiple possible fields
            $userid = null;
            if (!empty($data['user_id'])) {
                $userid = $data['user_id'];
            } elseif (!empty($data['student_id'])) {
                $userid = $data['student_id'];
            } elseif (!empty($data['userid'])) {
                $userid = $data['userid'];
            } elseif (!empty($data['id'])) {
                // For user_created/user_updated events
                $userid = $data['id'];
            }
            
            if ($userid) {
                $user = $DB->get_record('user', ['id' => $userid], 'firstname, lastname, email');
                if ($user) {
                    // Build full name manually to avoid missing fields warning
                    $context['student_name'] = trim($user->firstname . ' ' . $user->lastname);
                    $context['related_id'] = $userid;
                }
            }
            
            // Extract course name - check multiple possible fields
            $courseid = null;
            if (!empty($data['course_id'])) {
                $courseid = $data['course_id'];
            } elseif (!empty($data['courseid'])) {
                $courseid = $data['courseid'];
            }
            
            if ($courseid && $courseid > 1) { // Skip site course
                $course = $DB->get_record('course', ['id' => $courseid], 'fullname, shortname');
                if ($course) {
                    $context['course_name'] = $course->fullname;
                    if (empty($context['related_id'])) {
                        $context['related_id'] = $courseid;
                    }
                }
            }
            
            // Extract assignment name - check multiple possible fields
            $assignmentid = null;
            if (!empty($data['assignment_id'])) {
                $assignmentid = $data['assignment_id'];
            } elseif (!empty($data['assignmentid'])) {
                $assignmentid = $data['assignmentid'];
            }
            
            if ($assignmentid) {
                $assignment = $DB->get_record('assign', ['id' => $assignmentid], 'name');
                if ($assignment) {
                    $context['assignment_name'] = $assignment->name;
                }
            }
            
            // Extract grade value - check multiple possible fields
            $grade = null;
            if (isset($data['grade'])) {
                $grade = $data['grade'];
            } elseif (isset($data['finalgrade'])) {
                $grade = $data['finalgrade'];
            } elseif (isset($data['rawgrade'])) {
                $grade = $data['rawgrade'];
            }
            
            if ($grade !== null && $grade !== '') {
                $context['grade_name'] = is_numeric($grade) ? number_format((float)$grade, 2) : $grade;
            }
            
            // For grade events, also check grade_letter
            if (!empty($data['grade_letter'])) {
                $context['grade_name'] = $data['grade_letter'];
            }
            
        } catch (\Exception $e) {
            debugging('Error extracting context: ' . $e->getMessage(), DEBUG_DEVELOPER);
            error_log('Context extraction error: ' . $e->getMessage() . ' | Data: ' . json_encode($data));
        }
        
        return $context;
    }

    /**
     * Log warning message
     * 
     * @param string $message
     */
    private function log_warning($message) {
        error_log('[Moodle-Zoho Sync WARNING] ' . $message);
    }
}
