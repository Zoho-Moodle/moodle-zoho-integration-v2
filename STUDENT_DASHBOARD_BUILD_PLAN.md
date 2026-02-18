# 🎓 Student Dashboard - Build Plan (Phase 1: Basic Information)

## **Architecture Overview**

```
Zoho CRM (BTEC_Students)
    ↓ (Webhook on create/update/delete)
Backend API (Python FastAPI)
    ↓ (Process & validate)
Moodle Database (local_mzi_students table)
    ↓ (Read only)
Student Dashboard (PHP + JavaScript)
```

---

## **📋 Phase 1: Basic Information Tab**

### **✅ Completed: Database Schema**

**Tables Created:**
1. ✅ `mdl_local_mzi_students` - Student profile data from Zoho
2. ✅ `mdl_local_mzi_webhook_logs` - Webhook debugging logs
3. ✅ `mdl_local_mzi_sync_status` - Sync status tracking

**Zoho Fields Mapped:**
```json
{
  "Name": "student_id",
  "BTEC_Registration_Number": "registration_number",
  "First_Name": "first_name",
  "Last_Name": "last_name",
  "Email": "email",
  "Academic_Email": "academic_email",
  "Phone_Number": "phone_number",
  "Date_of_Birth": "date_of_birth",
  "Nationality": "nationality",
  "Address": "address",
  "City": "city",
  "Status": "status",
  "Academic_Program": "academic_program",
  "Registration_Date": "registration_date",
  "Study_Language": "study_language"
}
```

**Student Identification:**
- `academic_email` (Zoho) = `username` (Moodle)
- `Moodle_User_ID` (Zoho) = `id` (mdl_user)
- `zoho_student_id` = Unique Zoho CRM record ID

---

## **📝 Step-by-Step Implementation**

### **Step 1: Backend - Zoho Webhook Handler** 🔧

**File:** `backend/app/api/v1/endpoints/student_webhook.py`

**Responsibilities:**
1. Receive webhook from Zoho when BTEC_Students record changes
2. Validate webhook signature (Zoho security)
3. Extract student data from payload
4. Match student with Moodle user (via academic_email or Moodle_User_ID)
5. Insert/Update `mdl_local_mzi_students` table
6. Log webhook in `mdl_local_mzi_webhook_logs`

**Webhook Operations:**
- `create` → Insert new student
- `update` → Update existing student
- `delete` → Soft delete (or mark status = 'Deleted')

**Sample Payload:**
```json
{
  "module": "BTEC_Students",
  "operation": "update",
  "record": {
    "id": "3652397000012345678",
    "Name": "BTEC-2024-00123",
    "First_Name": "Ahmad",
    "Last_Name": "Hassan",
    "Academic_Email": "ahmad.hassan@abchorizon.com",
    "Moodle_User_ID": "42",
    "Status": "Active",
    ...
  }
}
```

---

### **Step 2: Backend - Initial Bulk Sync Command** 📥

**File:** `backend/app/cli/sync_students.py`

**Purpose:** Initial sync to pull ALL existing students from Zoho

**Command:**
```bash
python backend/app/cli/sync_students.py --full-sync
```

**Process:**
1. Query Zoho API: `GET /BTEC_Students?per_page=200`
2. For each student:
   - Match with Moodle user (via Academic_Email)
   - Insert into `mdl_local_mzi_students`
3. Update `mdl_local_mzi_sync_status` table

---

### **Step 3: Frontend - Student Dashboard Page** 🎨

**File:** `moodle_plugin/ui/student/index.php`

**URL:** `https://lms.abchorizon.com/local/moodle_zoho_sync/ui/student/`

**Features:**
- ✅ Check user is logged in and has 'student' role
- ✅ Fetch student data from `mdl_local_mzi_students` table
- ✅ Display Basic Information in clean Moodle-styled layout
- ✅ Handle case where student data doesn't exist yet

**UI Components:**
```
┌─────────────────────────────────────────┐
│  Student Dashboard                      │
├─────────────────────────────────────────┤
│  [Profile] [Classes] [Grades] [Finance] │
├─────────────────────────────────────────┤
│                                         │
│  📸 [Photo]    Student ID: BTEC-2024-123│
│               Ahmad Hassan               │
│               ahmad.hassan@abc.com      │
│                                         │
│  📋 Basic Information                   │
│  ├─ Registration Number: BTEC-2024-123  │
│  ├─ Date of Birth: 2000-05-15          │
│  ├─ Nationality: Syria                  │
│  ├─ Phone: +963 123 456 789            │
│  ├─ Address: Damascus, Syria           │
│  ├─ Status: Active                      │
│  └─ Program: Business Management        │
│                                         │
│  Last Updated: Feb 15, 2026 14:30      │
└─────────────────────────────────────────┘
```

---

### **Step 4: Data Access Layer** 🗃️

**File:** `moodle_plugin/classes/local/student_data.php`

**Purpose:** Clean API to fetch student data

**Methods:**
```php
class student_data {
    /**
     * Get student by Moodle user ID
     * @param int $userid
     * @return stdClass|null
     */
    public static function get_student_by_userid($userid);

    /**
     * Get student by Zoho ID
     * @param string $zoho_id
     * @return stdClass|null
     */
    public static function get_student_by_zoho_id($zoho_id);

    /**
     * Check if student data exists
     * @param int $userid
     * @return bool
     */
    public static function student_exists($userid);
}
```

---

## **🚀 Implementation Order**

### **Day 1: Backend Webhook Handler**
1. ✅ Create `backend/app/api/v1/endpoints/student_webhook.py`
2. ✅ Implement webhook validation (Zoho signature)
3. ✅ Implement database operations (insert/update)
4. ✅ Test with sample webhook payloads

### **Day 2: Backend Bulk Sync**
1. ✅ Create `backend/app/cli/sync_students.py`
2. ✅ Implement Zoho API pagination
3. ✅ Test full sync with real data

### **Day 3: Frontend Dashboard**
1. ✅ Create `moodle_plugin/ui/student/index.php`
2. ✅ Create CSS file: `styles/student_dashboard.css`
3. ✅ Create data layer: `classes/local/student_data.php`
4. ✅ Test display with synced data

### **Day 4: Testing & Refinement**
1. ✅ Test webhook create/update/delete
2. ✅ Test edge cases (no data, deleted student)
3. ✅ Add error handling
4. ✅ Add admin notifications

---

## **🔐 Security Checklist**

- ✅ Webhook signature validation (Zoho secret key)
- ✅ Moodle capability check: `local/moodle_zoho_sync:viewdashboard`
- ✅ User can only see their own data
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (Moodle s() and format_text())

---

## **📊 Testing Scenarios**

### **Scenario 1: New Student**
1. Create student in Zoho CRM
2. Zoho sends webhook → Backend
3. Backend inserts into `mdl_local_mzi_students`
4. Student logs into Moodle
5. Dashboard shows complete profile

### **Scenario 2: Update Student**
1. Admin updates phone number in Zoho
2. Webhook triggers update in Moodle DB
3. Student refreshes dashboard
4. New phone number displayed

### **Scenario 3: Deleted Student**
1. Admin deletes student in Zoho
2. Webhook marks status = 'Deleted' in Moodle
3. Student dashboard shows "Account inactive"

---

## **🎯 Next Steps After Phase 1**

### **Phase 2: Classes Tab**
- Sync enrollments from Zoho
- Display active classes, schedule, attendance

### **Phase 3: Grades Tab**
- Sync grades from Zoho
- Show BTEC grades with learning outcomes
- Grade acknowledgement feature

### **Phase 4: Finance Tab**
- Sync payment records
- Display fees, payments, balance

### **Phase 5: Admin Tools**
- Webhook monitoring dashboard
- Manual sync button
- Error reporting

---

## **📝 Notes**

- **Data Flow:** Zoho → Backend → Moodle DB (one-way sync)
- **No Direct API Calls:** Dashboard NEVER calls Zoho API directly
- **Performance:** All data served from local Moodle database
- **Fallback:** If webhook fails, daily cron job syncs incrementally
- **Monitoring:** Admin can view webhook logs and sync status

---

**Ready to start Step 1? 🚀**
