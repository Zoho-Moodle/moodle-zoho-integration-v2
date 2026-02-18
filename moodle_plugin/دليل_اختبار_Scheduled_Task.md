# دليل اختبار Scheduled Task - Sync Missing Grades

## الإعداد:

### 1. رفع Plugin للسيرفر
```bash
# رفع الملفات:
- moodle_plugin/classes/task/sync_missing_grades.php
- moodle_plugin/db/tasks.php
- moodle_plugin/lang/en/local_moodle_zoho_sync.php

# في Moodle:
Site Administration → Notifications → Upgrade Moodle database now
```

### 2. التحقق من التسجيل
```
Site Administration → 
Server → 
Scheduled tasks → 
Search: "sync_missing"

✅ يجب أن يظهر: "Sync missing grades (F and RR)"
✅ Schedule: 0 3 * * * (3 AM daily)
```

---

## السيناريوهات:

### **Test 1: F Grade (No Submission)**

#### **Setup:**
1. إنشاء assignment جديد
2. ضبط deadline = yesterday (أمس)
3. تسجيل طالب في الكورس
4. **عدم** تقديم submission

#### **التنفيذ:**
```
Scheduled tasks → 
Sync missing grades (F and RR) → 
Run now
```

#### **النتيجة المتوقعة:**
```
Output في Console:
========================================
Starting Missing Grades Sync Task
========================================
Found 1 assignments with passed deadlines
Processing Assignment: Test Assignment (ID: 123)
  Found 1 enrolled students
  → Student Ahmed Mohamed: No submission → F
========================================
Missing Grades Sync Complete
========================================
Total students processed: 1
F grades sent: 1
RR grades sent: 0
Errors: 0
========================================
```

✅ **Zoho يستقبل:**
```json
{
    "grade": "F",
    "status": "NO_SUBMISSION",
    "student_email": "ahmed@example.com",
    "assignment_name": "Test Assignment",
    "reason": "No submission before deadline"
}
```

---

### **Test 2: RR Grade (Double Refer)**

#### **Setup:**
1. إنشاء BTEC assignment
2. ضبط: "Attempts reopened" = Manually
3. طالب يقدم 1st attempt
4. مدرس يصحح: كل P criteria = No → **Refer**
5. مدرس يفتح 2nd attempt
6. طالب يقدم 2nd attempt
7. مدرس يصحح: كل P criteria = No → **Refer** مرة ثانية

#### **التنفيذ:**
```
Scheduled tasks → 
Sync missing grades (F and RR) → 
Run now
```

#### **النتيجة المتوقعة:**
```
Output:
Processing Assignment: Programming Basics (ID: 456)
  Found 1 enrolled students
  → Student Sara Ali: 2 Refer attempts → RR
========================================
F grades sent: 0
RR grades sent: 1
========================================
```

✅ **Zoho يستقبل:**
```json
{
    "grade": "RR",
    "status": "DOUBLE_REFER",
    "student_email": "sara@example.com",
    "attempts": 2,
    "attempt_details": [
        {"number": 0, "grade": 40, "btec_result": "R", "is_refer": true},
        {"number": 1, "grade": 42, "btec_result": "R", "is_refer": true}
    ],
    "reason": "Failed both 1st and 2nd attempts"
}
```

---

### **Test 3: Mixed Scenario**

#### **Setup:**
1. Assignment واحد
2. 10 طلاب:
   - 3 طلاب: ما قدموا → F
   - 2 طلاب: قدموا وحصلوا Pass → (بيرسل من Observer)
   - 1 طالب: Refer مرتين → RR
   - 4 طلاب: قدموا وما صححوا بعد → (ما بيرسل شي)

#### **النتيجة:**
```
F grades sent: 3   ← No submission
RR grades sent: 1  ← Double Refer
```

---

## التشخيص:

### **إذا ما اشتغل:**

#### 1. Check Cron:
```bash
# في السيرفر:
grep "Sync missing grades" /path/to/moodle/admin/cli/cron.log
```

#### 2. Check Database:
```sql
-- هل Task مسجل؟
SELECT * FROM mdl_task_scheduled 
WHERE classname = 'local_moodle_zoho_sync\\task\\sync_missing_grades';

-- هل في assignments عدا deadline؟
SELECT id, name, duedate, cutoffdate 
FROM mdl_assign 
WHERE duedate < UNIX_TIMESTAMP() AND duedate > 0;
```

#### 3. Run Manually:
```bash
# من Terminal:
cd /path/to/moodle
php admin/cli/scheduled_task.php --execute='\local_moodle_zoho_sync\task\sync_missing_grades'
```

---

## Notes:

- ✅ Task **آمن** - ما بيعدل بيانات Moodle
- ✅ **Idempotent** - يمكن تشغيله أكثر من مرة بأمان
- ✅ **Logged** - كل شي بيتسجل في Event Logger
- ⚠️ **Performance**: إذا في آلاف الطلاب، ممكن ياخذ وقت
- 💡 **Tip**: شغله يدوياً أول مرة للتأكد إنه شغال صح
