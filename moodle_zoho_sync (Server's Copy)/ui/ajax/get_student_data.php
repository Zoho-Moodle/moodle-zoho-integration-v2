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
 * AJAX endpoint to fetch student data from Backend API.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

use local_moodle_zoho_sync\config_manager;

require_login();

// CSRF protection.
$sesskey = required_param('sesskey', PARAM_ALPHANUM);
require_sesskey();

$context = context_system::instance();

$userid = required_param('userid', PARAM_INT);
$datatype = required_param('type', PARAM_ALPHA); // profile, academics, finance, classes, grades

// Verify user can only access their own data (no role check needed)
if ($userid != $USER->id) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(array('error' => 'Access denied - you can only view your own data'));
    exit;
}

/**
 * Fetch data from Backend API.
 *
 * @param string $endpoint API endpoint
 * @param array $params Query parameters
 * @return array Response data
 */
function fetch_backend_data($endpoint, $params = array()) {
    $baseurl = config_manager::get_backend_url();
    $token = config_manager::get_api_token();
    $sslverify = config_manager::is_ssl_verify_enabled();
    $timeout = config_manager::get_connection_timeout();

    // Build URL with query parameters.
    $url = $baseurl . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    // Initialize cURL.
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslverify);

    // Add authorization header if token exists.
    $headers = array('Content-Type: application/json');
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute request.
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Handle response.
    if ($httpcode === 200 && $response) {
        return json_decode($response, true);
    } else {
        return array(
            'error' => true,
            'message' => $error ? $error : "HTTP $httpcode",
            'http_code' => $httpcode
        );
    }
}

/**
 * Format response as JSON and exit.
 *
 * @param mixed $data Response data
 */
function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

try {
    // Get Moodle user.
    $user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0));
    
    if (!$user) {
        json_response(array('error' => true, 'message' => 'User not found'));
    }

    // Get Backend URL
    $backendurl = config_manager::get_backend_url();
    
    // If no Backend URL configured, return sample data
    if (empty($backendurl)) {
        // Return sample/demo data for testing
        switch ($datatype) {
            case 'profile':
                json_response(array(
                    'success' => true,
                    'data' => array(
                        'fullname' => fullname($user),
                        'email' => $user->email,
                        'username' => $user->username,
                        'student_id' => $user->idnumber ?: 'N/A',
                        'phone' => $user->phone1 ?: 'N/A',
                        'address' => $user->address ?: 'N/A',
                        'city' => $user->city ?: 'N/A'
                    )
                ));
                break;
            
            case 'academics':
                json_response(array(
                    'success' => true,
                    'data' => array(
                        'programs' => array(),
                        'message' => 'No programs found. Backend API not configured.'
                    )
                ));
                break;
            
            case 'finance':
                json_response(array(
                    'success' => true,
                    'data' => array(
                        'total_fees' => 0,
                        'amount_paid' => 0,
                        'balance_due' => 0,
                        'payments' => array(),
                        'message' => 'Backend API not configured.'
                    )
                ));
                break;
            
            case 'classes':
                json_response(array(
                    'success' => true,
                    'data' => array(
                        'classes' => array(),
                        'message' => 'Backend API not configured.'
                    )
                ));
                break;
            
            case 'grades':
                json_response(array(
                    'success' => true,
                    'data' => array(
                        'grades' => array(),
                        'message' => 'Backend API not configured.'
                    )
                ));
                break;
            
            default:
                json_response(array('error' => true, 'message' => 'Invalid data type'));
        }
    }

    // Route based on data type.
    switch ($datatype) {
        case 'profile':
            // Fetch student profile from Backend.
            $response = fetch_backend_data('/v1/extension/students/profile', array(
                'moodle_user_id' => $userid
            ));
            json_response($response);
            break;

        case 'academics':
            // Fetch academic data (programs, units, enrollments).
            $response = fetch_backend_data('/v1/extension/students/academics', array(
                'moodle_user_id' => $userid
            ));
            json_response($response);
            break;

        case 'finance':
            // Fetch financial data (payments, balance).
            $response = fetch_backend_data('/v1/extension/students/finance', array(
                'moodle_user_id' => $userid
            ));
            json_response($response);
            break;

        case 'classes':
            // Fetch classes data.
            $response = fetch_backend_data('/v1/extension/students/classes', array(
                'moodle_user_id' => $userid
            ));
            json_response($response);
            break;

        case 'grades':
            // Fetch grades data.
            $response = fetch_backend_data('/v1/extension/students/grades', array(
                'moodle_user_id' => $userid
            ));
            json_response($response);
            break;

        default:
            json_response(array('error' => true, 'message' => 'Invalid data type'));
    }

} catch (Exception $e) {
    json_response(array('error' => true, 'message' => $e->getMessage()));
}
