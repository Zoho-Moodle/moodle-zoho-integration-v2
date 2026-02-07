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
 * Configuration manager for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodle_zoho_sync;

defined('MOODLE_INTERNAL') || die();

/**
 * Config manager class.
 *
 * Manages plugin configuration with encryption support.
 */
class config_manager {

    /** @var string Encryption method */
    const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Get configuration value.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        return get_config('local_moodle_zoho_sync', $key) ?: $default;
    }

    /**
     * Set configuration value.
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool Success
     */
    public static function set($key, $value) {
        return set_config($key, $value, 'local_moodle_zoho_sync');
    }

    /**
     * Get encrypted configuration value.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Decrypted configuration value
     */
    public static function get_encrypted($key, $default = null) {
        global $DB;

        try {
            $record = $DB->get_record('local_mzi_config', 
                array('config_key' => $key, 'is_encrypted' => 1));
            
            if (!$record || empty($record->config_value)) {
                return $default;
            }

            return self::decrypt($record->config_value);

        } catch (\Exception $e) {
            debugging('Error retrieving encrypted config: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $default;
        }
    }

    /**
     * Set encrypted configuration value.
     *
     * @param string $key Configuration key
     * @param string $value Configuration value to encrypt
     * @return bool Success
     */
    public static function set_encrypted($key, $value) {
        global $DB, $USER;

        try {
            $encrypted = self::encrypt($value);
            
            $record = $DB->get_record('local_mzi_config', array('config_key' => $key));
            
            if ($record) {
                // Update existing record.
                $record->config_value = $encrypted;
                $record->is_encrypted = 1;
                $record->timemodified = time();
                $record->updated_by = $USER->id ?? 0;
                return $DB->update_record('local_mzi_config', $record);
            } else {
                // Insert new record.
                $record = new \stdClass();
                $record->config_key = $key;
                $record->config_value = $encrypted;
                $record->is_encrypted = 1;
                $record->timemodified = time();
                $record->updated_by = $USER->id ?? 0;
                return $DB->insert_record('local_mzi_config', $record) > 0;
            }

        } catch (\Exception $e) {
            debugging('Error setting encrypted config: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Encrypt data.
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     */
    private static function encrypt($data) {
        global $CFG;

        // Use Moodle's secret key as encryption key (binary format).
        $key = hash('sha256', $CFG->passwordsaltmain ?? 'default_salt_key', true);
        
        // Generate initialization vector.
        $ivlength = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = openssl_random_pseudo_bytes($ivlength);
        
        // Encrypt with OPENSSL_RAW_DATA for binary output.
        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        
        // Combine IV and encrypted data (both binary), then base64 encode once.
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data.
     *
     * @param string $data Encrypted data (base64 encoded)
     * @return string Decrypted data
     */
    private static function decrypt($data) {
        global $CFG;

        // Use Moodle's secret key as encryption key (binary format).
        $key = hash('sha256', $CFG->passwordsaltmain ?? 'default_salt_key', true);
        
        // Decode base64 once.
        $data = base64_decode($data);
        
        // Extract IV and encrypted data.
        $ivlength = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = substr($data, 0, $ivlength);
        $encrypted = substr($data, $ivlength);
        
        // Decrypt with OPENSSL_RAW_DATA for binary input.
        return openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Get Backend API URL.
     *
     * @return string Backend API URL
     */
    public static function get_backend_url() {
        $url = self::get('backend_url', 'http://localhost:8001');
        return rtrim($url, '/');
    }

    /**
     * Get API token - ALWAYS from encrypted storage, NEVER from mdl_config_plugins
     * SECURITY MODEL: Tokens are stored encrypted in local_mzi_config, not in plain text.
     *
     * @return string API token (decrypted) or empty string
     */
    public static function get_api_token() {
        // CRITICAL: Read from encrypted storage ONLY
        return self::get_encrypted('api_token', '');
    }
    
    /**
     * Set API token - ALWAYS to encrypted storage, NEVER to mdl_config_plugins
     * SECURITY MODEL: This is called from settings.php when admin updates the token.
     *
     * @param string $token API token to store (will be encrypted)
     * @return bool Success
     */
    public static function set_api_token($token) {
        // CRITICAL: Write to encrypted storage ONLY
        // First, remove any legacy plain-text token from mdl_config_plugins (cleanup)
        unset_config('api_token', 'local_moodle_zoho_sync');
        
        // Store encrypted in local_mzi_config
        return self::set_encrypted('api_token', $token);
    }

    /**
     * Check if SSL verification is enabled.
     *
     * @return bool SSL verification enabled
     */
    public static function is_ssl_verify_enabled() {
        return (bool)self::get('ssl_verify', true);
    }

    /**
     * Check if user sync is enabled.
     *
     * @return bool User sync enabled
     */
    public static function is_user_sync_enabled() {
        return (bool)self::get('enable_user_sync', true);
    }

    /**
     * Check if enrollment sync is enabled.
     *
     * @return bool Enrollment sync enabled
     */
    public static function is_enrollment_sync_enabled() {
        return (bool)self::get('enable_enrollment_sync', true);
    }

    /**
     * Check if grade sync is enabled.
     *
     * @return bool Grade sync enabled
     */
    public static function is_grade_sync_enabled() {
        return (bool)self::get('enable_grade_sync', true);
    }

    /**
     * Get max retry attempts.
     *
     * @return int Max retry attempts
     */
    public static function get_max_retry_attempts() {
        return (int)self::get('max_retry_attempts', 3);
    }

    /**
     * Get retry delay in seconds.
     *
     * @return int Retry delay
     */
    public static function get_retry_delay() {
        return (int)self::get('retry_delay', 5);
    }

    /**
     * Check if debug logging is enabled.
     *
     * @return bool Debug logging enabled
     */
    public static function is_debug_enabled() {
        return (bool)self::get('enable_debug', false);
    }

    /**
     * Get log retention days.
     *
     * @return int Log retention days
     */
    public static function get_log_retention_days() {
        return (int)self::get('log_retention_days', 30);
    }

    /**
     * Get connection timeout in seconds.
     *
     * @return int Connection timeout
     */
    public static function get_connection_timeout() {
        return (int)self::get('connection_timeout', 10);
    }

    /**
     * Test Backend API connection.
     *
     * @return array Result with 'success' boolean and 'message' string
     */
    public static function test_connection() {
        try {
            $url = self::get_backend_url() . '/health';
            $token = self::get_api_token();

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, self::is_ssl_verify_enabled());

            if (!empty($token)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ));
            }

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpcode === 200) {
                return array(
                    'success' => true,
                    'message' => get_string('connection_success', 'local_moodle_zoho_sync')
                );
            } else {
                return array(
                    'success' => false,
                    'message' => get_string('connection_failed', 'local_moodle_zoho_sync') . 
                                " (HTTP $httpcode)"
                );
            }

        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => get_string('connection_error', 'local_moodle_zoho_sync') . 
                            ': ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Alias for test_connection() - for backward compatibility with Admin Dashboard.
     *
     * @return array Result with 'success' boolean and 'message' string
     */
    public static function test_backend_connection() {
        return self::test_connection();
    }
    
    /**
     * Check backend health with detailed response.
     * Used by health_check.php for comprehensive system diagnostics.
     *
     * @return array Health status with keys: healthy, error, response_time
     */
    public static function check_backend_health() {
        try {
            $url = self::get('backend_url');
            
            if (empty($url)) {
                return [
                    'healthy' => false,
                    'error' => 'Backend URL not configured',
                    'response_time' => 0
                ];
            }
            
            // Ensure URL has /health endpoint
            $healthurl = rtrim($url, '/') . '/health';
            $token = self::get_api_token();
            
            $starttime = microtime(true);
            
            $ch = curl_init($healthurl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, self::is_ssl_verify_enabled());
            
            if (!empty($token)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ]);
            }
            
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $responsetime = round((microtime(true) - $starttime) * 1000); // Convert to ms
            
            if ($httpcode === 200) {
                return [
                    'healthy' => true,
                    'response_time' => $responsetime
                ];
            } else {
                return [
                    'healthy' => false,
                    'error' => "HTTP $httpcode" . ($error ? ": $error" : ''),
                    'response_time' => $responsetime
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'response_time' => 0
            ];
        }
    }
}
