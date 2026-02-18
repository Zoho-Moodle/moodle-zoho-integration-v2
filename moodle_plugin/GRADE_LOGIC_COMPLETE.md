# 📊 Grade Conversion Logic - Complete Guide

**Version:** 3.4.1  
**Date:** February 9, 2026  
**System:** Hybrid Grading System (Observer + Scheduled Task)

---

## 🎯 Overview

This document describes the **complete grade conversion logic** for the Moodle-Zoho Integration plugin, including the new **feedback-based F detection** and **workflow_state tracking**.

---

## ⚙️ Grade Conversion Rules

### 1️⃣ **F (Fail)** - Priority Checks

F grades are assigned in **3 scenarios** (checked in order):

#### **🔴 Scenario 1: Invalid Submission (Feedback Code "01122")**
```php
if (strpos($feedback, '01122') !== false) {
    return "F";  // Invalid/Insufficient file
}
```

**When:** Teacher marks submission as invalid by including "01122" in feedback  
**Why:** Student uploaded wrong file, insufficient work, or non-qualifying submission  
**Example:** Student uploads blank document or wrong assignment  
**Arabic:** ملف خاطئ/غير كافي - يعتبر كأنه ما قدم أبداً  

---

#### **🔴 Scenario 2: No Submission**
```php
if (!$has_submission) {
    return "F";  // No submission at all
}
```

**When:** Student never submitted work  
**Why:** No file uploaded, no attempt made  
**Detection:** `assign_submission.status != 'submitted'`  
**Arabic:** ما في تسليم أبداً  

---

#### **🔴 Scenario 3: Explicitly Graded Zero**
```php
if (isset($rawgrade) && $rawgrade == 0) {
    return "F";  // Teacher explicitly set grade to 0
}
```

**When:** Teacher manually grades as 0  
**Why:** Submission doesn't meet minimum requirements  
**Arabic:** راسب بشكل صريح  

---

### 2️⃣ **R (Refer)** - First Attempt Fail

```php
if (is_null($rawgrade) || $rawgrade < 2) {
    return "R";  // Refer - Needs improvement
}
```

**Requirements:**
- ✅ Submission exists (`has_submission = true`)
- ✅ Feedback does NOT contain "01122"
- ✅ Grade is `null` OR `< 2` (below Pass threshold)
- ✅ Any attempt number (0 or 1)

**Example:**  
- Student submits work but doesn't meet Pass criteria  
- Grade: `null` (ungraded but needs work) OR `0-1.99` (below Pass)  

**Arabic:** محتاج تحسين - في تسليم بس راسب  

---

### 3️⃣ **RR (Double Refer)** - Second Attempt Fail

```php
// Detected by scheduled task (check_for_rr phase)
if ($attemptnumber == 1 && $btec_grade == 'R') {
    // Update Zoho: Change R → RR
}
```

**Requirements:**
- ✅ Grade is **R** (Refer)
- ✅ Attempt number is **1** (second attempt, zero-indexed)
- ✅ Detected by **scheduled task** (NOT observer)

**Process:**
1. Observer sends basic grade as **R**
2. Scheduled task checks `attemptnumber` field
3. If `attemptnumber = 1` → Update Zoho record from R to RR

**Arabic:** راسب بالمحاولة التانية  

---

### 4️⃣ **P (Pass)** - Minimum Pass

```php
if ($rawgrade >= 2 && $rawgrade < 3) {
    return "P";  // Pass
}
```

**Requirements:**
- ✅ Submission exists
- ✅ Grade: `2.0 - 2.99`
- ✅ Can be achieved on **any attempt** (first or second)

**Arabic:** نجاح - علامة Pass  

---

### 5️⃣ **M (Merit)** - Good Performance

```php
if ($rawgrade >= 3 && $rawgrade < 4) {
    return "M";  // Merit
}
```

**Requirements:**
- ✅ Submission exists
- ✅ Grade: `3.0 - 3.99`
- ✅ Can be achieved on **any attempt**

**Arabic:** نجاح - علامة Merit  

---

### 6️⃣ **D (Distinction)** - Excellent Performance

```php
if ($rawgrade >= 4) {
    return "D";  // Distinction
}
```

**Requirements:**
- ✅ Submission exists
- ✅ Grade: `4.0+`
- ✅ Can be achieved on **any attempt**

**Arabic:** نجاح - علامة Distinction  

---

## 🔄 Workflow State Tracking

### **New Field: `workflow_state`**

Extracted from `assign_user_flags.workflowstate` table:

```php
$user_flags = $DB->get_record('assign_user_flags', [
    'assignment' => $assignment->id,
    'userid' => $student->id
]);
$workflow_state = $user_flags->workflowstate ?? null;
```

**Possible Values:**
- `draft` - Student is working on submission
- `submitted` - Student submitted for marking
- `inmarking` - Teacher is marking
- `inreview` - Under review (IV)
- `released` - Grade released to student

**Sent to Zoho:**
```json
{
    "workflow_state": "released",
    "grade": "P",
    "attempt_number": 1
}
```

**Arabic:** حالة التسليم من assign_user_flags  

---

## 📋 Complete Decision Tree

```
┌─────────────────────────────────────┐
│  Submission Graded Event Triggered  │
└────────────────┬────────────────────┘
                 │
                 ▼
        ┌────────────────┐
        │ Get Feedback   │
        └────────┬───────┘
                 │
                 ▼
     ┌───────────────────────┐
     │ Feedback contains     │
     │ "01122"?              │
     └───────┬───────────┬───┘
             │ YES       │ NO
             ▼           ▼
        ┌────────┐  ┌──────────────┐
        │ F      │  │ Check Submit │
        │ (Invalid)│  └──────┬───────┘
        └────────┘         │
                           ▼
                  ┌─────────────────┐
                  │ has_submission? │
                  └────┬────────┬───┘
                   NO  │        │ YES
                       ▼        ▼
                  ┌────────┐  ┌──────────┐
                  │ F      │  │ Check    │
                  │ (No Sub)  │ rawgrade │
                  └────────┘  └────┬─────┘
                                   │
                ┌──────────────────┼──────────────────┐
                │                  │                  │
                ▼                  ▼                  ▼
          rawgrade=0?         null or <2?        >=2?
                │                  │                  │
                ▼                  ▼                  ▼
            ┌────┐            ┌────┐            ┌─────────┐
            │ F  │            │ R  │            │ P/M/D   │
            └────┘            └────┘            │ (Pass)  │
                                                └─────────┘
                              │
                              ▼
                   ┌─────────────────────┐
                   │ Scheduled Task      │
                   │ checks attempt #    │
                   └──────────┬──────────┘
                              │
                   ┌──────────┴──────────┐
                   │ attemptnumber = 1?  │
                   └──────┬──────────┬───┘
                      YES │          │ NO
                          ▼          ▼
                     ┌────────┐  ┌────┐
                     │ RR     │  │ R  │
                     │ (Update)  │    │
                     └────────┘  └────┘
```

---

## 🧪 Test Cases

### **Test 1: Invalid Submission (01122)**
```
Input:
- has_submission: true
- rawgrade: 3.5 (Merit level!)
- feedback: "Good work but wrong assignment. Code: 01122"

Output: F (Fail)
Reason: Feedback contains 01122 - takes precedence over grade
```

---

### **Test 2: No Submission**
```
Input:
- has_submission: false
- rawgrade: null
- feedback: ""

Output: F (Fail)
Reason: No submission exists
```

---

### **Test 3: First Attempt Refer**
```
Input:
- has_submission: true
- rawgrade: 1.5
- attemptnumber: 0
- feedback: "Needs improvement"

Output: R (Refer)
Reason: Below Pass threshold, first attempt
```

---

### **Test 4: Second Attempt Refer → RR**
```
Input:
- has_submission: true
- rawgrade: 1.8
- attemptnumber: 1
- feedback: "Still not meeting criteria"

Observer Output: R (Refer)
Scheduled Task: Detects attempt=1, updates to RR
Final Zoho Grade: RR (Double Refer)
```

---

### **Test 5: Second Attempt Pass**
```
Input:
- has_submission: true
- rawgrade: 2.5
- attemptnumber: 1
- feedback: "Much better!"

Output: P (Pass)
Reason: Meets Pass threshold, attempt number doesn't matter for pass grades
```

---

## 📦 Database Schema

### **Grade Queue Table: `local_mzi_grade_queue`**

```sql
CREATE TABLE local_mzi_grade_queue (
    id BIGINT PRIMARY KEY,
    grade_id BIGINT NOT NULL,
    student_id BIGINT NOT NULL,
    assignment_id BIGINT NOT NULL,
    course_id BIGINT NOT NULL,
    zoho_record_id VARCHAR(50),
    composite_key VARCHAR(255) NOT NULL UNIQUE,
    workflow_state VARCHAR(50),              -- ✅ NEW FIELD
    status VARCHAR(20) DEFAULT 'BASIC_SENT',
    basic_sent_at BIGINT,
    enriched_at BIGINT,
    failed_at BIGINT,
    needs_enrichment TINYINT DEFAULT 1,
    needs_rr_check TINYINT DEFAULT 0,
    error_message TEXT,
    retry_count TINYINT DEFAULT 0,
    timecreated BIGINT NOT NULL,
    timemodified BIGINT NOT NULL
);
```

---

## 🚀 Payload Examples

### **Basic Payload (Observer)**
```json
{
    "grade_id": 123,
    "student_id": 456,
    "student_name": "John Doe",
    "student_email": "john@example.com",
    "assignment_id": 789,
    "assignment_name": "Unit 1 Assignment",
    "course_id": 101,
    "course_name": "BTEC Level 3",
    "grade": "R",
    "raw_grade": 1.5,
    "attempt_number": 1,
    "attemptnumber_zero_indexed": 0,
    "timestamp": 1707465600,
    "graded_at": "2026-02-09 14:30:00",
    "grader_name": "Teacher Smith",
    "grader_role": "Teacher",
    "feedback": "Needs more detail in section 2",
    "workflow_state": "released",
    "status": "PENDING_ENRICHMENT",
    "composite_key": "456_101_789",
    "sync_type": "basic"
}
```

### **Enriched Payload (Scheduled Task)**
```json
{
    // ... all basic fields ...
    "learning_outcomes": [
        {
            "outcome_id": "LO1.1",
            "outcome_name": "Understand concepts",
            "grade": "Achieved"
        },
        {
            "outcome_id": "LO1.2",
            "outcome_name": "Apply techniques",
            "grade": "Not Achieved"
        }
    ],
    "status": "ENRICHED",
    "sync_type": "enriched"
}
```

### **RR Update Payload (Scheduled Task)**
```json
{
    "zoho_record_id": "5847100000123456",
    "grade": "RR",
    "attempt_number": 2,
    "status": "RR_UPDATED",
    "rr_detected_at": "2026-02-09 15:00:00"
}
```

---

## 🔧 Configuration

### **Language Strings**

#### **English (`lang/en/local_moodle_zoho_sync.php`)**
```php
$string['gradequeue_workflow_state'] = 'Workflow State';
$string['gradequeue_invalid_submission'] = 'Invalid Submission (01122)';
```

#### **Arabic (`lang/ar/local_moodle_zoho_sync.php`)**
```php
$string['gradequeue_workflow_state'] = 'حالة سير العمل';
$string['gradequeue_invalid_submission'] = 'تسليم غير صالح (01122)';
```

---

## 🎓 Summary Table

| Grade | Condition | Submission? | Raw Grade | Attempt | Detection |
|-------|-----------|-------------|-----------|---------|-----------|
| **F** | Invalid (01122) | ✅ Yes | Any | Any | Observer |
| **F** | No submission | ❌ No | - | - | Observer/Task |
| **F** | Explicit 0 | ✅ Yes | 0 | Any | Observer |
| **R** | Below Pass | ✅ Yes | null or <2 | 0 | Observer |
| **RR** | Below Pass | ✅ Yes | null or <2 | 1 | Task |
| **P** | Pass | ✅ Yes | 2.0-2.99 | Any | Observer |
| **M** | Merit | ✅ Yes | 3.0-3.99 | Any | Observer |
| **D** | Distinction | ✅ Yes | 4.0+ | Any | Observer |

---

## 🔄 Version History

### **v3.4.1** - February 9, 2026
- ✅ Added `workflow_state` field tracking
- ✅ Implemented feedback-based F detection ("01122" code)
- ✅ Enhanced quick_btec_conversion() with 3-priority F logic
- ✅ Updated database schema (upgrade.php version 2026020901)
- ✅ Added English and Arabic language strings

### **v3.4.0** - February 8, 2026
- ✅ Hybrid Grading System (Observer + Scheduled Task)
- ✅ RR detection via scheduled task
- ✅ F grade creation for no submissions
- ✅ Learning outcomes enrichment

---

## 📝 Notes

1. **Priority Order:** Feedback (01122) → No Submission → Explicit 0 → R/Pass grades
2. **RR Detection:** Only done by scheduled task, NOT observer
3. **Workflow State:** Optional field, sent to Zoho for tracking
4. **Attempt Indexing:** Internal (0-based), Display (1-based)
5. **Performance:** Observer < 100ms, Scheduled Task processes 100 records per run

---

## 🎯 Arabic Summary / الملخص بالعربي

### قواعد تحويل العلامات:

1. **F (راسب)**:
   - الأولوية 1: Feedback فيه "01122" (ملف خاطئ/غير كافي)
   - الأولوية 2: ما في تسليم أبداً
   - الأولوية 3: علامة 0 صريحة

2. **R (محتاج إعادة)**:
   - في تسليم بس العلامة null أو أقل من 2
   - المحاولة الأولى فقط

3. **RR (راسب مرتين)**:
   - علامة R بالمحاولة التانية (attempt = 1)
   - بيكشفها الـ Scheduled Task

4. **P/M/D (نجاح)**:
   - P: علامة 2-2.99
   - M: علامة 3-3.99
   - D: علامة 4+
   - ممكن تتحقق بأي محاولة

5. **Workflow State (حالة التسليم)**:
   - يجي من جدول assign_user_flags
   - بيرسل لـ Zoho مع البيانات الأساسية

---

**End of Document** 🎉
