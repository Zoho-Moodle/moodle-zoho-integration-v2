# Student UI Pages - Upload Guide

## ✅ Files Ready for Upload

All student UI pages have been created and are ready to be uploaded to **srv793436**.

---

## 📋 Files to Upload

### 1. **Student UI Pages** (Upload to: `/public/local/moodle_zoho_sync/ui/student/`)

```
moodle_plugin/ui/student/profile.php        → Display student personal information
moodle_plugin/ui/student/programs.php       → Show program registrations and payment status
moodle_plugin/ui/student/classes.php        → Display enrolled classes with details
moodle_plugin/ui/student/requests.php       → Track student requests with status updates
moodle_plugin/ui/student/student_card.php   → Generate PDF student ID card
```

### 2. **Updated Core Files** (Upload to: `/public/local/moodle_zoho_sync/`)

```
moodle_plugin/lib.php                       → Updated navigation with 5-page submenu
moodle_plugin/lang/en/local_moodle_zoho_sync.php → Added language strings
```

---

## 🚀 Upload Instructions

### Option A: FileZilla (GUI)

1. **Connect to server**:
   - Host: `srv793436.hstgr.cloud`
   - Username: Your SSH username
   - Password: Your SSH password
   - Port: 22 (SFTP)

2. **Navigate to plugin directory**:
   ```
   Remote: /public/local/moodle_zoho_sync/
   ```

3. **Create student directory** (if not exists):
   ```
   Right-click → Create directory → "ui/student"
   ```

4. **Upload files**:
   - Upload all 5 files from `moodle_plugin/ui/student/` → remote `/public/local/moodle_zoho_sync/ui/student/`
   - Upload `lib.php` → remote `/public/local/moodle_zoho_sync/`
   - Upload `lang/en/local_moodle_zoho_sync.php` → remote `/public/local/moodle_zoho_sync/lang/en/`

5. **Set permissions**: Right-click each file → File permissions → `644`

### Option B: SSH Terminal

```bash
# Connect to server
ssh username@srv793436.hstgr.cloud

# Navigate to plugin directory
cd /public/local/moodle_zoho_sync/

# Create student UI directory
mkdir -p ui/student

# Exit SSH and upload from local machine
exit

# Upload files using SCP (run from local machine in project directory)
scp moodle_plugin/ui/student/*.php username@srv793436.hstgr.cloud:/public/local/moodle_zoho_sync/ui/student/
scp moodle_plugin/lib.php username@srv793436.hstgr.cloud:/public/local/moodle_zoho_sync/
scp moodle_plugin/lang/en/local_moodle_zoho_sync.php username@srv793436.hstgr.cloud:/public/local/moodle_zoho_sync/lang/en/

# Connect again to set permissions
ssh username@srv793436.hstgr.cloud

cd /public/local/moodle_zoho_sync/
chmod 644 ui/student/*.php
chmod 644 lib.php
chmod 644 lang/en/local_moodle_zoho_sync.php
```

---

## 🔧 Post-Upload Configuration

### 1. Clear Moodle Caches

After uploading all files, you **MUST** clear Moodle caches:

**Method A: Admin Interface**
1. Login as admin
2. Navigate to: **Site administration → Development → Purge all caches**
3. Click "Purge all caches" button

**Method B: CLI (faster)**
```bash
ssh username@srv793436.hstgr.cloud
cd /public/admin/cli
php purge_caches.php
```

### 2. Verify Navigation Menu

1. Login as a student user
2. Look for **"My Dashboard"** in the navigation menu (left sidebar or drawer)
3. Click to expand - should show 5 submenu items:
   - ✅ Profile
   - ✅ My Programs
   - ✅ My Classes
   - ✅ My Requests
   - ✅ Student Card

---

## 🧪 Testing Checklist

After upload, test each page:

### ✅ Profile Page (`profile.php`)
- [ ] Displays student personal information
- [ ] Shows status badge (Active/Inactive/Deleted)
- [ ] Displays nationality, DOB, contact info
- [ ] Navigation tabs work correctly

### ✅ My Programs Page (`programs.php`)
- [ ] Lists all program registrations
- [ ] Shows payment progress bars with percentage
- [ ] Calculates balance due correctly
- [ ] Status badges display properly (Active/Pending/Completed/Cancelled)

### ✅ My Classes Page (`classes.php`)
- [ ] Displays enrolled classes in card grid
- [ ] Shows instructor name and class dates
- [ ] Displays enrollment status
- [ ] Grade count indicator works

### ✅ My Requests Page (`requests.php`)
- [ ] Table lists all student requests
- [ ] Status badges color-coded correctly
- [ ] Modal popup shows full description
- [ ] Timestamps display properly

### ✅ Student Card Page (`student_card.php`)
- [ ] Card preview renders correctly
- [ ] Download button generates PDF
- [ ] PDF contains student info, photo placeholder, validity dates
- [ ] PDF downloads with correct filename

### ✅ Navigation
- [ ] "My Dashboard" appears in main navigation
- [ ] Submenu expands with 5 items
- [ ] Icons display correctly
- [ ] Active page highlighted

### ✅ Responsive Design
- [ ] All pages responsive on mobile
- [ ] Tables scroll horizontally on small screens
- [ ] Cards stack on mobile devices
- [ ] Navigation tabs wrap properly

### ✅ Edge Cases
- [ ] Student with no registrations: Shows "No programs found" message
- [ ] Student with no classes: Shows "Not enrolled in any classes" message
- [ ] Student with no requests: Shows "No requests submitted" message
- [ ] Student not linked to Zoho: Shows appropriate error message

---

## 📊 What Each Page Does

### 1. **Profile Page**
- Queries: `local_mzi_students` table by `moodle_user_id`
- Displays: Personal info, contact details, emergency contact, program info
- Features: Status badge, last updated timestamp, sync status alert

### 2. **Programs Page**
- Queries: `local_mzi_registrations` + LEFT JOIN `local_mzi_payments`
- Calculates: Total paid, balance due, payment percentage
- Features: Progress bars, payment summary, status badges

### 3. **Classes Page**
- Queries: `local_mzi_enrollments` INNER JOIN `local_mzi_classes`
- Counts: Number of grades per class
- Features: Card grid layout, instructor info, date ranges, grade indicators

### 4. **Requests Page**
- Queries: `local_mzi_requests` table
- Features: Status tracking, modal popups, color-coded badges, timeline

### 5. **Student Card Page**
- Queries: Student + current active program
- Generates: PDF using TCPDF (Moodle core library)
- Features: Landscape A6 card, photo placeholder, barcode-ready format

---

## 🔍 Common Issues & Solutions

### Issue: "Student not found" error
**Solution**: User must be linked to Zoho student via `moodle_user_id` field

### Issue: Navigation menu doesn't appear
**Solution**: 
1. Clear Moodle caches
2. Check capability: User must have `local/moodle_zoho_sync:viewdashboard`
3. Verify files uploaded to correct location

### Issue: "Permission denied" error
**Solution**: Check file permissions - should be `644` (read/write owner, read others)

### Issue: Pages load but show blank data
**Solution**: 
1. Check database has records in `local_mzi_*` tables
2. Verify foreign keys properly set (student_id, registration_id, class_id)
3. Check `moodle_user_id` matches logged-in user

### Issue: PDF download fails
**Solution**: 
1. Verify TCPDF library available (Moodle core includes it)
2. Check PHP memory limit (increase if needed)
3. Ensure no output before PDF generation

---

## 📝 Database Requirements

All pages query these tables:
- ✅ `mdl_local_mzi_students`
- ✅ `mdl_local_mzi_registrations`
- ✅ `mdl_local_mzi_payments`
- ✅ `mdl_local_mzi_classes`
- ✅ `mdl_local_mzi_enrollments`
- ✅ `mdl_local_mzi_grades`
- ✅ `mdl_local_mzi_requests`

Make sure these tables exist and have data synced from Zoho.

---

## 🎯 Next Steps After Upload

1. ✅ Upload all 7 files
2. ✅ Clear Moodle caches
3. ✅ Test navigation menu appears
4. ✅ Test each page with real student account
5. ✅ Verify responsive design on mobile
6. 📤 Deploy backend to production (if not done)
7. 📡 Configure Zoho CRM webhooks (21 endpoints)
8. 🧪 Test end-to-end: Zoho → Backend → Moodle → UI

---

## 📞 Support

If you encounter issues:
1. Check Moodle error logs: `/public/moodledata/log/php_error_log`
2. Check Apache/Nginx error logs
3. Enable debugging: **Site administration → Development → Debugging → DEVELOPER level**
4. Verify database records exist with correct foreign keys

---

**Created**: 2026-02-16  
**Plugin Version**: 2026021606  
**Backend Status**: 21 endpoints implemented (7 CREATE, 7 UPDATE, 7 DELETE)
