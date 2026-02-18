# Grade Operations Monitor - User Guide

## Overview
Enhanced monitoring page for the Hybrid Grading System, providing real-time visibility into all grade operations from both Observer (real-time syncs) and Scheduled Tasks (F & RR grades).

---

## 📊 Dashboard Features

### 1. **Navigation Tabs**
- **📊 Dashboard**: Overview of all operations (50 most recent)
- **⚡ Observer Operations**: Real-time syncs triggered when teachers submit grades
- **⏰ Scheduled Tasks**: F grades and RR grades created by scheduled task
- **❌ Failed Operations**: Operations that encountered errors with retry functionality

### 2. **Time Range Filter**
Select different time windows to view operations:
- **Last Hour** (1h) - Real-time monitoring
- **Last 24 Hours** (24h) - Default view
- **Last 7 Days** (7d) - Weekly overview
- **Last 30 Days** (30d) - Monthly summary

### 3. **Action Buttons**
- **🔄 Refresh**: Reload current view
- **📥 Export CSV**: Download operations data to spreadsheet

---

## 📈 Statistics Cards

### Real-time Metrics (Updates based on selected time range):

1. **Total Operations**
   - All grade sync operations (SYNCED + F_CREATED + RR_CREATED)
   - Shows total count + count in selected time range

2. **Observer Syncs** (Green Card)
   - Real-time syncs triggered by teacher grade submission
   - Status: `SYNCED`
   - Average sync time: ~400ms

3. **F Grades Created** (Orange Card)
   - F grades created by Scheduled Task
   - Status: `F_CREATED`
   - For students with grade=0 (01122 feedback)

4. **RR Grades Created** (Orange Card)
   - RR grades created by Scheduled Task
   - Status: `RR_CREATED`
   - For students with R on attempt 1 + no submission on attempt 2

5. **Failed Operations** (Red Card)
   - Operations with errors
   - Includes retry functionality

6. **Success Rate** (Green Card)
   - Percentage of successful operations
   - Formula: `(Total - Failed) / Total * 100`

---

## 📋 Views Details

### **Dashboard View** (📊)
Shows all operations from all sources in chronological order.

**Columns:**
- ID - Queue record identifier
- Status - Operation status (SYNCED, F_CREATED, RR_CREATED)
- Student - Full name + email
- Assignment - Assignment name
- Composite Key - Unique identifier (StudentID_AssignmentID_AttemptNumber)
- Zoho ID - Link to Zoho CRM record
- Modified - Last update timestamp
- Actions - Retry button (for failed operations)

**Features:**
- Shows last 50 operations in selected time range
- Error messages displayed in yellow row below failed operation
- Direct links to Zoho CRM records

---

### **Observer Operations View** (⚡)
Real-time syncs triggered when teachers submit grades.

**Columns:**
- ID - Queue record identifier
- Student - Full name + email
- Assignment - Assignment name
- Grade - Grade badge (F/R/P/M/D) with numeric value
- Composite Key - Unique identifier
- Zoho ID - Link to Zoho CRM record
- Sync Time - Exact sync timestamp
- Duration - Sync duration in milliseconds

**Features:**
- Only shows `status='SYNCED'` records
- Performance monitoring (sync duration)
- Grade color coding:
  - 🔴 **F** (Fail) - grade = 0
  - 🟡 **R** (Refer) - grade < 2
  - 🟢 **P** (Pass) - grade 2-2.99
  - 🔵 **M** (Merit) - grade 3-3.99
  - 🟣 **D** (Distinction) - grade ≥ 4

**Use Cases:**
- Monitor real-time sync performance
- Verify teacher-submitted grades are syncing immediately
- Check sync latency (should be <500ms)

---

### **Scheduled Tasks View** (⏰)
F and RR grades created by the scheduled task.

**Columns:**
- ID - Queue record identifier
- Status - F_CREATED or RR_CREATED
- Student - Full name + email
- Assignment - Assignment name
- Grade - F or RR badge with numeric value
- Composite Key - Unique identifier
- Zoho ID - Link to Zoho CRM record
- Created - Creation timestamp

**Features:**
- Shows `status='F_CREATED'` OR `status='RR_CREATED'`
- Separate color coding:
  - 🟠 **F_CREATED** - Failed grade detected (01122 feedback)
  - 🟠 **RR_CREATED** - Refer-Refer detected (R + no submission)

**Use Cases:**
- Verify scheduled task is detecting F grades correctly
- Monitor RR creation (R on attempt 1 + no submit on attempt 2)
- Check that duplicate RR detection is working

---

### **Failed Operations View** (❌)
Operations that encountered errors.

**Columns:**
- ID - Queue record identifier
- Status - Current status
- Student - Full name + email
- Assignment - Assignment name
- Composite Key - Unique identifier
- Error - Error message (truncated, click "...more" for full)
- Failed At - Error timestamp
- Retries - Retry count (X / 5)
- Actions - 🔄 Retry button

**Features:**
- Shows records with `error_message IS NOT NULL`
- Retry functionality (resets to SYNCED status)
- Full error message in popup
- Empty state shows ✅ success message when no errors

**Use Cases:**
- Identify and retry failed operations
- Monitor system health
- Diagnose integration issues (DUPLICATE_DATA, network errors, etc.)

---

## 🎨 Visual Elements

### Status Badges
- **SYNCED** (Green) - Observer real-time sync completed
- **F_CREATED** (Orange) - F grade created by scheduled task
- **RR_CREATED** (Orange) - RR grade created by scheduled task
- **ERROR** (Red) - Operation failed

### Grade Badges
- **F** (Red background) - Fail grade
- **R** (Yellow background) - Refer grade
- **RR** (Orange background) - Refer-Refer grade
- **P** (Green background) - Pass grade
- **M** (Blue background) - Merit grade
- **D** (Purple background) - Distinction grade

### Empty States
Beautiful empty state messages when no records found:
- 📭 No operations found
- ⚡ No observer operations
- 📝 No scheduled task operations
- ✅ No failed operations (success!)

---

## 🔧 Technical Details

### Database Table
Monitor reads from: `local_mzi_grade_queue`

### Key Fields:
- `id` - Primary key
- `status` - Operation status (SYNCED, F_CREATED, RR_CREATED)
- `composite_key` - StudentID_AssignmentID_AttemptNumber
- `zoho_record_id` - Zoho CRM record ID
- `grade_id` - Link to assign_grades table
- `student_id` - Link to user table
- `assignment_id` - Link to assign table
- `error_message` - Error details (if failed)
- `retry_count` - Number of retry attempts
- `timecreated` - Record creation time
- `timemodified` - Last modification time

### Performance
- Shows last 50 operations per view
- Optimized SQL queries with time filter
- Real-time statistics calculation
- No caching (always fresh data)

---

## 📝 Common Use Cases

### **1. Monitor Real-time Sync Performance**
1. Go to **Observer Operations** tab
2. Select **Last Hour** time range
3. Check **Duration** column - should be <500ms
4. Verify grades are syncing immediately after teacher submission

### **2. Check Scheduled Task Execution**
1. Go to **Scheduled Tasks** tab
2. Select **Last 24 Hours**
3. Look for new **F_CREATED** records (detected 01122 feedback)
4. Look for new **RR_CREATED** records (R + no submit)

### **3. Troubleshoot Failed Operations**
1. Go to **Failed Operations** tab
2. Click **...more** to see full error message
3. Common errors:
   - `DUPLICATE_DATA` - Zoho indexing delay (wait & retry)
   - `Network error` - Backend connection issue
   - `Student not found` - Student not in Zoho CRM
4. Click **🔄 Retry** to reprocess

### **4. Verify RR Detection**
1. Go to **Scheduled Tasks** tab
2. Filter for **RR_CREATED** status
3. Verify composite key ends with `_2` (attempt 2)
4. Check Zoho record to confirm:
   - Grade_Status = RR
   - Attempt_Number = 2
   - Original Feedback, Grader_Name preserved

### **5. Export Data for Analysis**
1. Select desired view (Dashboard/Observer/Scheduled/Failed)
2. Select time range
3. Click **📥 Export CSV**
4. Open in Excel/Google Sheets for analysis

---

## 🚨 Alerts & Indicators

### **High Success Rate** (>95%)
- ✅ System healthy
- Majority of operations succeeding

### **Low Success Rate** (<90%)
- ⚠️ Check Failed Operations view
- Review error messages
- Contact system administrator

### **Slow Sync Duration** (>1000ms)
- ⚠️ Backend performance issue
- Check Backend logs
- Verify network latency

### **No RR Detection** (Expected but none created)
- Check student has R on attempt 1 (grade <2)
- Check attempt 2 has no submission (grade=-1)
- Verify scheduled task is running
- Check duplicate detection not blocking

---

## 📊 Comparison with Event Logs

### **Event Logs Page** (Generic)
- All webhook events (users, enrollments, grades, classes)
- Broader scope
- Technical details (request/response payloads)

### **Grade Operations Monitor** (Specialized)
- **Grade operations only**
- Real-time statistics
- Performance monitoring
- User-friendly interface
- Retry functionality
- Time-range filtering

**Recommendation:** Use Grade Monitor for daily operations monitoring, Event Logs for technical debugging.

---

## 🎯 Best Practices

1. **Check Dashboard daily** - Monitor overall health
2. **Review Failed Operations** - Retry failed records promptly
3. **Monitor Observer sync time** - Should be <500ms
4. **Verify RR creation** - Ensure no duplicates being sent
5. **Export weekly reports** - Track trends over time
6. **Use time filters effectively** - 1h for real-time, 7d for weekly review

---

## 📞 Support

**Issues with:**
- **Observer syncs** → Check Backend connection, verify webhook endpoint
- **F grades** → Verify scheduled task running, check 01122 in feedback
- **RR grades** → Confirm R on attempt 1, no submit on attempt 2
- **Duplicate DATA** → Zoho indexing delay, retry after 30 seconds
- **Performance** → Check Backend logs, verify network latency

**Contact:** System Administrator

---

## 🔄 Version History

### **v1.0** (Current)
- ✅ Real-time statistics with time-range filtering
- ✅ Multi-view interface (Dashboard, Observer, Scheduled, Failed)
- ✅ Grade badges with color coding
- ✅ Performance monitoring (sync duration)
- ✅ Retry functionality for failed operations
- ✅ CSV export
- ✅ Empty state messages
- ✅ Responsive design
- ✅ Direct Zoho record links

---

## 📚 Related Documentation

- [ARCHITECTURE.md](../../../backend/ARCHITECTURE.md) - System architecture overview
- [PHASE4_COMPLETE.md](../../../backend/PHASE4_COMPLETE.md) - RR implementation details
- [observer.php](../../classes/observer.php) - Observer code
- [sync_missing_grades.php](../../classes/task/sync_missing_grades.php) - Scheduled task code
- [webhooks.py](../../../backend/app/api/v1/endpoints/webhooks.py) - Backend webhook handler

---

**Last Updated:** January 2025  
**Author:** ABC Horizon Development Team
