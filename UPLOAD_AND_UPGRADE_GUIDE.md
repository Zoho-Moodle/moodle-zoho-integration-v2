# 🚀 Student Dashboard - Upload & Upgrade Guide

## **Current Status:**
✅ Database: Cleaned and ready
✅ Critical tables: Restored (grade_queue, btec_templates, grade_ack)
✅ Code: Updated (version 2026021501)
✅ New tables: Ready to create (students, webhook_logs, sync_status)

---

## **📋 Files to Upload:**

### **1. Database Files:**
- `moodle_plugin/db/install.xml` → **/var/www/html/moodle/local/moodle_zoho_sync/db/**
- `moodle_plugin/db/upgrade.php` → **/var/www/html/moodle/local/moodle_zoho_sync/db/**

### **2. Version File:**
- `moodle_plugin/version.php` → **/var/www/html/moodle/local/moodle_zoho_sync/**

---

## **🔧 Step-by-Step Upgrade Process:**

### **Step 1: Upload Files via FileZilla/SFTP**

```
Local Path → Server Path
============================================
moodle_plugin/db/install.xml 
  → /var/www/html/moodle/local/moodle_zoho_sync/db/install.xml

moodle_plugin/db/upgrade.php 
  → /var/www/html/moodle/local/moodle_zoho_sync/db/upgrade.php

moodle_plugin/version.php 
  → /var/www/html/moodle/local/moodle_zoho_sync/version.php
```

**⚠️ Important:** Backup existing files before overwriting!

---

### **Step 2: Verify Files on Server**

```bash
ssh root@195.35.25.188

# Check version.php
cat /var/www/html/moodle/local/moodle_zoho_sync/version.php | grep "version"
# Should show: $plugin->version = 2026021501;

# Check upgrade.php exists
ls -lh /var/www/html/moodle/local/moodle_zoho_sync/db/upgrade.php

# Check install.xml exists
ls -lh /var/www/html/moodle/local/moodle_zoho_sync/db/install.xml
```

---

### **Step 3: Run Moodle Upgrade**

**Option A: Via Web Interface (Recommended)** 🌐

1. Open browser: `https://lms.abchorizon.com`
2. Login as admin
3. Moodle will detect version change automatically
4. You'll see: **"Moodle Upgrade - Database upgrade required"**
5. Click **"Upgrade Moodle database now"**
6. Wait for upgrade to complete
7. Look for: **"local_moodle_zoho_sync: Upgrade to version 2026021501"**

**Option B: Via Command Line** 💻

```bash
ssh root@195.35.25.188

# Run upgrade CLI
sudo -u www-data php /var/www/html/moodle/admin/cli/upgrade.php

# Expected output:
# == local_moodle_zoho_sync ==
# Upgrade to version 2026021501
# Creating table local_mzi_students
# Creating table local_mzi_webhook_logs
# Creating table local_mzi_sync_status
# Success!
```

---

### **Step 4: Verify New Tables Created**

```bash
# On server or local machine:
mysql -u moodle_user -p'BaBa112233@@' moodle_db -e "
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME LIKE 'mdl_local_mzi_%'
ORDER BY TABLE_NAME;"
```

**Expected Output:**
```
+------------------------------+------------+---------------------+
| TABLE_NAME                   | TABLE_ROWS | CREATE_TIME         |
+------------------------------+------------+---------------------+
| mdl_local_mzi_btec_templates |          0 | 2026-02-15 16:15:13 |
| mdl_local_mzi_config         |          X | ...                 |
| mdl_local_mzi_event_log      |          X | ...                 |
| mdl_local_mzi_grade_ack      |          0 | 2026-02-15 16:15:36 |
| mdl_local_mzi_grade_queue    |          8 | 2026-02-15 16:15:01 |
| mdl_local_mzi_students       |          0 | 2026-02-15 XX:XX:XX | ← NEW
| mdl_local_mzi_sync_history   |          X | ...                 |
| mdl_local_mzi_sync_status    |          0 | 2026-02-15 XX:XX:XX | ← NEW
| mdl_local_mzi_webhook_logs   |          0 | 2026-02-15 XX:XX:XX | ← NEW
+------------------------------+------------+---------------------+
```

---

### **Step 5: Verify Plugin Version**

```bash
mysql -u moodle_user -p'BaBa112233@@' moodle_db -e "
SELECT name, value 
FROM mdl_config_plugins 
WHERE plugin = 'local_moodle_zoho_sync' 
  AND name IN ('version', 'release');"
```

**Expected:**
```
+---------+------------+
| name    | value      |
+---------+------------+
| version | 2026021501 | ← UPDATED
| release | 4.0.0      | ← UPDATED
+---------+------------+
```

---

### **Step 6: Clear Moodle Cache**

```bash
ssh root@195.35.25.188

# Clear all caches
sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php

# Verify cache cleared
echo "Cache cleared successfully!"
```

---

## **✅ Success Checklist:**

- [ ] Files uploaded to server
- [ ] version.php shows 2026021501
- [ ] Upgrade ran without errors
- [ ] 3 new tables created (students, webhook_logs, sync_status)
- [ ] Plugin version in database = 2026021501
- [ ] Cache cleared
- [ ] No errors in Moodle admin dashboard

---

## **🚨 Troubleshooting:**

### **Problem: "Table already exists" error**

```sql
-- Check if tables exist
SELECT TABLE_NAME FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' 
  AND TABLE_NAME IN ('mdl_local_mzi_students', 'mdl_local_mzi_webhook_logs', 'mdl_local_mzi_sync_status');

-- If they exist, upgrade will skip creation (that's OK!)
```

### **Problem: Foreign key constraint fails**

```bash
# Check mdl_user table exists
mysql -u moodle_user -p'BaBa112233@@' moodle_db -e "DESCRIBE mdl_user;" | head -5
```

### **Problem: Permission denied**

```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/html/moodle/local/moodle_zoho_sync/
sudo chmod -R 755 /var/www/html/moodle/local/moodle_zoho_sync/
```

---

## **📊 Post-Upgrade Verification SQL:**

```sql
-- Run this to verify everything:
USE moodle_db;

-- 1. Check version
SELECT 'Plugin Version:' as info, value FROM mdl_config_plugins 
WHERE plugin = 'local_moodle_zoho_sync' AND name = 'version';

-- 2. Count tables
SELECT 'Total Tables:' as info, COUNT(*) as count 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'moodle_db' AND TABLE_NAME LIKE 'mdl_local_mzi_%';

-- 3. New tables structure
DESCRIBE mdl_local_mzi_students;
DESCRIBE mdl_local_mzi_webhook_logs;
DESCRIBE mdl_local_mzi_sync_status;

-- 4. Check indexes
SHOW INDEXES FROM mdl_local_mzi_students;
```

---

## **🎯 Next Steps After Upgrade:**

1. ✅ **Backend Webhook Handler** - Receive Zoho webhooks and populate tables
2. ✅ **Bulk Sync Script** - Initial sync of all students from Zoho
3. ✅ **Student Dashboard Frontend** - Display data to students
4. ✅ **Admin Monitoring** - Dashboard to monitor sync status

---

**Ready to upload? Let's go!** 🚀
