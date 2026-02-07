<?php
/**
 * Test script to verify grading observer registration and functionality
 * 
 * Upload this file to: /local/moodle_zoho_sync/test_grading_observer.php
 * Run via browser: https://your-moodle-site.com/local/moodle_zoho_sync/test_grading_observer.php
 * 
 * @package    local_moodle_zoho_sync
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Grading Observer Test</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
h1 { color: #333; }
h2 { color: #666; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
.code { background: #f8f9fa; padding: 10px; border-left: 3px solid #007bff; font-family: monospace; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #007bff; color: white; }
</style></head><body>";

echo "<h1>🧪 Grading Observer Test - Moodle-Zoho Integration</h1>";

// ==================== TEST 1: Check Plugin Version ====================
echo "<div class='test-section'>";
echo "<h2>📦 Test 1: Plugin Version</h2>";

$plugin = new stdClass();
require(__DIR__ . '/version.php');

echo "<table>";
echo "<tr><th>Property</th><th>Value</th></tr>";
echo "<tr><td>Component</td><td>{$plugin->component}</td></tr>";
echo "<tr><td>Version</td><td class='success'>{$plugin->version}</td></tr>";
echo "<tr><td>Release</td><td>{$plugin->release}</td></tr>";
echo "<tr><td>Requires</td><td>{$plugin->requires}</td></tr>";
echo "</table>";

// Check installed version in DB
$installed_version = $DB->get_field('config_plugins', 'value', ['plugin' => 'local_moodle_zoho_sync', 'name' => 'version']);
if ($installed_version == $plugin->version) {
    echo "<p class='success'>✅ Installed version matches: {$installed_version}</p>";
} else {
    echo "<p class='error'>❌ Version mismatch! Installed: {$installed_version}, File: {$plugin->version}</p>";
    echo "<p class='warning'>⚠️ Run upgrade.php to update!</p>";
}
echo "</div>";

// ==================== TEST 2: Check Observer Registration ====================
echo "<div class='test-section'>";
echo "<h2>👀 Test 2: Observer Registration</h2>";

$observers = $DB->get_records('events_handlers', ['component' => 'local_moodle_zoho_sync']);
$observer_count = count($observers);

echo "<p>Found <strong>{$observer_count}</strong> registered observers:</p>";

if ($observer_count > 0) {
    echo "<table>";
    echo "<tr><th>Event Name</th><th>Callback</th><th>Status</th></tr>";
    foreach ($observers as $obs) {
        echo "<tr>";
        echo "<td>{$obs->eventname}</td>";
        echo "<td>{$obs->handlerfunction}</td>";
        echo "<td class='success'>✅ Registered</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ No observers registered!</p>";
    echo "<div class='code'>";
    echo "Expected observers:<br>";
    echo "1. \\core\\event\\user_created<br>";
    echo "2. \\core\\event\\user_updated<br>";
    echo "3. \\core\\event\\user_enrolment_created<br>";
    echo "4. \\core\\event\\user_enrolment_deleted<br>";
    echo "5. \\mod_assign\\event\\submission_graded<br>";
    echo "6. \\core\\event\\user_graded<br>";
    echo "</div>";
}

// Check expected observers
$expected_observers = [
    '\\core\\event\\user_created' => '\\local_moodle_zoho_sync\\observer::user_created',
    '\\core\\event\\user_updated' => '\\local_moodle_zoho_sync\\observer::user_updated',
    '\\core\\event\\user_enrolment_created' => '\\local_moodle_zoho_sync\\observer::enrollment_created',
    '\\core\\event\\user_enrolment_deleted' => '\\local_moodle_zoho_sync\\observer::enrollment_deleted',
    '\\mod_assign\\event\\submission_graded' => '\\local_moodle_zoho_sync\\observer::submission_graded',
    '\\core\\event\\user_graded' => '\\local_moodle_zoho_sync\\observer::grade_updated',
];

echo "<h3>Expected vs Actual:</h3>";
echo "<table>";
echo "<tr><th>Event</th><th>Expected Callback</th><th>Status</th></tr>";
foreach ($expected_observers as $event => $callback) {
    $found = false;
    foreach ($observers as $obs) {
        if ($obs->eventname === $event && $obs->handlerfunction === $callback) {
            $found = true;
            break;
        }
    }
    echo "<tr>";
    echo "<td>{$event}</td>";
    echo "<td>{$callback}</td>";
    if ($found) {
        echo "<td class='success'>✅ Found</td>";
    } else {
        echo "<td class='error'>❌ Missing</td>";
    }
    echo "</tr>";
}
echo "</table>";

echo "</div>";

// ==================== TEST 3: Check Observer Class Exists ====================
echo "<div class='test-section'>";
echo "<h2>🔍 Test 3: Observer Class</h2>";

$observer_file = __DIR__ . '/classes/observer.php';
if (file_exists($observer_file)) {
    echo "<p class='success'>✅ Observer file exists: {$observer_file}</p>";
    
    require_once($observer_file);
    
    if (class_exists('\\local_moodle_zoho_sync\\observer')) {
        echo "<p class='success'>✅ Observer class loaded successfully</p>";
        
        $methods_to_check = [
            'user_created',
            'user_updated',
            'enrollment_created',
            'enrollment_deleted',
            'submission_graded',
            'grade_updated'
        ];
        
        echo "<table>";
        echo "<tr><th>Method</th><th>Status</th></tr>";
        foreach ($methods_to_check as $method) {
            $exists = method_exists('\\local_moodle_zoho_sync\\observer', $method);
            echo "<tr>";
            echo "<td>{$method}()</td>";
            if ($exists) {
                echo "<td class='success'>✅ Exists</td>";
            } else {
                echo "<td class='error'>❌ Missing</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Observer class not found</p>";
    }
} else {
    echo "<p class='error'>❌ Observer file not found</p>";
}

echo "</div>";

// ==================== TEST 4: Check Configuration ====================
echo "<div class='test-section'>";
echo "<h2>⚙️ Test 4: Plugin Configuration</h2>";

$config_items = [
    'backend_url' => 'Backend API URL',
    'enable_user_sync' => 'User Sync Enabled',
    'enable_enrollment_sync' => 'Enrollment Sync Enabled',
    'enable_grade_sync' => 'Grade Sync Enabled',
];

echo "<table>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
foreach ($config_items as $key => $label) {
    $value = get_config('local_moodle_zoho_sync', $key);
    echo "<tr>";
    echo "<td>{$label}</td>";
    echo "<td>" . ($value ?: '<em>not set</em>') . "</td>";
    if ($value) {
        echo "<td class='success'>✅</td>";
    } else {
        echo "<td class='warning'>⚠️ Not configured</td>";
    }
    echo "</tr>";
}
echo "</table>";

$backend_url = get_config('local_moodle_zoho_sync', 'backend_url');
if ($backend_url && $backend_url === 'http://localhost:8001') {
    echo "<p class='error'>❌ Backend URL is localhost - won't work in production!</p>";
    echo "<p>Change to: <code>http://YOUR_SERVER_IP:8000</code></p>";
}

echo "</div>";

// ==================== TEST 5: Test Webhook Connectivity ====================
echo "<div class='test-section'>";
echo "<h2>🌐 Test 5: Backend Connectivity</h2>";

$backend_url = get_config('local_moodle_zoho_sync', 'backend_url');
if ($backend_url) {
    echo "<p>Testing connection to: <strong>{$backend_url}</strong></p>";
    
    $health_url = rtrim($backend_url, '/') . '/health';
    $ch = curl_init($health_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200) {
        echo "<p class='success'>✅ Backend is reachable (HTTP {$http_code})</p>";
        echo "<div class='code'>" . htmlspecialchars($response) . "</div>";
    } else {
        echo "<p class='error'>❌ Cannot reach backend</p>";
        echo "<p>HTTP Code: {$http_code}</p>";
        if ($curl_error) {
            echo "<p>Error: {$curl_error}</p>";
        }
    }
} else {
    echo "<p class='warning'>⚠️ Backend URL not configured</p>";
}

echo "</div>";

// ==================== TEST 6: Check Recent Grades ====================
echo "<div class='test-section'>";
echo "<h2>📊 Test 6: Recent Grades (Sample Data)</h2>";

$recent_grades = $DB->get_records_sql(
    "SELECT g.id, g.userid, g.itemid, g.finalgrade, g.timemodified,
            u.firstname, u.lastname, gi.itemname
     FROM {grade_grades} g
     JOIN {user} u ON u.id = g.userid
     JOIN {grade_items} gi ON gi.id = g.itemid
     WHERE g.timemodified > :since
     ORDER BY g.timemodified DESC
     LIMIT 10",
    ['since' => time() - (7 * 24 * 60 * 60)] // Last 7 days
);

if ($recent_grades) {
    echo "<p>Found " . count($recent_grades) . " grades in last 7 days:</p>";
    echo "<table>";
    echo "<tr><th>Student</th><th>Item</th><th>Grade</th><th>Date</th></tr>";
    foreach ($recent_grades as $grade) {
        echo "<tr>";
        echo "<td>{$grade->firstname} {$grade->lastname}</td>";
        echo "<td>{$grade->itemname}</td>";
        echo "<td>{$grade->finalgrade}</td>";
        echo "<td>" . date('Y-m-d H:i:s', $grade->timemodified) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No grades found in last 7 days</p>";
}

echo "</div>";

// ==================== RECOMMENDATIONS ====================
echo "<div class='test-section'>";
echo "<h2>💡 Recommendations</h2>";

$issues = [];
if ($observer_count === 0) {
    $issues[] = "❌ No observers registered - Run uninstall/reinstall or manual SQL fix";
}
if ($installed_version != $plugin->version) {
    $issues[] = "⚠️ Version mismatch - Run php admin/cli/upgrade.php";
}
if (!get_config('local_moodle_zoho_sync', 'backend_url')) {
    $issues[] = "⚠️ Backend URL not configured - Set in plugin settings";
}
if ($backend_url === 'http://localhost:8001') {
    $issues[] = "❌ Backend URL is localhost - Change to server IP";
}

if (empty($issues)) {
    echo "<p class='success'>✅ All checks passed! Plugin should be working.</p>";
    echo "<p><strong>Next step:</strong> Create a test grade in Moodle and check:</p>";
    echo "<div class='code'>";
    echo "1. PHP error log for: === SUBMISSION_GRADED OBSERVER FIRED ===<br>";
    echo "2. Backend logs for: POST /api/v1/webhooks<br>";
    echo "</div>";
} else {
    echo "<p class='error'>Found " . count($issues) . " issues:</p>";
    echo "<ol>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ol>";
    
    echo "<h3>🔧 Quick Fix - Manual Observer Registration:</h3>";
    echo "<div class='code'>";
    echo "Run this SQL on your Moodle database:<br><br>";
    echo "DELETE FROM mdl_events_handlers WHERE component = 'local_moodle_zoho_sync';<br><br>";
    echo "Then purge all caches:<br>";
    echo "php admin/cli/purge_caches.php<br><br>";
    echo "Then run upgrade:<br>";
    echo "php admin/cli/upgrade.php --non-interactive<br>";
    echo "</div>";
}

echo "</div>";

// ==================== LOG CHECK ====================
echo "<div class='test-section'>";
echo "<h2>📝 Test 7: Check Error Logs</h2>";
echo "<p>Look for these patterns in your PHP error log:</p>";
echo "<div class='code'>";
echo "✅ Success patterns:<br>";
echo "- === USER_CREATED OBSERVER FIRED ===<br>";
echo "- === ENROLLMENT CREATED OBSERVER FIRED ===<br>";
echo "- === SUBMISSION_GRADED OBSERVER FIRED ===<br>";
echo "- === GRADE_UPDATED OBSERVER FIRED ===<br>";
echo "<br>";
echo "❌ Error patterns:<br>";
echo "- Error in grade_updated observer<br>";
echo "- Failed to send webhook<br>";
echo "- Connection refused<br>";
echo "</div>";

$apache_log = ini_get('error_log');
echo "<p>PHP error log location: <code>{$apache_log}</code></p>";
echo "</div>";

echo "<hr>";
echo "<p style='text-align: center; color: #666;'>Test completed at " . date('Y-m-d H:i:s') . "</p>";
echo "</body></html>";
