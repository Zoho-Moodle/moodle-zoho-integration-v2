# Moodle-Zoho Integration API - Documentation

## Overview
REST API لـ مزامنة بيانات الطلاب من Zoho إلى Moodle.

---

## Base URLs

**Local Development:**
```
http://127.0.0.1:8006
```

**Remote (ngrok):**
```
https://noncorrespondingly-tractile-ava.ngrok-free.dev
```

---

## Endpoints

### Phase 1: Student Sync

### 1. Health Check

**Request:**
```http
GET /v1/health
```

**Response:**
```json
{
  "status": "ok",
  "message": "API is healthy"
}
```

**Status:** `200 OK`

---

### 2. Sync Students

**Request:**
```http
POST /v1/sync/students
Content-Type: application/json
```

**Body:**
```json
{
  "data": [
    {
      "id": "zoho_id_123",
      "Name": "Student Name",
      "Academic_Email": "student@example.com",
      "Phone": "+1234567890",
      "Status": "Active"
    }
  ]
}
```

**Response Success:**
```json
{
  "status": "success",
  "idempotency_key": "abc123def456",
  "results": [
    {
      "zoho_student_id": "zoho_id_123",
      "status": "NEW",
      "message": "Student created"
    }
  ]
}
```

**Status:** `200 OK`

---

## Student States

| State | Meaning | Description |
|-------|---------|-------------|
| **NEW** | New Student | Student added to database |
| **UNCHANGED** | No Changes | Same data, no action taken |
| **UPDATED** | Data Changed | Student record updated with changes |
| **INVALID** | Invalid Data | Missing required fields |
| **duplicate_request** | Duplicate | Same request within 1 hour |

---

## Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|

---

## Phase 12: Moodle Integration ⭐ NEW

### Batch Ingestion Endpoints

#### 1. Import Moodle Users
```http
POST /v1/moodle/users
Content-Type: application/json
```

**Body:**
```json
{
  "users": [
    {
      "userid": 456,
      "username": "ali.ahmad",
      "email": "ali@example.com",
      "firstname": "Ali",
      "lastname": "Ahmad",
      "role": "student"
    }
  ]
}
```

#### 2. Import Moodle Enrollments
```http
POST /v1/moodle/enrollments
```

#### 3. Import Moodle Grades
```http
POST /v1/moodle/grades
```

**BTEC Grade Conversion:**
- 70-100 → Distinction
- 60-69 → Merit
- 40-59 → Pass
- 0-39 → Refer

### Real-time Webhook Endpoints

#### 4. User Created Event
```http
POST /v1/events/moodle/user_created
```

#### 5. User Updated Event
```http
POST /v1/events/moodle/user_updated
```

#### 6. Enrollment Created Event
```http
POST /v1/events/moodle/enrollment_created
```

#### 7. Grade Updated Event
```http
POST /v1/events/moodle/grade_updated
```

**Payload (Phase 1 - BTEC Compliance):**
- `grade_id` (int)
- `userid` (int)
- `user_username` (string)
- `user_email` (string)
- `user_fullname` (string)
- `itemid` (int)
- `iteminstance` (int, assignment id when item_module = assign)
- `item_name` (string)
- `item_type` (string)
- `item_module` (string) — e.g., `assign`
- `courseid` (int)
- `course_name` (string)
- `course_shortname` (string)
- `finalgrade_numeric` (float, 0–100 normalized)
- `raw_grade` (float, original Moodle grade value)
- `btec_grade` (string: Pass / Merit / Distinction / Refer) — computed in plugin using legacy logic
- `grademax` (float)
- `grademin` (float)
- `timecreated` (int)
- `timemodified` (int)
- `grader_userid` (int)
- `grader_fullname` (string)
- `grader_role` (string: `teacher`, `iv`, `other`) — legacy role detection (IV > Teacher)
- `learning_outcomes` (array of objects):
  - `code` (string, e.g., P1, M2, D3)
  - `level` (string, P/M/D)
  - `description` (string)
  - `achieved` (bool)

---

## Field Mapping Reference

For complete field mapping documentation, see:
- **[BACKEND_SYNC_MAPPING.md](BACKEND_SYNC_MAPPING.md)** - 420+ Zoho fields, sync workflows
- **[MOODLE_PLUGIN_ARCHITECTURE_AR.md](MOODLE_PLUGIN_ARCHITECTURE_AR.md)** - Moodle plugin architecture

### Key Fields (Moodle ↔ Backend ↔ Zoho)

| Moodle Field | Backend Field | Zoho Field | Purpose |
|--------------|---------------|------------|---------|
| id | string | ✅ | Unique Zoho identifier |
| Name | string | ✅ | Student full name |
| Academic_Email | string | ✅ | Student email |
| Phone | string | ❌ | Contact number |
| Status | string | ❌ | Active/Inactive/Pending |

---

## Response Fields

| Field | Type | Description |
|-------|------|-------------|
| status | string | success/error |
| idempotency_key | string | Request fingerprint (MD5 hash) |
| results | array | Array of sync results |
| zoho_student_id | string | Student ID |
| state | string | Sync state (NEW/UNCHANGED/UPDATED/INVALID) |
| message | string | Human-readable message |
| changes | object | Changed fields (for UPDATED only) |

---

## Example Workflows

### Example 1: Add New Student

**Request:**
```bash
curl -X POST http://127.0.0.1:8006/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "zoho_001",
      "Name": "Ahmed Mohammed",
      "Academic_Email": "ahmed@test.com",
      "Phone": "+201234567890",
      "Status": "Active"
    }]
  }'
```

**Response:**
```json
{
  "status": "success",
  "idempotency_key": "hash123",
  "results": [
    {
      "zoho_student_id": "zoho_001",
      "status": "NEW",
      "message": "Student created"
    }
  ]
}
```

---

### Example 2: Duplicate Request (Idempotency)

**Send Same Request Again (within 1 hour):**

**Response:**
```json
{
  "status": "ignored",
  "reason": "duplicate_request",
  "idempotency_key": "hash123"
}
```

---

### Example 3: Update Student

**Request (Same ID, Different Data):**
```bash
curl -X POST http://127.0.0.1:8006/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "data": [{
      "id": "zoho_001",
      "Name": "Ahmed Mohammed Ali",
      "Academic_Email": "ahmed@test.com",
      "Phone": "+201234567891",
      "Status": "Inactive"
    }]
  }'
```

**Response:**
```json
{
  "status": "success",
  "results": [
    {
      "zoho_student_id": "zoho_001",
      "status": "UPDATED",
      "message": "Student updated",
      "changes": {
        "phone": ["+201234567890", "+201234567891"],
        "status": ["Active", "Inactive"]
      }
    }
  ]
}
```

---

### Example 4: Batch Processing (3 Students)

**Request:**
```bash
curl -X POST http://127.0.0.1:8006/v1/sync/students \
  -H "Content-Type: application/json" \
  -d '{
    "data": [
      {
        "id": "batch_001",
        "Name": "Student 1",
        "Academic_Email": "student1@test.com",
        "Phone": "+201111111111",
        "Status": "Active"
      },
      {
        "id": "batch_002",
        "Name": "Student 2",
        "Academic_Email": "student2@test.com",
        "Phone": "+202222222222",
        "Status": "Active"
      },
      {
        "id": "batch_003",
        "Name": "Student 3",
        "Academic_Email": "student3@test.com",
        "Phone": "+203333333333",
        "Status": "Pending"
      }
    ]
  }'
```

**Response:**
```json
{
  "status": "success",
  "results": [
    {"zoho_student_id": "batch_001", "status": "NEW"},
    {"zoho_student_id": "batch_002", "status": "NEW"},
    {"zoho_student_id": "batch_003", "status": "NEW"}
  ]
}
```

---

## Error Handling

### Example: Missing Required Field

**Request:**
```json
{
  "data": [{
    "id": "test_001",
    "Name": "Test Student",
    "Phone": "+1234567890"
  }]
}
```

**Response:**
```json
{
  "status": "success",
  "results": [
    {
      "zoho_student_id": "test_001",
      "status": "INVALID",
      "message": "Missing required field: Academic_Email"
    }
  ]
}
```

---

## Headers

| Header | Value | Required |
|--------|-------|----------|
| Content-Type | application/json | ✅ |
| User-Agent | Any | ❌ |

---

## Rate Limiting

- ⚠️ No rate limiting currently implemented
- Idempotency: 1 hour TTL (default)

---

## Database Fields

Students stored with these fields:

```
id              - UUID primary key
zoho_id         - Unique Zoho identifier
academic_email  - Email address
username        - Derived from email
display_name    - Full name
phone           - Phone number
status          - Active/Inactive/Pending
fingerprint     - SHA256 hash for change detection
sync_status     - synced/pending
tenant_id       - Tenant identifier (default: "default")
created_at      - Record creation timestamp
updated_at      - Last update timestamp
```

---

## Testing Checklist

- [x] Health endpoint
- [x] NEW student
- [x] Duplicate detection
- [x] UPDATED student
- [x] BATCH processing
- [x] MIXED (new + existing)
- [x] ngrok remote access

---

## Support

For issues or questions:
1. Check logs: `app.log`
2. Verify database connection: `psql -U admin -d moodle_zoho`
3. Check `.env` configuration






