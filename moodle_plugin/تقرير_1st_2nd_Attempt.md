# تقرير شامل: 1st & 2nd Attempt في BTEC Grading
# Comprehensive Report: 1st & 2nd Attempt in Assignment Submission & Grading

**التاريخ / Date:** February 9, 2026  
**المجلدات المدروسة / Folders Analyzed:**
- `gradingform_btec/` - BTEC Advanced Grading Method Plugin
- `report_advancedgrading/` - Advanced Grading Report Plugin

---

## 📋 الخلاصة التنفيذية / Executive Summary

بعد فحص شامل لكل ملفات `gradingform_btec` و `report_advancedgrading`، **النتيجة المفاجئة:**

### ❌ **لا يوجد دعم أصلي لـ 1st & 2nd Attempt في BTEC Plugin**

**The BTEC grading plugin does NOT have built-in support for tracking 1st and 2nd attempts.**

---

## 1️⃣ ما وجدناه / What We Found

### **في gradingform_btec/ (BTEC Plugin):**

#### ✅ **الجداول الموجودة / Existing Tables:**

```sql
-- جدول المعايير (P1, P2, M1, D1, etc.)
gradingform_btec_criteria (
    id,
    definitionid,           -- ربط بالـ grading definition
    sortorder,
    shortname,              -- P1, M1, D1
    description,            -- وصف المعيار للطلاب
    descriptionformat,
    descriptionmarkers,     -- وصف للمصححين
    descriptionmarkersformat
)

-- جدول التصحيح (نتائج كل طالب لكل معيار)
gradingform_btec_fillings (
    id,
    instanceid,             -- ربط بـ grading_instances
    criterionid,            -- أي معيار (P1, M1, D1)
    remark,                 -- ملاحظات المدرس
    remarkformat,
    score                   -- 0 (No) أو 1 (Yes) - تم الإنجاز؟
)

-- جدول التعليقات المتكررة
gradingform_btec_comments (
    id,
    definitionid,
    sortorder,
    description,
    descriptionformat
)
```

#### ❌ **ما لا يوجد / What's Missing:**

```sql
-- لا يوجد:
attemptnumber           -- رقم المحاولة
attempt_date            -- تاريخ المحاولة
resubmission_flag       -- هل هذا resubmission؟
first_submission_date
second_submission_date
```

---

### **في Moodle Core (assign_grades):**

Moodle الأصلي **يدعم Attempts** في جدول `assign_grades`:

```sql
-- جدول assign_grades في Moodle Core
assign_grades (
    id,
    assignment,             -- أي واجب
    userid,                 -- أي طالب
    timecreated,
    timemodified,
    grader,                 -- من صحح
    grade,                  -- الدرجة
    attemptnumber           -- ✅ رقم المحاولة (0, 1, 2, ...)
)
```

**✅ Moodle يسمح بـ Multiple Attempts:**
- المدرس يمكنه ضبط: "Allow unlimited attempts"
- كل محاولة جديدة تُخزّن برقم `attemptnumber` جديد
- التصحيح يمكن أن يكون لكل محاولة على حدة

---

## 2️⃣ كيف يعمل التصحيح حالياً / How Grading Works Currently

### **الارتباط بين الجداول / Table Relationships:**

```
Assignment Submission (Moodle)
        ↓
assign_grades (attemptnumber ✅)
        ↓
grading_instances (instanceid) ← ربط واحد لواحد
        ↓
gradingform_btec_fillings (score, remark) ← نتيجة كل معيار (P1, M1, D1)
```

### **المشكلة / The Problem:**

```
grading_instances
└─ لا يحتوي على attemptnumber ❌
└─ فقط يرتبط بـ assign_grades.id
└─ إذا الطالب قدّم محاولة ثانية:
   - يتم إنشاء assign_grades جديد (attemptnumber = 1)
   - ولكن grading_instances قديم يبقى مرتبط بالمحاولة الأولى
   - أو يتم حذفه وإنشاء واحد جديد (يضيع التاريخ)
```

---

## 3️⃣ السيناريو الواقعي / Real-World Scenario

### **مثال: أحمد يقدم واجب BTEC مرتين**

#### **المحاولة الأولى (1st Attempt):**

```
1. أحمد يقدم الواجب → submission_id = 123
2. المدرس يصحح:
   - assign_grades: id=1, userid=Ahmed, attemptnumber=0, grade=40
   - grading_instances: id=1, itemid=1 (ربط بـ assign_grades.id)
   - gradingform_btec_fillings:
     * P1: score=1 (Yes) ✅
     * P2: score=0 (No)  ❌ - Refer
     * M1: score=0 (No)  ❌
   
3. النتيجة: Refer (لأنه ما أنجز كل Pass criteria)
```

#### **المحاولة الثانية (2nd Attempt - Resubmission):**

```
4. أحمد يحسّن ويعيد تقديم
5. Moodle ينشئ:
   - assign_grades: id=2, userid=Ahmed, attemptnumber=1, grade=70
   - grading_instances: id=2, itemid=2
   - gradingform_btec_fillings (NEW):
     * P1: score=1 (Yes) ✅
     * P2: score=1 (Yes) ✅ - حل المشكلة
     * M1: score=1 (Yes) ✅
   
6. النتيجة: Merit
```

### **ماذا يحدث للبيانات القديمة؟ / What Happens to Old Data?**

#### **خيار 1: Moodle Mode = "Replace Previous Attempts"**
```
- grading_instances القديم يُحذف ❌
- gradingform_btec_fillings القديم يُحذف ❌
- تضيع بيانات المحاولة الأولى
- لا يمكن عمل مقارنة بين 1st و 2nd Attempt
```

#### **خيار 2: Moodle Mode = "Keep All Attempts"**
```
- grading_instances القديم يبقى موجود ✅
- grading_instances الجديد يُنشأ ✅
- لكن: ما فيه طريقة مباشرة لمعرفة أيهما 1st و أيهما 2nd
- الحل: من خلال assign_grades.attemptnumber
```

---

## 4️⃣ كيف نعرف إذا كان 1st أو 2nd Attempt؟ / How to Identify Attempts?

### **الطريقة الوحيدة الموثوقة / The Only Reliable Method:**

```sql
-- الاستعلام الصحيح لجلب كل المحاولات مع أرقامها:
SELECT 
    ag.id AS grade_id,
    ag.userid,
    ag.attemptnumber,           -- ✅ هنا الرقم
    ag.timemodified AS attempt_date,
    ag.grade,
    
    gi.id AS instance_id,
    
    gbf.criterionid,
    gbc.shortname AS criterion,  -- P1, M1, D1
    gbf.score,                   -- 0 or 1
    gbf.remark
    
FROM {assign_grades} ag
LEFT JOIN {grading_instances} gi ON gi.itemid = ag.id
LEFT JOIN {gradingform_btec_fillings} gbf ON gbf.instanceid = gi.id
LEFT JOIN {gradingform_btec_criteria} gbc ON gbc.id = gbf.criterionid

WHERE ag.assignment = :assignmentid
  AND ag.userid = :userid
  
ORDER BY ag.attemptnumber ASC;  -- من الأقدم للأحدث
```

**النتيجة:**
```
+----------+---------+----------------+-------------+-------+------------+
| grade_id | userid  | attemptnumber  | criterion   | score | remark     |
+----------+---------+----------------+-------------+-------+------------+
| 1        | Ahmed   | 0              | P1          | 1     | Good work  | ← 1st Attempt
| 1        | Ahmed   | 0              | P2          | 0     | Incomplete |
| 1        | Ahmed   | 0              | M1          | 0     | Not met    |
+----------+---------+----------------+-------------+-------+------------+
| 2        | Ahmed   | 1              | P1          | 1     | Excellent  | ← 2nd Attempt
| 2        | Ahmed   | 1              | P2          | 1     | Fixed!     |
| 2        | Ahmed   | 1              | M1          | 1     | Well done  |
+----------+---------+----------------+-------------+-------+------------+
```

---

## 5️⃣ المعلومات المتعلقة بـ Attempts / Attempt-Related Information

### **أين تُخزّن البيانات؟ / Where is Data Stored?**

| **المعلومة** | **الجدول** | **الحقل** | **ملاحظات** |
|--------------|------------|-----------|-------------|
| رقم المحاولة | `assign_grades` | `attemptnumber` | ✅ 0 = first, 1 = second |
| تاريخ المحاولة | `assign_grades` | `timemodified` | ✅ Unix timestamp |
| الدرجة النهائية | `assign_grades` | `grade` | ✅ رقمية |
| من صحح | `assign_grades` | `grader` | ✅ user ID |
| تفاصيل BTEC | `gradingform_btec_fillings` | `score, remark` | ✅ لكل criterion |
| ربط المحاولة بالتصحيح | `grading_instances` | `itemid` | ✅ → assign_grades.id |

### **ما لا يُخزّن / What is NOT Stored:**

| **المعلومة المفقودة** | **السبب** | **الحل المقترح** |
|-----------------------|-----------|-------------------|
| ❌ Attempt Status (1st/2nd/3rd) | لا يوجد حقل | استخدام `attemptnumber` |
| ❌ Resubmission Flag | لا يوجد حقل | المقارنة: `attemptnumber > 0` |
| ❌ First Submission Grade | لا يوجد حقل منفصل | Query: `WHERE attemptnumber = 0` |
| ❌ Resubmission Grade | لا يوجد حقل منفصل | Query: `WHERE attemptnumber = 1` |
| ❌ Improvement ΔGrade | لا يتم حسابه | `grade(attempt=1) - grade(attempt=0)` |

---

## 6️⃣ إعدادات Assignment في Moodle / Assignment Settings

### **كيف يتم تفعيل Multiple Attempts؟ / How to Enable Multiple Attempts?**

في إعدادات Assignment:

```
Submission settings:
├─ Require students click submit button: Yes
├─ Require that students accept the submission statement: Yes
├─ Attempts reopened: Manually (by teacher) ✅
│                    : Automatically until pass
│                    : Never (single attempt only)
├─ Maximum attempts: Unlimited
└─ Resubmit for marking: Yes ✅
```

**الفرق بين الخيارات:**

| **Option** | **السلوك** | **الحالة في Database** |
|------------|------------|------------------------|
| **Manually** | المدرس يفتح محاولة جديدة | `attemptnumber` يزيد |
| **Automatically until pass** | تلقائياً إذا رسب | `attemptnumber` يزيد |
| **Never** | محاولة واحدة فقط | `attemptnumber = 0` دائماً |

---

## 7️⃣ كيف يتعامل BTEC Plugin مع Attempts؟ / How BTEC Handles Attempts?

### **الكود الحالي / Current Code Behavior:**

من `gradingform_btec/lib.php` (line 610-620):

```php
public function get_or_create_instance($itemid, $raterid, $userid) {
    global $DB;
    
    // يحاول جلب instance موجود
    $instance = $DB->get_record('grading_instances', [
        'raterid' => $raterid,
        'definitionid' => $this->definition->id,
        'itemid' => $itemid  // ← هنا المشكلة: كل محاولة لها itemid مختلف
    ]);
    
    if ($instance) {
        return $this->get_instance($instance);
    }
    
    // إذا ما لقى، ينشئ واحد جديد
    return $this->create_instance($userid, $itemid);
}
```

**التحليل:**
- كل `itemid` = `assign_grades.id` مختلف لكل محاولة
- إذاً كل محاولة → `grading_instances` جديد
- ❌ **لا يوجد ربط بين المحاولات في BTEC Plugin**

---

## 8️⃣ التقارير الموجودة / Existing Reports

### **في report_advancedgrading/classes/btec.php:**

```php
public function get_data(\cm_info $cm): array {
    $sql = "SELECT 
                gbf.id AS ggfid, 
                criteria.shortname, 
                gbf.score,
                gbf.remark,
                ag.id,           -- ← assign_grades.id
                ag.grade,
                stu.firstname, 
                stu.lastname
                
            FROM {assign_grades} ag
            JOIN {grading_instances} gin ON gin.itemid = ag.id
            JOIN {gradingform_btec_fillings} gbf ON gbf.instanceid = gin.id
            JOIN {gradingform_btec_criteria} criteria ON criteria.id = gbf.criterionid
            
            WHERE cm.id = :cmid 
              AND gin.status = :instancestatus
              
            ORDER BY lastname, firstname, criteria.sortorder ASC";
}
```

**ما يعرضه التقرير:**
- ✅ أسماء الطلاب
- ✅ كل criterion (P1, M1, D1)
- ✅ النتيجة (score: 0 or 1)
- ✅ الملاحظات (remark)
- ✅ الدرجة النهائية

**ما لا يعرضه:**
- ❌ رقم المحاولة (`attemptnumber`)
- ❌ التاريخ
- ❌ مقارنة بين 1st و 2nd Attempt
- ❌ Improvement tracking

---

## 9️⃣ الحلول المقترحة / Proposed Solutions

### **Option 1: تعديل التقرير الموجود (الأسهل)**

إضافة `attemptnumber` للتقرير:

```php
// تعديل report_advancedgrading/classes/btec.php

public function get_data(\cm_info $cm): array {
    $sql = "SELECT 
                gbf.id AS ggfid, 
                criteria.shortname, 
                gbf.score,
                gbf.remark,
                ag.id AS grade_id,
                ag.grade,
                ag.attemptnumber,           -- ✅ إضافة
                ag.timemodified,            -- ✅ إضافة
                stu.firstname, 
                stu.lastname
                
            FROM {assign_grades} ag
            JOIN {grading_instances} gin ON gin.itemid = ag.id
            JOIN {gradingform_btec_fillings} gbf ON gbf.instanceid = gin.id
            JOIN {gradingform_btec_criteria} criteria ON criteria.id = gbf.criterionid
            
            WHERE cm.id = :cmid 
              AND gin.status = :instancestatus
              
            ORDER BY lastname, firstname, ag.attemptnumber, criteria.sortorder ASC";
}
```

**النتيجة:**
- ✅ سيظهر كل طالب مع كل محاولاته
- ✅ يمكن فلترة: "عرض المحاولة الأولى فقط" أو "الأحدث فقط"
- ✅ يمكن عمل مقارنة بين 1st و 2nd

---

### **Option 2: إنشاء تقرير مخصص (متوسط)**

ملف جديد: `report_advancedgrading/classes/btec_attempts.php`

```php
class btec_attempts {
    
    public function get_attempts_comparison($cmid, $userid = null) {
        global $DB;
        
        $sql = "SELECT 
                    stu.id AS userid,
                    stu.firstname,
                    stu.lastname,
                    
                    ag.attemptnumber,
                    ag.timemodified AS attempt_date,
                    ag.grade AS overall_grade,
                    
                    criteria.shortname AS criterion,
                    gbf.score,
                    gbf.remark
                    
                FROM {assign_grades} ag
                JOIN {user} stu ON stu.id = ag.userid
                JOIN {grading_instances} gin ON gin.itemid = ag.id
                JOIN {gradingform_btec_fillings} gbf ON gbf.instanceid = gin.id
                JOIN {gradingform_btec_criteria} criteria ON criteria.id = gbf.criterionid
                JOIN {course_modules} cm ON ag.assignment = cm.instance
                
                WHERE cm.id = :cmid";
        
        if ($userid) {
            $sql .= " AND stu.id = :userid";
        }
        
        $sql .= " ORDER BY stu.lastname, ag.attemptnumber, criteria.sortorder";
        
        $records = $DB->get_records_sql($sql, ['cmid' => $cmid, 'userid' => $userid]);
        
        // Group by student and attempt
        return $this->format_attempts_data($records);
    }
    
    private function format_attempts_data($records) {
        $formatted = [];
        
        foreach ($records as $record) {
            $userid = $record->userid;
            $attemptnum = $record->attemptnumber;
            
            if (!isset($formatted[$userid])) {
                $formatted[$userid] = [
                    'name' => $record->firstname . ' ' . $record->lastname,
                    'attempts' => []
                ];
            }
            
            if (!isset($formatted[$userid]['attempts'][$attemptnum])) {
                $formatted[$userid]['attempts'][$attemptnum] = [
                    'date' => $record->attempt_date,
                    'grade' => $record->overall_grade,
                    'criteria' => []
                ];
            }
            
            $formatted[$userid]['attempts'][$attemptnum]['criteria'][] = [
                'shortname' => $record->criterion,
                'score' => $record->score,
                'remark' => $record->remark
            ];
        }
        
        return $formatted;
    }
}
```

**مثال على الناتج:**
```php
[
    'Ahmed_123' => [
        'name' => 'Ahmed Mohamed',
        'attempts' => [
            0 => [  // 1st Attempt
                'date' => 1738281600,
                'grade' => 40,
                'criteria' => [
                    ['shortname' => 'P1', 'score' => 1, 'remark' => 'Good'],
                    ['shortname' => 'P2', 'score' => 0, 'remark' => 'Incomplete'],
                    ['shortname' => 'M1', 'score' => 0, 'remark' => 'Not met']
                ]
            ],
            1 => [  // 2nd Attempt
                'date' => 1739491200,
                'grade' => 70,
                'criteria' => [
                    ['shortname' => 'P1', 'score' => 1, 'remark' => 'Excellent'],
                    ['shortname' => 'P2', 'score' => 1, 'remark' => 'Fixed'],
                    ['shortname' => 'M1', 'score' => 1, 'remark' => 'Well done']
                ]
            ]
        ]
    ]
]
```

---

### **Option 3: إضافة حقول جديدة (متقدم، غير موصى به)**

تعديل `gradingform_btec_fillings` لإضافة:

```sql
ALTER TABLE gradingform_btec_fillings
ADD COLUMN attemptnumber INT DEFAULT 0,
ADD COLUMN attempt_date INT DEFAULT 0;
```

**❌ المشكلة:**
- يكسر البنية الموجودة
- يحتاج Migration معقد
- غير ضروري (البيانات موجودة أصلاً في `assign_grades`)

---

## 🔟 واجهة المستخدم المقترحة / Proposed UI

### **للمدرس / For Teachers:**

#### **عرض كل المحاولات:**

```
┌───────────────────────────────────────────────────────────────────┐
│ BTEC Grading Report - Assignment: Programming Fundamentals       │
│                                                                   │
│ Student: Ahmed Mohamed (ahmed@example.com)                       │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│ 📊 Attempts Overview:                                            │
│                                                                   │
│ ┌──────────────┬──────────────┬───────────────────────────────┐ │
│ │ Attempt      │ Date         │ Overall Grade                 │ │
│ ├──────────────┼──────────────┼───────────────────────────────┤ │
│ │ 1st (0)      │ Jan 30, 2026 │ 40/100 - Refer               │ │
│ │ 2nd (1)      │ Feb 07, 2026 │ 70/100 - Merit               │ │
│ │ Improvement  │ +8 days      │ ▲ +30 points (+75%)          │ │
│ └──────────────┴──────────────┴───────────────────────────────┘ │
│                                                                   │
│ 📋 Detailed Comparison:                                          │
│                                                                   │
│ ┌───────────┬────────────┬────────────┬────────────────────────┐ │
│ │ Criterion │ 1st Attempt│ 2nd Attempt│ Status                 │ │
│ ├───────────┼────────────┼────────────┼────────────────────────┤ │
│ │ P1        │ ✅ Yes     │ ✅ Yes     │ Maintained             │ │
│ │ P2        │ ❌ No      │ ✅ Yes     │ ✅ Fixed!              │ │
│ │ P3        │ ✅ Yes     │ ✅ Yes     │ Maintained             │ │
│ │ M1        │ ❌ No      │ ✅ Yes     │ ✅ Improved!           │ │
│ │ M2        │ ❌ No      │ ❌ No      │ ⚠️ Still missing       │ │
│ │ D1        │ ❌ No      │ ❌ No      │ Not attempted          │ │
│ └───────────┴────────────┴────────────┴────────────────────────┘ │
│                                                                   │
│ 💬 Feedback:                                                     │
│ P2 (2nd): "Much better! All requirements met now."              │
│ M1 (2nd): "Good analysis and comparison shown."                 │
│                                                                   │
│ [View 1st Attempt Details] [View 2nd Attempt Details]           │
│ [Export Comparison Report] [Send Feedback to Student]           │
└───────────────────────────────────────────────────────────────────┘
```

---

### **للطالب / For Students:**

```
┌───────────────────────────────────────────────────────────────────┐
│ My BTEC Progress - Programming Fundamentals                       │
│                                                                   │
│ 🎯 Current Status: Merit (70/100)                                │
│ 📅 Last Graded: February 7, 2026                                 │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│ 📊 Your Attempts:                                                │
│                                                                   │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 1️⃣ First Attempt (January 30, 2026)                         │ │
│ │    Grade: 40/100 - Refer                                    │ │
│ │    ├─ ✅ P1: Define programming concepts                    │ │
│ │    ├─ ❌ P2: Write simple algorithms (Incomplete)           │ │
│ │    ├─ ✅ P3: Test and debug code                            │ │
│ │    ├─ ❌ M1: Compare programming paradigms (Not met)        │ │
│ │    └─ ❌ D1: Evaluate solutions (Not attempted)             │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 2️⃣ Resubmission (February 7, 2026)                          │ │
│ │    Grade: 70/100 - Merit ✅                                 │ │
│ │    ├─ ✅ P1: Define programming concepts (Maintained)       │ │
│ │    ├─ ✅ P2: Write simple algorithms (✨ FIXED!)            │ │
│ │    ├─ ✅ P3: Test and debug code (Maintained)               │ │
│ │    ├─ ✅ M1: Compare programming paradigms (✨ IMPROVED!)   │ │
│ │    └─ ❌ M2: Justify choices (Still missing)                │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│ 💡 Teacher's Feedback:                                           │
│ "Great improvement! You fixed P2 and achieved M1. Focus on M2   │
│  for your next attempt to reach Distinction."                   │
│                                                                   │
│ 📈 Your Progress:                                                │
│ Refer → Merit (+30 points in 8 days!)                           │
│                                                                   │
│ [View Detailed Feedback] [Request Another Attempt]              │
└───────────────────────────────────────────────────────────────────┘
```

---

## 1️⃣1️⃣ الكود المقترح للتطبيق / Implementation Code

### **إضافة صفحة جديدة:**

`moodle_plugin/ui/reports/btec_attempts_comparison.php`

```php
<?php
require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/lib.php');

$cmid = required_param('id', PARAM_INT);
$userid = optional_param('userid', null, PARAM_INT);

$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/reports/btec_attempts_comparison.php', ['id' => $cmid]));
$PAGE->set_title('BTEC Attempts Comparison');
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

// Get all attempts for this assignment
$sql = "SELECT 
            stu.id AS userid,
            stu.firstname,
            stu.lastname,
            stu.email,
            
            ag.id AS grade_id,
            ag.attemptnumber,
            ag.timemodified AS attempt_date,
            ag.grade AS overall_grade,
            
            gbc.shortname AS criterion,
            gbc.description AS criterion_description,
            gbf.score,
            gbf.remark,
            
            gi.id AS instance_id
            
        FROM {assign_grades} ag
        JOIN {user} stu ON stu.id = ag.userid
        JOIN {grading_instances} gi ON gi.itemid = ag.id
        JOIN {gradingform_btec_fillings} gbf ON gbf.instanceid = gi.id
        JOIN {gradingform_btec_criteria} gbc ON gbc.id = gbf.criterionid
        
        WHERE ag.assignment = :assignmentid";

$params = ['assignmentid' => $assign->id];

if ($userid) {
    $sql .= " AND stu.id = :userid";
    $params['userid'] = $userid;
}

$sql .= " ORDER BY stu.lastname, stu.firstname, ag.attemptnumber, gbc.sortorder";

$records = $DB->get_records_sql($sql, $params);

// Group data by student
$students = [];
foreach ($records as $record) {
    $uid = $record->userid;
    $attemptnum = $record->attemptnumber;
    
    if (!isset($students[$uid])) {
        $students[$uid] = [
            'name' => fullname($record),
            'email' => $record->email,
            'attempts' => []
        ];
    }
    
    if (!isset($students[$uid]['attempts'][$attemptnum])) {
        $students[$uid]['attempts'][$attemptnum] = [
            'date' => $record->attempt_date,
            'grade' => $record->overall_grade,
            'criteria' => []
        ];
    }
    
    $students[$uid]['attempts'][$attemptnum]['criteria'][] = [
        'shortname' => $record->criterion,
        'description' => $record->criterion_description,
        'score' => $record->score,
        'remark' => $record->remark
    ];
}

// Display table
echo '<h2>BTEC Attempts Comparison Report</h2>';

foreach ($students as $uid => $student) {
    echo '<div class="card mb-4">';
    echo '<div class="card-header">';
    echo '<h4>' . $student['name'] . ' (' . $student['email'] . ')</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    
    // Attempts overview
    $attempt_count = count($student['attempts']);
    echo '<p><strong>Total Attempts:</strong> ' . $attempt_count . '</p>';
    
    if ($attempt_count > 1) {
        // Compare 1st vs latest
        $first = $student['attempts'][0];
        $latest = $student['attempts'][$attempt_count - 1];
        
        $improvement = $latest['grade'] - $first['grade'];
        $improvement_percent = ($first['grade'] > 0) ? ($improvement / $first['grade']) * 100 : 0;
        
        echo '<div class="alert alert-info">';
        echo '<strong>Improvement:</strong> ';
        if ($improvement > 0) {
            echo '▲ +' . number_format($improvement, 1) . ' points (+' . number_format($improvement_percent, 1) . '%)';
        } else if ($improvement < 0) {
            echo '▼ ' . number_format($improvement, 1) . ' points (' . number_format($improvement_percent, 1) . '%)';
        } else {
            echo 'No change';
        }
        echo '</div>';
    }
    
    // Attempts table
    echo '<table class="table table-bordered">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Criterion</th>';
    
    foreach ($student['attempts'] as $attemptnum => $attempt) {
        $label = ($attemptnum == 0) ? '1st Attempt' : (($attemptnum == 1) ? '2nd Attempt' : ($attemptnum + 1) . 'th Attempt');
        $date = userdate($attempt['date'], '%d %b %Y');
        echo '<th>' . $label . '<br><small>' . $date . '</small><br><strong>Grade: ' . number_format($attempt['grade'], 1) . '</strong></th>';
    }
    
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    // Get all unique criteria
    $all_criteria = [];
    foreach ($student['attempts'] as $attempt) {
        foreach ($attempt['criteria'] as $criterion) {
            if (!in_array($criterion['shortname'], $all_criteria)) {
                $all_criteria[] = $criterion['shortname'];
            }
        }
    }
    
    // Display each criterion across attempts
    foreach ($all_criteria as $criterion_name) {
        echo '<tr>';
        echo '<td><strong>' . $criterion_name . '</strong></td>';
        
        foreach ($student['attempts'] as $attemptnum => $attempt) {
            $criterion_data = null;
            foreach ($attempt['criteria'] as $crit) {
                if ($crit['shortname'] == $criterion_name) {
                    $criterion_data = $crit;
                    break;
                }
            }
            
            if ($criterion_data) {
                $status = $criterion_data['score'] ? '✅ Yes' : '❌ No';
                $badge_class = $criterion_data['score'] ? 'success' : 'danger';
                
                echo '<td>';
                echo '<span class="badge badge-' . $badge_class . '">' . $status . '</span>';
                if (!empty($criterion_data['remark'])) {
                    echo '<br><small>' . htmlspecialchars($criterion_data['remark']) . '</small>';
                }
                echo '</td>';
            } else {
                echo '<td><span class="text-muted">-</span></td>';
            }
        }
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</div>'; // card-body
    echo '</div>'; // card
}

if (empty($students)) {
    echo '<div class="alert alert-warning">No graded attempts found for this assignment.</div>';
}

echo $OUTPUT->footer();
```

---

## 1️⃣2️⃣ الخلاصة النهائية / Final Summary

### ✅ **ما يمكن عمله الآن:**

1. **عرض المحاولات المتعددة** باستخدام `assign_grades.attemptnumber`
2. **مقارنة 1st vs 2nd Attempt** من خلال الاستعلامات
3. **تتبع التحسين** بحساب فرق الدرجات
4. **عرض تفاصيل كل criterion** في كل محاولة

### ❌ **ما لا يمكن:**

1. **BTEC Plugin لا يدعم Attempts أصلاً** - يحتاج تعديل أو تقرير خارجي
2. **لا يوجد UI جاهز** للمقارنة - يجب إنشاؤه من الصفر
3. **لا توجد إحصائيات تلقائية** عن التحسين بين المحاولات

### 🎯 **التوصية:**

**Option 2 (تقرير مخصص)** هو الأفضل لأنه:
- ✅ لا يعدّل الـ Core Plugin
- ✅ يستخدم البيانات الموجودة
- ✅ مرن وقابل للتطوير
- ✅ يعمل مع أي تحديثات لـ Moodle

---

## 1️⃣3️⃣ الخطوات التالية / Next Steps

إذا بدك تطبيق هذا:

1. **إنشاء ملف التقرير** `btec_attempts_comparison.php` (الكود أعلاه)
2. **إضافة رابط في القائمة** للوصول للتقرير
3. **تصميم UI جميل** مثل الأمثلة أعلاه
4. **اختبار مع بيانات حقيقية** (طلاب عندهم محاولتين)
5. **إضافة فلاتر:** (عرض طالب واحد، تاريخ محدد، إلخ)

---

**هل بدك أبدأ بتطبيق التقرير المقترح؟ 🚀**
