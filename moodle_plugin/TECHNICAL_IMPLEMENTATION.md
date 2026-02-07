# 🔧 التفاصيل التقنية العملية - Moodle Plugin Implementation
# Technical Implementation Details

<div dir="rtl">

## 📋 جدول المحتويات

1. [الأكواد الجاهزة للتنفيذ](#الأكواد-الجاهزة-للتنفيذ)
2. [Database Schema التفصيلي](#database-schema-التفصيلي)
3. [API Contracts](#api-contracts)
4. [أمثلة عملية](#أمثلة-عملية)
5. [Troubleshooting](#troubleshooting)

---

## 💻 الأكواد الجاهزة للتنفيذ

### 1. version.php

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_moodle_zoho_integration';
$plugin->version   = 2026020100;   // YYYYMMDDXX
$plugin->requires  = 2022041900;   // Moodle 4.0+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '3.0 - Complete Integration';
$plugin->dependencies = [];
```

---

### 2. db/install.xml

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/moodle_zoho_integration/db" VERSION="20260201" COMMENT="Moodle Zoho Integration tables">
  <TABLES>
    
    <!-- Event Log Table -->
    <TABLE NAME="mb_zoho_event_log" COMMENT="Stores all webhook events">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="event_id" TYPE="char" LENGTH="36" NOTNULL="true" COMMENT="UUID"/>
        <FIELD NAME="event_type" TYPE="char" LENGTH="50" NOTNULL="true"/>
        <FIELD NAME="event_data" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="status" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="pending"/>
        <FIELD NAME="retry_count" TYPE="int" LENGTH="2" NOTNULL="true" DEFAULT="0"/>
        <FIELD NAME="last_error" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="created_at" TYPE="int" LENGTH="10" NOTNULL="true"/>
        <FIELD NAME="updated_at" TYPE="int" LENGTH="10" NOTNULL="true"/>
        <FIELD NAME="processed_at" TYPE="int" LENGTH="10" NOTNULL="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="event_id_unique" TYPE="unique" FIELDS="event_id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="event_type_idx" UNIQUE="false" FIELDS="event_type"/>
        <INDEX NAME="status_idx" UNIQUE="false" FIELDS="status"/>
        <INDEX NAME="created_at_idx" UNIQUE="false" FIELDS="created_at"/>
      </INDEXES>
    </TABLE>
    
    <!-- Sync History Table -->
    <TABLE NAME="mb_zoho_sync_history" COMMENT="Tracks manual sync operations">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="sync_type" TYPE="char" LENGTH="50" NOTNULL="true"/>
        <FIELD NAME="sync_action" TYPE="char" LENGTH="50" NOTNULL="true"/>
        <FIELD NAME="status" TYPE="char" LENGTH="20" NOTNULL="true"/>
        <FIELD NAME="records_processed" TYPE="int" LENGTH="10" DEFAULT="0"/>
        <FIELD NAME="records_failed" TYPE="int" LENGTH="10" DEFAULT="0"/>
        <FIELD NAME="started_at" TYPE="int" LENGTH="10" NOTNULL="true"/>
        <FIELD NAME="completed_at" TYPE="int" LENGTH="10" NOTNULL="false"/>
        <FIELD NAME="error_message" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="triggered_by" TYPE="int" LENGTH="10" NOTNULL="false"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="sync_type_idx" UNIQUE="false" FIELDS="sync_type"/>
        <INDEX NAME="status_idx" UNIQUE="false" FIELDS="status"/>
      </INDEXES>
    </TABLE>
    
    <!-- Config Table -->
    <TABLE NAME="mb_zoho_config" COMMENT="Encrypted configuration storage">
      <FIELDS>
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="config_key" TYPE="char" LENGTH="100" NOTNULL="true"/>
        <FIELD NAME="config_value" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="is_encrypted" TYPE="int" LENGTH="1" DEFAULT="0"/>
        <FIELD NAME="updated_at" TYPE="int" LENGTH="10" NOTNULL="true"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="config_key_unique" TYPE="unique" FIELDS="config_key"/>
      </KEYS>
    </TABLE>
    
  </TABLES>
</XMLDB>
```

---

### 3. db/events.php

```php
<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback' => '\local_moodle_zoho_integration\observer::user_created_handler',
        'priority' => 100,
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback' => '\local_moodle_zoho_integration\observer::user_updated_handler',
        'priority' => 100,
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_moodle_zoho_integration\observer::enrollment_created_handler',
        'priority' => 100,
    ],
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => '\local_moodle_zoho_integration\observer::grade_updated_handler',
        'priority' => 100,
    ],
];
```

---

### 4. db/access.php

```php
<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/moodle_zoho_integration:manage' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    
    'local/moodle_zoho_integration:viewdashboard' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'user' => CAP_ALLOW,
        ],
    ],
    
    'local/moodle_zoho_integration:viewothers' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
    
    'local/moodle_zoho_integration:triggersync' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
```

---

### 5. classes/observer.php

```php
<?php
namespace local_moodle_zoho_integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for Moodle-Backend integration
 */
class observer {
    
    /**
     * Handle user created event
     */
    public static function user_created_handler(\core\event\user_created $event) {
        try {
            // Extract user data
            $data = data_extractor::extract_user_data($event->objectid);
            
            // Generate unique event ID
            $event_id = self::generate_event_id();
            
            // Send webhook
            $sender = new webhook_sender();
            $success = $sender->send_event('user_created', $event_id, $data);
            
            if (!$success) {
                // Queue for retry
                event_logger::queue_for_retry($event_id, 'user_created', $data);
            }
            
        } catch (\Exception $e) {
            // Log error but don't break Moodle
            debugging('Webhook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            event_logger::log_error($event->objectid, 'user_created', $e->getMessage());
        }
    }
    
    /**
     * Handle user updated event
     */
    public static function user_updated_handler(\core\event\user_updated $event) {
        try {
            $data = data_extractor::extract_user_data($event->objectid);
            $event_id = self::generate_event_id();
            
            $sender = new webhook_sender();
            $success = $sender->send_event('user_updated', $event_id, $data);
            
            if (!$success) {
                event_logger::queue_for_retry($event_id, 'user_updated', $data);
            }
            
        } catch (\Exception $e) {
            debugging('Webhook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            event_logger::log_error($event->objectid, 'user_updated', $e->getMessage());
        }
    }
    
    /**
     * Handle enrollment created event
     */
    public static function enrollment_created_handler(\core\event\user_enrolment_created $event) {
        try {
            $data = data_extractor::extract_enrollment_data($event->objectid);
            $event_id = self::generate_event_id();
            
            $sender = new webhook_sender();
            $success = $sender->send_event('enrollment_created', $event_id, $data);
            
            if (!$success) {
                event_logger::queue_for_retry($event_id, 'enrollment_created', $data);
            }
            
        } catch (\Exception $e) {
            debugging('Webhook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            event_logger::log_error($event->objectid, 'enrollment_created', $e->getMessage());
        }
    }
    
    /**
     * Handle grade updated event
     */
    public static function grade_updated_handler(\mod_assign\event\submission_graded $event) {
        try {
            $data = data_extractor::extract_grade_data($event->objectid);
            $event_id = self::generate_event_id();
            
            $sender = new webhook_sender();
            $success = $sender->send_event('grade_updated', $event_id, $data);
            
            if (!$success) {
                event_logger::queue_for_retry($event_id, 'grade_updated', $data);
            }
            
        } catch (\Exception $e) {
            debugging('Webhook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            event_logger::log_error($event->objectid, 'grade_updated', $e->getMessage());
        }
    }
    
    /**
     * Generate unique event ID (UUID v4)
     */
    private static function generate_event_id() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
```

---

### 6. classes/data_extractor.php

```php
<?php
namespace local_moodle_zoho_integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Extract and validate data from Moodle database
 */
class data_extractor {
    
    /**
     * Extract user data
     */
    public static function extract_user_data($userid) {
        global $DB;
        
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        
        return [
            'userid' => (int)$user->id,
            'username' => clean_param($user->username, PARAM_EMAIL),
            'firstname' => clean_param($user->firstname, PARAM_TEXT),
            'lastname' => clean_param($user->lastname, PARAM_TEXT),
            'email' => clean_param($user->email, PARAM_EMAIL),
            'idnumber' => clean_param($user->idnumber, PARAM_TEXT),
            'phone1' => clean_param($user->phone1, PARAM_TEXT),
            'city' => clean_param($user->city, PARAM_TEXT),
            'country' => clean_param($user->country, PARAM_TEXT),
            'suspended' => (bool)$user->suspended,
            'deleted' => (bool)$user->deleted,
            'timecreated' => (int)$user->timecreated,
            'timemodified' => (int)$user->timemodified,
        ];
    }
    
    /**
     * Extract enrollment data
     */
    public static function extract_enrollment_data($enrolmentid) {
        global $DB;
        
        $enrolment = $DB->get_record('user_enrolments', ['id' => $enrolmentid], '*', MUST_EXIST);
        $enrol = $DB->get_record('enrol', ['id' => $enrolment->enrolid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $enrol->courseid], '*', MUST_EXIST);
        
        return [
            'enrollmentid' => (int)$enrolment->id,
            'userid' => (int)$enrolment->userid,
            'courseid' => (int)$enrol->courseid,
            'coursename' => clean_param($course->fullname, PARAM_TEXT),
            'courseshortname' => clean_param($course->shortname, PARAM_TEXT),
            'roleid' => 5, // Student role
            'status' => (int)$enrolment->status,
            'timestart' => (int)$enrolment->timestart,
            'timeend' => (int)$enrolment->timeend,
            'timecreated' => (int)$enrolment->timecreated,
        ];
    }
    
    /**
     * Extract grade data
     */
    public static function extract_grade_data($gradeid) {
        global $DB;
        
        $grade = $DB->get_record('assign_grades', ['id' => $gradeid], '*', MUST_EXIST);
        $assignment = $DB->get_record('assign', ['id' => $grade->assignment], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $assignment->course], '*', MUST_EXIST);
        
        // Convert to percentage (0-100)
        $maxgrade = $assignment->grade;
        $score = ($grade->grade / $maxgrade) * 100;
        
        // BTEC conversion
        $btec_grade = self::convert_to_btec($score);
        
        // Get feedback
        $feedback = '';
        $feedbackrecord = $DB->get_record('assignfeedback_comments', ['grade' => $gradeid]);
        if ($feedbackrecord) {
            $feedback = format_text($feedbackrecord->commenttext, FORMAT_HTML);
            $feedback = strip_tags($feedback);
        }
        
        return [
            'gradeid' => (int)$grade->id,
            'userid' => (int)$grade->userid,
            'assignmentid' => (int)$assignment->id,
            'assignmentname' => clean_param($assignment->name, PARAM_TEXT),
            'courseid' => (int)$course->id,
            'coursename' => clean_param($course->fullname, PARAM_TEXT),
            'score' => round($score, 2),
            'btec_grade' => $btec_grade,
            'feedback' => clean_param($feedback, PARAM_TEXT),
            'grader' => (int)$grade->grader,
            'timecreated' => (int)$grade->timecreated,
            'timemodified' => (int)$grade->timemodified,
        ];
    }
    
    /**
     * Convert score to BTEC grade
     */
    private static function convert_to_btec($score) {
        $score = max(0, min(100, $score)); // Clamp between 0-100
        
        if ($score >= 70) {
            return 'Distinction';
        } elseif ($score >= 60) {
            return 'Merit';
        } elseif ($score >= 40) {
            return 'Pass';
        } else {
            return 'Refer';
        }
    }
}
```

---

### 7. classes/webhook_sender.php

```php
<?php
namespace local_moodle_zoho_integration;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTP client for sending webhooks to Backend API
 */
class webhook_sender {
    
    private $config;
    
    public function __construct() {
        $this->config = config_manager::get_settings();
    }
    
    /**
     * Send event to Backend API
     */
    public function send_event($event_type, $event_id, $data) {
        // Check if integration is enabled
        if (empty($this->config['enabled'])) {
            debugging('Integration is disabled', DEBUG_DEVELOPER);
            return false;
        }
        
        // Build endpoint URL
        $endpoint = $this->get_endpoint($event_type);
        if (!$endpoint) {
            throw new \Exception("Unknown event type: $event_type");
        }
        
        // Build payload
        $payload = [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'timestamp' => date('c'),
            'source' => 'moodle',
            'moodle_url' => $this->config['moodle_url'] ?? '',
            'tenant_id' => $this->config['tenant_id'] ?? 'default',
            'data' => $data,
        ];
        
        // Send with retry
        return $this->send_with_retry($endpoint, $payload);
    }
    
    /**
     * Get Backend endpoint for event type
     */
    private function get_endpoint($event_type) {
        $base_url = rtrim($this->config['backend_url'], '/');
        
        $endpoints = [
            'user_created' => '/v1/events/moodle/user_created',
            'user_updated' => '/v1/events/moodle/user_updated',
            'enrollment_created' => '/v1/events/moodle/enrollment_created',
            'grade_updated' => '/v1/events/moodle/grade_updated',
        ];
        
        if (!isset($endpoints[$event_type])) {
            return null;
        }
        
        return $base_url . $endpoints[$event_type];
    }
    
    /**
     * Send HTTP POST with retry logic
     */
    private function send_with_retry($url, $payload, $max_attempts = 3) {
        $attempt = 0;
        $delay = 5; // seconds
        
        while ($attempt < $max_attempts) {
            $attempt++;
            
            try {
                $response = $this->send_http_post($url, $payload);
                
                if ($response['http_code'] >= 200 && $response['http_code'] < 300) {
                    // Success
                    event_logger::log_event($payload['event_id'], 'success', $response);
                    return true;
                }
                
                // Non-2xx response
                $error = "HTTP {$response['http_code']}: {$response['body']}";
                event_logger::log_event($payload['event_id'], 'failed', [
                    'attempt' => $attempt,
                    'error' => $error,
                ]);
                
            } catch (\Exception $e) {
                event_logger::log_event($payload['event_id'], 'failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
            
            if ($attempt < $max_attempts) {
                sleep($delay);
                $delay *= 2; // Exponential backoff
            }
        }
        
        // All retries failed
        event_logger::log_event($payload['event_id'], 'failed_all_retries', [
            'attempts' => $max_attempts,
        ]);
        
        return false;
    }
    
    /**
     * Execute HTTP POST request
     */
    private function send_http_post($url, $payload) {
        // Validate HTTPS
        if (strpos($url, 'https://') !== 0 && strpos($url, 'http://localhost') !== 0) {
            throw new \Exception('Only HTTPS URLs are allowed (or localhost for testing)');
        }
        
        // Prepare headers
        $headers = [
            'Content-Type: application/json',
            'X-Moodle-Token: ' . $this->config['api_token'],
            'X-Tenant-ID: ' . $this->config['tenant_id'],
            'X-Event-ID: ' . $payload['event_id'],
        ];
        
        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Execute
        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("cURL error: $error");
        }
        
        return [
            'http_code' => $http_code,
            'body' => $response_body,
        ];
    }
}
```

---

### 8. classes/config_manager.php

```php
<?php
namespace local_moodle_zoho_integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Configuration management with encryption
 */
class config_manager {
    
    /**
     * Get all settings
     */
    public static function get_settings() {
        return [
            'enabled' => get_config('local_moodle_zoho_integration', 'enabled'),
            'backend_url' => get_config('local_moodle_zoho_integration', 'backend_url'),
            'api_token' => self::decrypt(get_config('local_moodle_zoho_integration', 'api_token')),
            'tenant_id' => get_config('local_moodle_zoho_integration', 'tenant_id') ?: 'default',
            'moodle_url' => get_config('local_moodle_zoho_integration', 'moodle_url'),
            'retry_enabled' => get_config('local_moodle_zoho_integration', 'retry_enabled'),
            'max_retry_attempts' => get_config('local_moodle_zoho_integration', 'max_retry_attempts') ?: 3,
        ];
    }
    
    /**
     * Save settings
     */
    public static function save_settings($settings) {
        foreach ($settings as $key => $value) {
            if ($key === 'api_token') {
                // Encrypt sensitive data
                $value = self::encrypt($value);
            }
            set_config($key, $value, 'local_moodle_zoho_integration');
        }
    }
    
    /**
     * Encrypt value
     */
    private static function encrypt($value) {
        if (empty($value)) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', hex2bin($key), 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt value
     */
    private static function decrypt($encrypted) {
        if (empty($encrypted)) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', hex2bin($key), 0, $iv);
    }
    
    /**
     * Get or create encryption key
     */
    private static function get_encryption_key() {
        $key = get_config('local_moodle_zoho_integration', 'encryption_key');
        
        if (!$key) {
            // Generate new key on first use
            $key = bin2hex(random_bytes(32));
            set_config('encryption_key', $key, 'local_moodle_zoho_integration');
        }
        
        return $key;
    }
    
    /**
     * Test Backend connection
     */
    public static function test_connection() {
        $config = self::get_settings();
        $url = rtrim($config['backend_url'], '/') . '/v1/health';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Moodle-Token: ' . $config['api_token'],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => ($http_code === 200),
            'http_code' => $http_code,
            'response' => $response,
        ];
    }
}
```

---

### 9. classes/event_logger.php

```php
<?php
namespace local_moodle_zoho_integration;

defined('MOODLE_INTERNAL') || die();

/**
 * Event logging to local database
 */
class event_logger {
    
    /**
     * Log event to database
     */
    public static function log_event($event_id, $status, $details = []) {
        global $DB;
        
        $record = [
            'event_id' => $event_id,
            'status' => $status,
            'updated_at' => time(),
        ];
        
        if ($status === 'success') {
            $record['processed_at'] = time();
        } elseif ($status === 'failed') {
            $record['retry_count'] = $DB->get_field('mb_zoho_event_log', 'retry_count', ['event_id' => $event_id]) + 1;
            $record['last_error'] = json_encode($details);
        }
        
        // Update or insert
        $existing = $DB->get_record('mb_zoho_event_log', ['event_id' => $event_id]);
        if ($existing) {
            $record['id'] = $existing->id;
            $DB->update_record('mb_zoho_event_log', $record);
        } else {
            $record['created_at'] = time();
            $DB->insert_record('mb_zoho_event_log', $record);
        }
    }
    
    /**
     * Queue event for retry
     */
    public static function queue_for_retry($event_id, $event_type, $data) {
        global $DB;
        
        $record = [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'event_data' => json_encode($data),
            'status' => 'pending',
            'retry_count' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];
        
        $DB->insert_record('mb_zoho_event_log', $record);
    }
    
    /**
     * Log error
     */
    public static function log_error($objectid, $event_type, $error) {
        debugging("Event error ($event_type, $objectid): $error", DEBUG_DEVELOPER);
        
        // Could also write to a separate error log file
        $logfile = __DIR__ . '/../logs/errors.log';
        $logdir = dirname($logfile);
        
        if (!is_dir($logdir)) {
            mkdir($logdir, 0755, true);
        }
        
        $message = sprintf(
            "[%s] %s (ID: %s): %s\n",
            date('Y-m-d H:i:s'),
            $event_type,
            $objectid,
            $error
        );
        
        file_put_contents($logfile, $message, FILE_APPEND);
    }
}
```

---

## 🗃️ Database Schema التفصيلي

### Tables Relationships

```
┌────────────────────────────────────────────────────────────┐
│                   MOODLE DATABASE                          │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  mdl_mb_zoho_event_log                                     │
│  ├─ id (PK)                                                │
│  ├─ event_id (UNIQUE) ◄─── Used for idempotency          │
│  ├─ event_type                                             │
│  ├─ event_data (JSON)                                      │
│  ├─ status                                                 │
│  ├─ retry_count                                            │
│  ├─ last_error                                             │
│  ├─ created_at                                             │
│  ├─ updated_at                                             │
│  └─ processed_at                                           │
│                                                            │
│  mdl_mb_zoho_sync_history                                  │
│  ├─ id (PK)                                                │
│  ├─ sync_type                                              │
│  ├─ sync_action                                            │
│  ├─ status                                                 │
│  ├─ records_processed                                      │
│  ├─ records_failed                                         │
│  ├─ started_at                                             │
│  ├─ completed_at                                           │
│  ├─ error_message                                          │
│  └─ triggered_by (FK → mdl_user.id)                       │
│                                                            │
│  mdl_mb_zoho_config                                        │
│  ├─ id (PK)                                                │
│  ├─ config_key (UNIQUE)                                    │
│  ├─ config_value (encrypted)                               │
│  ├─ is_encrypted                                           │
│  └─ updated_at                                             │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### Queries Examples

```sql
-- Get all failed events (need retry)
SELECT * FROM mdl_mb_zoho_event_log 
WHERE status = 'failed' 
  AND retry_count < 3 
ORDER BY created_at ASC;

-- Get sync history for last 30 days
SELECT * FROM mdl_mb_zoho_sync_history 
WHERE started_at > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
ORDER BY started_at DESC;

-- Get event statistics
SELECT 
    event_type,
    status,
    COUNT(*) as count,
    AVG(retry_count) as avg_retries
FROM mdl_mb_zoho_event_log
GROUP BY event_type, status;
```

---

## 📡 API Contracts

### Backend Endpoints (Required)

#### 1. POST /v1/events/moodle/user_created

**Request:**
```json
{
  "event_id": "uuid-v4-here",
  "event_type": "user_created",
  "timestamp": "2026-02-01T14:30:00Z",
  "source": "moodle",
  "tenant_id": "default",
  "data": {
    "userid": 123,
    "username": "john.doe@example.com",
    "firstname": "John",
    "lastname": "Doe",
    "email": "john.doe@example.com",
    "idnumber": "STU12345",
    "phone1": "+962791234567",
    "city": "Amman",
    "country": "JO",
    "suspended": false,
    "deleted": false,
    "timecreated": 1640000000,
    "timemodified": 1640000000
  }
}
```

**Response (Success):**
```json
{
  "status": "success",
  "event_id": "uuid-v4-here",
  "message": "User created event processed"
}
```

**Response (Duplicate):**
```json
{
  "status": "duplicate",
  "event_id": "uuid-v4-here",
  "message": "Event already processed"
}
```

---

#### 2. GET /v1/students/profile

**Request:**
```
GET /v1/students/profile?moodle_user_id=123
Headers:
  X-Moodle-Token: your-token-here
  X-Tenant-ID: default
```

**Response:**
```json
{
  "student": {
    "id": "uuid",
    "zoho_id": "zoho123",
    "moodle_userid": 123,
    "username": "john.doe@example.com",
    "display_name": "John Doe",
    "academic_email": "john.doe@example.com",
    "phone": "+962791234567",
    "status": "Active"
  },
  "programs": [
    {
      "id": "uuid",
      "name": "BTEC Level 5",
      "status": "Active",
      "registration_date": "2024-09-01"
    }
  ],
  "payments": [
    {
      "id": "uuid",
      "amount": 5000.0,
      "payment_date": "2024-09-15",
      "payment_method": "Bank Transfer",
      "status": "Completed"
    }
  ],
  "classes": [
    {
      "id": "uuid",
      "name": "Advanced Programming",
      "teacher": "Dr. Smith",
      "status": "Active"
    }
  ],
  "grades": [
    {
      "id": "uuid",
      "unit": "Programming Fundamentals",
      "grade": "Distinction",
      "score": 85.5,
      "date": "2024-12-20",
      "feedback": "Excellent work!"
    }
  ]
}
```

---

## 🧪 أمثلة عملية

### Example 1: Full Event Flow

```
1. Student submits assignment in Moodle
   └─> Triggers: \mod_assign\event\submission_graded

2. Observer captures event
   └─> observer::grade_updated_handler()

3. Extract data from Moodle DB
   └─> data_extractor::extract_grade_data($gradeid)
   └─> Returns: {userid, score, btec_grade, feedback, ...}

4. Generate unique event_id
   └─> event_id = "550e8400-e29b-41d4-a716-446655440000"

5. Send webhook to Backend
   └─> webhook_sender::send_event('grade_updated', event_id, data)
   └─> POST https://backend.example.com/v1/events/moodle/grade_updated

6. Backend receives and processes
   └─> Check event_id uniqueness (deduplication)
   └─> Store in PostgreSQL (grades table)
   └─> Return 200 OK

7. Moodle logs success
   └─> event_logger::log_event(event_id, 'success')
   └─> Status in mdl_mb_zoho_event_log = 'success'
```

### Example 2: Retry on Failure

```
1. Network timeout occurs
   └─> webhook_sender receives exception

2. Log failure (attempt 1)
   └─> event_logger::log_event(event_id, 'failed', ['attempt' => 1])

3. Wait 5 seconds (exponential backoff)

4. Retry (attempt 2)
   └─> Same exception

5. Wait 10 seconds

6. Retry (attempt 3)
   └─> Success! Backend responds 200 OK

7. Log final success
   └─> event_logger::log_event(event_id, 'success')
```

---

## 🔧 Troubleshooting

### Common Issues & Solutions

#### Issue 1: Events not being sent

**Symptoms:**
- No entries in `mdl_mb_zoho_event_log`
- Backend not receiving webhooks

**Diagnosis:**
```php
// Check if observer is registered
$observers = $DB->get_records('events_handlers');
print_r($observers);

// Check if plugin is enabled
$enabled = get_config('local_moodle_zoho_integration', 'enabled');
echo "Enabled: " . ($enabled ? 'Yes' : 'No');

// Test webhook manually
$sender = new \local_moodle_zoho_integration\webhook_sender();
$test_data = ['test' => 'data'];
$result = $sender->send_event('user_created', 'test-uuid', $test_data);
echo "Result: " . ($result ? 'Success' : 'Failed');
```

**Solutions:**
1. Purge caches: `php admin/cli/purge_caches.php`
2. Verify observer registration: Check `db/events.php`
3. Enable debugging: `$CFG->debug = DEBUG_DEVELOPER;`

---

#### Issue 2: Backend returns 401 Unauthorized

**Symptoms:**
- HTTP 401 in event logs
- "Invalid Moodle token" error

**Diagnosis:**
```php
// Check token configuration
$config = \local_moodle_zoho_integration\config_manager::get_settings();
echo "Token: " . $config['api_token'];

// Test connection
$result = \local_moodle_zoho_integration\config_manager::test_connection();
print_r($result);
```

**Solutions:**
1. Verify token matches Backend `.env` file
2. Re-save settings in admin panel
3. Check encryption/decryption working

---

#### Issue 3: Dashboard shows "No data"

**Symptoms:**
- Student dashboard loads but shows empty data
- No errors in logs

**Diagnosis:**
```php
// Check if student exists in Backend
$userid = 123; // Moodle user ID
$config = \local_moodle_zoho_integration\config_manager::get_settings();
$url = $config['backend_url'] . "/v1/students/profile?moodle_user_id=$userid";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Moodle-Token: ' . $config['api_token']
]);
$response = curl_exec($ch);
echo $response;
```

**Solutions:**
1. Verify student synced to Backend: Check `students` table in PostgreSQL
2. Ensure `moodle_userid` field populated
3. Trigger manual sync for that user

---

## 📚 الخلاصة

هذه الوثيقة تحتوي على:

✅ **Complete Code Implementation** - جاهز للنسخ واللصق  
✅ **Database Schema** - Tables + indexes + relationships  
✅ **API Contracts** - Request/response formats  
✅ **Practical Examples** - Real-world scenarios  
✅ **Troubleshooting Guide** - Common issues + solutions  

**الخطوة التالية:** ابدأ بـ `version.php` وانتقل بالترتيب حسب خطة الـ 7 أسابيع! 🚀

</div>

---

**تاريخ الإنشاء:** فبراير 1, 2026  
**الإصدار:** 1.0 (Implementation Ready)  
**الحالة:** Production Ready ✅
