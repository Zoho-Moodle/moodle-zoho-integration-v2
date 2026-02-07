# Moodle Plugin Integration Guide

## Overview

This guide explains how to create a Moodle plugin that sends real-time events to the Backend API.

## Webhook Endpoints

All webhook endpoints are under `/api/v1/events/moodle/`:

- `POST /events/moodle/user_created` - When a user is created
- `POST /events/moodle/user_updated` - When a user profile is updated  
- `POST /events/moodle/user_enrolled` - When a user enrolls in a course
- `POST /events/moodle/grade_updated` - When a grade is submitted/updated

## Moodle Plugin Structure

```
local/backend_sync/
├── version.php
├── db/
│   └── events.php
├── classes/
│   └── observer.php
└── lang/
    └── en/
        └── local_backend_sync.php
```

## 1. version.php

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_backend_sync';
$plugin->version = 2026012600;
$plugin->requires = 2021051700; // Moodle 3.11+
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v1.0.0';
```

## 2. db/events.php

```php
<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback' => 'local_backend_sync_observer::user_created',
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback' => 'local_backend_sync_observer::user_updated',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => 'local_backend_sync_observer::user_enrolled',
    ],
    [
        'eventname' => '\core\event\user_graded',
        'callback' => 'local_backend_sync_observer::grade_updated',
    ],
];
```

## 3. classes/observer.php

```php
<?php
defined('MOODLE_INTERNAL') || die();

class local_backend_sync_observer {
    
    private static function send_webhook($endpoint, $data) {
        $config = get_config('local_backend_sync');
        $backend_url = $config->backend_url ?? 'http://localhost:8001';
        $api_token = $config->api_token ?? '';
        
        $url = $backend_url . '/api/v1/events/moodle/' . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Moodle-Token: ' . $api_token,
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            error_log("Backend sync failed: HTTP $http_code - $response");
        }
        
        return $http_code === 200;
    }
    
    public static function user_created(\core\event\user_created $event) {
        global $DB;
        
        $user = $DB->get_record('user', ['id' => $event->relateduserid]);
        if (!$user) {
            return;
        }
        
        $data = [
            'eventname' => $event->eventname,
            'userid' => $user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'idnumber' => $user->idnumber,
            'phone1' => $user->phone1,
            'city' => $user->city,
            'country' => $user->country,
            'suspended' => (bool)$user->suspended,
            'deleted' => (bool)$user->deleted,
            'timecreated' => $user->timecreated,
            'timemodified' => $user->timemodified,
        ];
        
        self::send_webhook('user_created', $data);
    }
    
    public static function user_updated(\core\event\user_updated $event) {
        global $DB;
        
        $user = $DB->get_record('user', ['id' => $event->relateduserid]);
        if (!$user) {
            return;
        }
        
        $data = [
            'eventname' => $event->eventname,
            'userid' => $user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'idnumber' => $user->idnumber,
            'phone1' => $user->phone1,
            'city' => $user->city,
            'country' => $user->country,
            'suspended' => (bool)$user->suspended,
            'deleted' => (bool)$user->deleted,
            'timecreated' => $user->timecreated,
            'timemodified' => $user->timemodified,
        ];
        
        self::send_webhook('user_updated', $data);
    }
    
    public static function user_enrolled(\core\event\user_enrolment_created $event) {
        global $DB;
        
        $enrolment = $DB->get_record('user_enrolments', ['id' => $event->objectid]);
        if (!$enrolment) {
            return;
        }
        
        $enrol = $DB->get_record('enrol', ['id' => $enrolment->enrolid]);
        
        $data = [
            'eventname' => $event->eventname,
            'enrollmentid' => $enrolment->id,
            'userid' => $enrolment->userid,
            'courseid' => $enrol->courseid,
            'roleid' => 5, // Student role
            'status' => $enrolment->status,
            'timestart' => $enrolment->timestart,
            'timeend' => $enrolment->timeend,
            'timecreated' => $enrolment->timecreated,
        ];
        
        self::send_webhook('user_enrolled', $data);
    }
    
    public static function grade_updated(\core\event\user_graded $event) {
        global $DB;
        
        $grade = $DB->get_record('grade_grades', ['id' => $event->objectid]);
        if (!$grade) {
            return;
        }
        
        $grade_item = $DB->get_record('grade_items', ['id' => $grade->itemid]);
        
        $data = [
            'eventname' => $event->eventname,
            'gradeid' => $grade->id,
            'userid' => $grade->userid,
            'itemid' => $grade->itemid,
            'itemname' => $grade_item->itemname ?? '',
            'finalgrade' => $grade->finalgrade,
            'feedback' => $grade->feedback ?? '',
            'grader' => $grade->usermodified,
            'timecreated' => $grade->timecreated,
            'timemodified' => $grade->timemodified,
        ];
        
        self::send_webhook('grade_updated', $data);
    }
}
```

## 4. lang/en/local_backend_sync.php

```php
<?php
$string['pluginname'] = 'Backend Sync';
$string['backend_url'] = 'Backend API URL';
$string['backend_url_desc'] = 'URL of the backend API (e.g., http://localhost:8001)';
$string['api_token'] = 'API Token';
$string['api_token_desc'] = 'Authentication token for the backend API';
```

## Installation

1. Copy the plugin to `moodle/local/backend_sync/`
2. Visit Site administration → Notifications
3. Click "Upgrade Moodle database now"
4. Configure settings:
   - Site administration → Plugins → Local plugins → Backend Sync
   - Set Backend API URL (e.g., `http://localhost:8001`)
   - Set API Token (if required)

## Testing

After installation, any of these actions will trigger webhooks:

1. **Create a user** → Webhook to `/events/moodle/user_created`
2. **Update user profile** → Webhook to `/events/moodle/user_updated`
3. **Enroll user in course** → Webhook to `/events/moodle/user_enrolled`
4. **Submit/update grade** → Webhook to `/events/moodle/grade_updated`

## Monitoring

Check Moodle logs for webhook errors:
- Site administration → Reports → Logs
- Filter by "Backend sync"

Check backend logs for received events:
- Backend API logs will show: "📥 Received [event_type] event"

## Troubleshooting

**Problem:** Webhooks not being sent

**Solution:**
- Check Moodle cron is running: `php admin/cli/cron.php`
- Check plugin is installed: Site admin → Plugins → Plugin overview
- Verify backend URL in settings

**Problem:** HTTP errors (404, 500)

**Solution:**
- Verify backend server is running: `http://localhost:8001/api/v1/health`
- Check backend URL is correct in plugin settings
- Check firewall/network connectivity

## Production Deployment

For production:

1. Use HTTPS for backend URL
2. Set up proper API token authentication
3. Implement retry logic for failed webhooks
4. Monitor webhook queue
5. Set up alerting for failed syncs

## Next Steps

After Moodle plugin is installed:

1. Test all webhook events
2. Verify data appears in backend database
3. Implement Backend → Zoho sync
4. Update Zoho tracking fields
5. Set up monitoring and alerts
