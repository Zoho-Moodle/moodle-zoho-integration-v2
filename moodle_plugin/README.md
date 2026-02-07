# Moodle-Zoho Integration Plugin

Real-time integration plugin that syncs Moodle data (users, enrollments, grades) to Backend API for Zoho CRM synchronization.

## 📋 Features

- ✅ **User Sync**: Automatically sync user created/updated events
- ✅ **Enrollment Sync**: Sync course enrollment events
- ✅ **Grade Sync**: Sync grade submissions (normalized to 0-100 scale)
- ✅ **Event-Driven**: Real-time webhooks using Moodle event observers
- ✅ **Retry Logic**: Automatic retry on failures (3 attempts)
- ✅ **Configurable**: Admin settings for URL, token, enable/disable per module
- ✅ **Logging**: Debug logging support

---

## 🚀 Installation

### Step 1: Upload Plugin Files

1. Copy the `moodle_plugin` folder to your Moodle installation:
   ```
   /path/to/moodle/local/moodle_zoho_sync/
   ```

2. Ensure proper permissions:
   ```bash
   chmod -R 755 /path/to/moodle/local/moodle_zoho_sync
   ```

### Step 2: Install via Moodle Admin

1. Log in to Moodle as administrator
2. Navigate to: **Site administration → Notifications**
3. Moodle will detect the new plugin
4. Click **"Upgrade Moodle database now"**
5. Complete the installation

### Step 3: Configure Plugin Settings

1. Navigate to: **Site administration → Plugins → Local plugins → Moodle-Zoho Integration**

2. Configure the following settings:

   | Setting | Value | Description |
   |---------|-------|-------------|
   | **Backend API URL** | `http://your-backend:8001` | Backend server URL |
   | **API Token** | (optional) | Authentication token if required |
   | **Enable User Sync** | ✅ | Sync user created/updated events |
   | **Enable Enrollment Sync** | ✅ | Sync enrollment created events |
   | **Enable Grade Sync** | ✅ | Sync grade updated events |
   | **Enable Debug Logging** | ❌ | Enable for testing only |
   | **Verify SSL Certificate** | ✅ | Disable only for development |

3. Click **"Save changes"**

---

## 🔧 Configuration Examples

### Local Development
```
Backend API URL: http://localhost:8001
API Token: (leave empty)
SSL Verify: No
Debug Logging: Yes
```

### Production (ngrok)
```
Backend API URL: https://your-subdomain.ngrok-free.dev
API Token: your_secret_token_here
SSL Verify: Yes
Debug Logging: No
```

### Production (VPS)
```
Backend API URL: https://api.yourdomain.com
API Token: your_secret_token_here
SSL Verify: Yes
Debug Logging: No
```

---

## 📡 Event Flow

### User Created/Updated
```
Moodle Event → Observer → Data Extractor → Webhook Sender → Backend API
(\core\event\user_created)   (extract_user_data)   (POST /api/v1/events/moodle/user_created)
```

**Data Sent:**
```json
{
  "userid": 123,
  "username": "john.doe",
  "email": "john@example.com",
  "firstname": "John",
  "lastname": "Doe",
  "phone1": "+1234567890",
  "city": "Amman",
  "country": "JO",
  "role": "student",
  "timecreated": 1706227200,
  "timemodified": 1706227200
}
```

### Enrollment Created
```
Moodle Event → Observer → Data Extractor → Webhook Sender → Backend API
(\core\event\user_enrolment_created)   (extract_enrollment_data)   (POST /api/v1/events/moodle/enrollment_created)
```

**Data Sent:**
```json
{
  "enrollment_id": 456,
  "userid": 123,
  "courseid": 10,
  "course_name": "Business Management",
  "course_shortname": "BM101",
  "status": 0,
  "enrol_method": "manual",
  "timestart": 1706227200,
  "timeend": 0,
  "timecreated": 1706227200
}
```

### Grade Updated
```
Moodle Event → Observer → Data Extractor → Webhook Sender → Backend API
(\core\event\user_graded)   (extract_grade_data)   (POST /api/v1/events/moodle/grade_updated)
```

**Data Sent:**
```json
{
  "grade_id": 789,
  "userid": 123,
  "itemid": 50,
  "item_name": "Assignment 1",
  "item_type": "mod",
  "item_module": "assign",
  "courseid": 10,
  "course_name": "Business Management",
  "finalgrade": 85.5,
  "raw_grade": 17.1,
  "grademax": 20.0,
  "grademin": 0.0,
  "timecreated": 1706227200,
  "timemodified": 1706227200
}
```

---

## 🧪 Testing

### Test User Sync

1. Create a new user in Moodle
2. Check Moodle logs: **Site administration → Reports → Logs**
3. Check Backend logs for received webhook
4. Verify data in Backend database:
   ```sql
   SELECT * FROM students WHERE moodle_user_id = '123';
   ```

### Test Enrollment Sync

1. Enroll a user in a course
2. Check Moodle logs
3. Verify in Backend:
   ```sql
   SELECT * FROM enrollments WHERE moodle_enrollment_id = 456;
   ```

### Test Grade Sync

1. Submit a grade for a student
2. Check logs
3. Verify in Backend:
   ```sql
   SELECT * FROM grades WHERE moodle_grade_id = 789;
   ```

### Debug Logging

Enable debug logging in plugin settings, then check:
```bash
tail -f /path/to/moodledata/error.log | grep "Moodle-Zoho Sync"
```

---

## 🐛 Troubleshooting

### Problem: Webhooks not being sent

**Solution:**
1. Check plugin is enabled: **Site administration → Plugins → Local plugins**
2. Verify settings: Backend URL, sync toggles enabled
3. Check Moodle error log for errors
4. Test Backend connectivity:
   ```bash
   curl -X POST http://your-backend:8001/api/v1/health
   ```

### Problem: SSL Certificate Error

**Solution:**
- For development: Disable "Verify SSL Certificate" in settings
- For production: Ensure valid SSL certificate on Backend server

### Problem: 401 Unauthorized

**Solution:**
- Check API Token is correctly configured in plugin settings
- Verify Backend expects the same token format

### Problem: Connection Timeout

**Solution:**
1. Check Backend server is running
2. Check firewall rules allow Moodle → Backend connection
3. Increase timeout in `webhook_sender.php` (line 135)

---

## 📁 File Structure

```
moodle_plugin/
├── version.php                          # Plugin metadata
├── settings.php                         # Admin settings page
├── db/
│   └── events.php                       # Event subscriptions
├── classes/
│   ├── observer.php                     # Event handlers
│   ├── data_extractor.php               # Data extraction from Moodle DB
│   └── webhook_sender.php               # HTTP client for Backend
├── lang/
│   └── en/
│       └── local_moodle_zoho_sync.php   # English language strings
└── README.md                            # This file
```

---

## 🔒 Security

- **API Token**: Store securely, never commit to version control
- **SSL Verification**: Always enable in production
- **IP Whitelist**: Consider restricting Backend API to Moodle server IP
- **HTTPS**: Use HTTPS for Backend API in production

---

## 📝 Backend API Endpoints

The plugin sends webhooks to these Backend endpoints:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/events/moodle/user_created` | POST | New user created |
| `/api/v1/events/moodle/user_updated` | POST | User updated |
| `/api/v1/events/moodle/enrollment_created` | POST | User enrolled in course |
| `/api/v1/events/moodle/grade_updated` | POST | Grade submitted |

---

## 🛠️ Development

### Enable Debug Mode

1. Enable "Debug Logging" in plugin settings
2. Check error log:
   ```bash
   tail -f /var/moodledata/error.log | grep "Moodle-Zoho Sync"
   ```

### Test Webhook Manually

```php
// In Moodle admin CLI or web
require_once(__DIR__ . '/local/moodle_zoho_sync/classes/webhook_sender.php');

$sender = new \local_moodle_zoho_sync\webhook_sender();
$response = $sender->send_user_created([
    'userid' => 123,
    'username' => 'test.user',
    'email' => 'test@example.com',
    'firstname' => 'Test',
    'lastname' => 'User',
    'role' => 'student'
]);

print_r($response);
```

---

## 📚 Resources

- **Backend API Documentation**: See `backend/BACKEND_SYNC_MAPPING.md`
- **Field Mapping**: See `backend/BACKEND_SYNC_MAPPING.md` Section 9
- **Moodle Events API**: https://docs.moodle.org/dev/Event_2
- **Moodle Plugin Development**: https://docs.moodle.org/dev/Local_plugins

---

## 📄 License

GNU GPL v3 or later

---

## 👥 Support

For issues or questions:
1. Check Backend logs
2. Check Moodle error log
3. Enable debug logging
4. Review `BACKEND_SYNC_MAPPING.md` for field mappings

---

**Version:** 1.0.0  
**Last Updated:** January 26, 2026  
**Maintainer:** ABC Horizon Development Team
