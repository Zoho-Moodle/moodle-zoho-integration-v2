# 🎓 Student Dashboard - Complete Technical Specification
## ABC Horizon BTEC Integration v2

**Version:** 2.0.0  
**Date:** February 16, 2026  
**Author:** System Architect  
**Status:** Ready for Implementation ✅

---

## 📑 Table of Contents

1. [Executive Summary](#executive-summary)
2. [System Architecture](#system-architecture)
3. [Dashboard Structure](#dashboard-structure)
4. [Database Schema](#database-schema)
5. [Zoho Modules Mapping](#zoho-modules-mapping)
6. [Backend API Endpoints](#backend-api-endpoints)
7. [Sync Strategy](#sync-strategy)
8. [UI Specifications](#ui-specifications)
9. [Student Card Design](#student-card-design)
10. [Security & Permissions](#security--permissions)
11. [Implementation Plan](#implementation-plan)
12. [Testing Strategy](#testing-strategy)

---

## 1. Executive Summary

### 🎯 Objective
Create a comprehensive Student Dashboard within Moodle that displays academic and financial data synced from Zoho CRM, with a focus on **clarity**, **simplicity**, and **registration-centric** design.

### 🔑 Key Features
- ✅ **4 Main Pages**: Profile, My Programs, Classes & Grades, Requests
- ✅ **Registration-Centric**: Financial + Academic data unified per program
- ✅ **Read-Only Display**: All business logic handled in Zoho
- ✅ **Real-Time Sync**: Initial bulk + webhook-driven updates
- ✅ **Student Card**: Automated HTML/PDF generation
- ✅ **Mobile Responsive**: Full support for mobile devices

### 📊 Data Flow (Single Database Architecture)
```
Zoho CRM (Source of Truth - Master Data)
    ↓ (Webhooks: Create/Update/Delete)
Backend (Event Processor - Lightweight Middleware)
    ↓ (Direct SQL: INSERT/UPDATE/DELETE)
Moodle Database (Single Source - Local Cache)
    ↓ (Direct PHP Queries - No API Layer)
Student Dashboard (Pure UI Display)
```

**Key Principle:** Backend writes directly to Moodle DB, Student UI reads directly from Moodle DB. **No intermediate REST API layer.**

---

## 2. System Architecture

### 🏗️ Simplified Architecture (Single Database)

```
┌─────────────────────────────────────────────────────────────────┐
│                    ZOHO CRM (Master Data)                        │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐ │
│  │BTEC_Students │Registrations │ Enrollments  │   Grades     │ │
│  │   Payments   │   Classes    │  Requests    │   Programs   │ │
│  └──────────────┴──────────────┴──────────────┴──────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ Webhooks (Real-time Events)
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│              BACKEND (Lightweight Event Processor)               │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  • Receives Webhooks                                     │  │
│  │  • Transforms Zoho Data                                  │  │
│  │  • Writes Directly to Moodle DB (SQL)                   │  │
│  │  • NO REST APIs for Dashboard                           │  │
│  │  • NO separate PostgreSQL                               │  │
│  └──────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ Direct SQL (INSERT/UPDATE/DELETE)
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│              MOODLE DATABASE (Single Source of Truth)            │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐ │
│  │mdl_local_mzi_│mdl_local_mzi_│mdl_local_mzi_│mdl_local_mzi_│ │
│  │students      │registrations │enrollments   │grades        │ │
│  │mdl_local_mzi_│mdl_local_mzi_│mdl_local_mzi_│mdl_local_mzi_│ │
│  │payments      │classes       │requests      │installments  │ │
│  └──────────────┴──────────────┴──────────────┴──────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ Direct PHP SQL Queries
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                MOODLE PLUGIN (Student Dashboard UI)              │
│  ┌──────────┬──────────┬──────────────┬──────────┐             │
│  │ Profile  │  Programs│Classes/Grades│ Requests │             │
│  │ (PHP)    │  (PHP)   │   (PHP)      │  (PHP)   │             │
│  └──────────┴──────────┴──────────────┴──────────┘             │
│                                                                  │
│  • Direct DB queries via $DB->get_records()                     │
│  • No API calls, no cURL, no fetch()                            │
│  • Load time: 20-50ms                                           │
└─────────────────────────────────────────────────────────────────┘
```

### 🎯 Architecture Benefits

| Benefit | Description |
|---------|-------------|
| **Simplicity** | One database, direct queries, minimal layers |
| **Performance** | No API latency, direct SQL access (20-50ms) |
| **Reliability** | Student UI works even if Backend is down |
| **Cost** | No separate infrastructure, uses existing Moodle DB |
| **Maintenance** | Single system to maintain, easier debugging |
| **Scalability** | Sufficient for 100-5000 students |

### 🔧 Technology Stack (Simplified)

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| **Backend Framework** | FastAPI | 3.1.1 | Webhook receiver only |
| **Backend Language** | Python | 3.14 | Event processing |
| **Database** | PostgreSQL | 14+ | **Single DB (Moodle)** |
| **Backend Role** | Event Processor | - | Transform + Write to Moodle DB |
| **Moodle Version** | Moodle | 4.x | Dashboard UI + DB host |
| **Frontend** | PHP + Bootstrap | 4.6 | Direct SQL queries |
| **PDF Generation** | wkhtmltopdf | 0.12.6 | Student card |
| **QR Code** | Google Charts API | v1 | Card verification |

**Key Difference:** Backend has **NO separate PostgreSQL**. It writes directly to Moodle's PostgreSQL database.

### 🔄 How Data Flows (Example: Student Update)

```
1️⃣ Admin updates student phone in Zoho CRM
   └─> Phone: +963 999 999 999

2️⃣ Zoho sends webhook to Backend
   POST /api/v1/webhooks/student_updated
   {
     "module": "BTEC_Students",
     "id": "5398830000123456789",
     "Phone": "+963 999 999 999"
   }

3️⃣ Backend receives webhook
   └─> Connects to Moodle PostgreSQL
   └─> Executes SQL:
       UPDATE mdl_local_mzi_students 
       SET phone = '+963 999 999 999', 
           synced_at = 1708088400
       WHERE zoho_student_id = '5398830000123456789';

4️⃣ Student opens Profile page
   └─> profile.php executes:
       $student = $DB->get_record('local_mzi_students', 
                                   ['moodle_user_id' => $USER->id]);
   └─> Renders: Phone: +963 999 999 999

⏱️ Total time: 2-5 seconds (webhook → DB → UI)
```

**No API calls. No cURL. No fetch(). Pure SQL.**

---

## 3. Dashboard Structure

### 📱 Navigation Menu

```
┌────────────────────────────────────────────────────────────┐
│  🎓 Student Dashboard                          [User Menu] │
├────────────────────────────────────────────────────────────┤
│  [Profile] [My Programs] [Classes & Grades] [Requests]     │
└────────────────────────────────────────────────────────────┘
```

### 3.1 Page 1: Profile 👤

**Purpose:** Display student personal information and quick actions

#### 📋 Sections

##### A. Basic Info Card
```
┌─────────────────────────────────────────────────────────────┐
│  👤 STUDENT PROFILE                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📸 [Photo]  │  First Name: Ahmed                          │
│              │  Last Name: Ali Hassan                      │
│              │  Student ID: ST-000123                      │
│              │  Status: ✅ Active                          │
│              │                                             │
│              │  Academic Email: ahmed@abchorizon.edu       │
│              │  Phone: +963 XXX XXX XXX                    │
│              │  Nationality: Syrian 🇸🇾                    │
│              │  Date of Birth: 15 Jan 2000 (26 years)     │
│              │                                             │
│              │  [👁️ Show National ID]                      │
│              │  National ID: ************                  │
│              │  Address: Damascus, Syria                   │
│              │                                             │
│              │  Last Updated: 16 Feb 2026 10:45 AM        │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  🔘 Request Information Update                              │
│  🔘 Request Student Card (Active students only)             │
│  📥 Download Student Card (if already issued)               │
└─────────────────────────────────────────────────────────────┘
```

#### 📊 Data Sources

| Field | Zoho Module | Zoho Field | Type |
|-------|-------------|------------|------|
| First Name | BTEC_Students | First_Name | text |
| Last Name | BTEC_Students | Last_Name | text |
| Student ID | BTEC_Students | Name (Auto) | autonumber |
| Email | BTEC_Students | Email | email |
| Phone | BTEC_Students | Phone | phone |
| Nationality | BTEC_Students | Nationality | picklist |
| Birth Date | BTEC_Students | Birth_Date | date |
| Birth Place | BTEC_Students | Birth_Place | text |
| National ID | BTEC_Students | National_ID_Number | text |
| Address | BTEC_Students | Address | text |
| Photo | BTEC_Students | Photo | fileupload |
| Status | BTEC_Registrations | Registration_Status | picklist |

#### 🔒 Privacy Features

```javascript
// National ID Toggle
<button onclick="toggleNationalID()">👁️ Show National ID</button>
<span id="nationalID" style="display:none;">123456789012</span>

<script>
function toggleNationalID() {
    var el = document.getElementById('nationalID');
    var btn = event.target;
    
    if (el.style.display === 'none') {
        el.style.display = 'inline';
        btn.textContent = '🙈 Hide National ID';
    } else {
        el.style.display = 'none';
        btn.textContent = '👁️ Show National ID';
    }
}
</script>
```

#### ⚡ Actions

1. **Request Information Update**
   - Opens Request form with Type = "Change Information"
   - Redirects to Requests page with pre-filled form

2. **Request Student Card**
   - **Condition:** `Registration_Status = 'Active'`
   - Creates request in `BTEC_Student_Requests`
   - Admin approval required

3. **Download Student Card**
   - **Condition:** Student has approved card
   - Generates PDF on-the-fly
   - Opens in new tab for printing

---

### 3.2 Page 2: My Programs 📚

**Purpose:** Display all registrations (past and current) with financial information

#### 📋 Structure

```
┌─────────────────────────────────────────────────────────────┐
│  📚 MY PROGRAMS                                             │
├─────────────────────────────────────────────────────────────┤
│  Filter: [All Programs ▼] [Active] [Completed] [Suspended] │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  🎓 Digital Marketing - Level 3 Diploma          [Active ✅]│
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📋 PROGRAM OVERVIEW                                        │
│  ├─ Program: Digital Marketing                             │
│  ├─ Level: Level 3                                         │
│  ├─ Study Mode: Full-Time                                  │
│  ├─ Registration Date: 20 Aug 2024                         │
│  ├─ Expected Completion: 30 Jun 2025                       │
│  └─ Status: Active ✅                                       │
│                                                             │
│  💰 FINANCIAL SUMMARY                                       │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Total Fees:      $15,000.00                         │  │
│  │ Paid:            $12,000.00                         │  │
│  │ Remaining:       $ 3,000.00                         │  │
│  │ Progress: ████████████████░░░░ 80%                 │  │
│  │                                                     │  │
│  │ Status: ⚠️ Balance Remaining                        │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  📅 INSTALLMENTS SCHEDULE                                   │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Due Date    │ Amount    │ Status                    │  │
│  ├─────────────────────────────────────────────────────┤  │
│  │ 15 Sep 2024 │ $3,000.00 │ ✅ Paid on 10 Sep 2024   │  │
│  │ 15 Dec 2024 │ $3,000.00 │ ✅ Paid on 05 Dec 2024   │  │
│  │ 15 Mar 2025 │ $3,000.00 │ ✅ Paid on 10 Mar 2025   │  │
│  │ 15 Jun 2025 │ $3,000.00 │ ⏳ Pending               │  │
│  │ 15 Sep 2025 │ $3,000.00 │ ⏰ Upcoming               │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  💳 PAYMENTS HISTORY                                        │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Payment Date │ Amount    │ Method       │ Reference │  │
│  ├─────────────────────────────────────────────────────┤  │
│  │ 10 Mar 2025  │ $3,000.00 │ Visa (4532)  │ PAY-089  │  │
│  │ 05 Dec 2024  │ $3,000.00 │ Bank Transfer│ PAY-067  │  │
│  │ 10 Sep 2024  │ $6,000.00 │ Cash         │ PAY-023  │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  [View Classes for this Program →]                          │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  🎓 Web Development - Level 5 HND            [Completed ✅] │
├─────────────────────────────────────────────────────────────┤
│  📋 PROGRAM OVERVIEW                                        │
│  ├─ Completion Date: 20 Jun 2024                           │
│  ├─ Final Status: Completed ✅                              │
│  └─ Certificate: [📥 Download]                              │
│                                                             │
│  💰 FINANCIAL SUMMARY                                       │
│  └─ Status: ✅ Paid in Full ($18,000 / $18,000)            │
│                                                             │
│  [View Historical Classes →] [View Transcript →]            │
└─────────────────────────────────────────────────────────────┘
```

#### 🔄 Sorting Logic

```python
# Backend sorting algorithm
def sort_registrations(registrations):
    priority = {
        'Active': 1,
        'In Progress': 2,
        'Suspended': 3,
        'Completed': 4,
        'Cancelled': 5
    }
    
    return sorted(registrations, 
                  key=lambda r: (priority.get(r.status, 99), 
                                 -r.registration_date.timestamp()))
```

#### 📊 Data Sources

| Section | Zoho Module | Fields |
|---------|-------------|--------|
| **Program Overview** | BTEC_Registrations | Program (lookup), Level, Study_Mode, Registration_Date, Expected_Completion_Date, Registration_Status |
| **Financial Summary** | BTEC_Registrations | Total_Fees, Paid_Amount, Remaining_Amount |
| **Installments** | BTEC_Registrations | Installments (subform): Due_Date, Amount, Status |
| **Payments** | BTEC_Payments | Payment_Date, Payment_Amount, Payment_Method, SRM_Voucher_Number |

#### 🎨 Status Badges

```html
<!-- Active -->
<span class="badge badge-success">✅ Active</span>

<!-- In Progress -->
<span class="badge badge-primary">⏳ In Progress</span>

<!-- Completed -->
<span class="badge badge-info">✅ Completed</span>

<!-- Suspended -->
<span class="badge badge-warning">⚠️ Suspended</span>

<!-- Cancelled -->
<span class="badge badge-danger">❌ Cancelled</span>
```

#### 💰 Financial Status Badges

```html
<!-- Paid in Full -->
<span class="badge badge-success">✅ Paid in Full</span>

<!-- Balance Remaining -->
<span class="badge badge-warning">⚠️ Balance Remaining</span>

<!-- Overdue -->
<span class="badge badge-danger">🔴 Overdue</span>
```

---

### 3.3 Page 3: Classes & Grades 📝

**Purpose:** Display class enrollments and assignment grades

#### 📋 Structure

```
┌─────────────────────────────────────────────────────────────┐
│  📝 CLASSES & GRADES                                        │
├─────────────────────────────────────────────────────────────┤
│  Filter: [All Classes ▼] [Active] [Completed]              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  📚 Digital Marketing - Unit 3: SEO & Analytics  [Active ⏳]│
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📋 CLASS DETAILS                                           │
│  ├─ Class ID: CLS-2024-DM-03                               │
│  ├─ Program: Digital Marketing L3                          │
│  ├─ Teacher: Prof. Sarah Johnson                           │
│  ├─ Start Date: 01 Sep 2024                                │
│  ├─ End Date: 30 Jun 2025                                  │
│  ├─ Overall Grade: Merit (M) 🎯                            │
│  └─ Progress: ████████░░░░ 75% (3/4 assignments completed) │
│                                                             │
│  📊 ASSIGNMENTS                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │Assignment Name      │Due Date  │Submitted│Grade│View│  │
│  ├─────────────────────────────────────────────────────┤  │
│  │SEO Fundamentals     │15 Oct 24 │10 Oct 24│ M1  │[👁️]│  │
│  │Keyword Research     │30 Nov 24 │28 Nov 24│ D2  │[👁️]│  │
│  │Analytics Dashboard  │15 Jan 25 │12 Jan 25│ M2  │[👁️]│  │
│  │Final SEO Campaign   │15 Mar 25 │Pending  │  -  │[ ]│  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  [View Full Class Details →]                                │
└─────────────────────────────────────────────────────────────┘
```

#### 🔍 Grade Details Modal

```
┌─────────────────────────────────────────────────────────────┐
│  📄 Assignment: SEO Fundamentals              [✖ Close]     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📊 GRADE DETAILS                                           │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ BTEC Grade:        Merit 1 (M1)                     │  │
│  │ Numeric Grade:     75/100                           │  │
│  │ Grade Status:      Marking Completed ✅             │  │
│  │ Submission Date:   10 Oct 2024                      │  │
│  │ Grading Date:      18 Oct 2024                      │  │
│  │ Attempt Number:    1 (First Attempt)                │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  🎯 LEARNING OUTCOMES                                       │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ LO1: Understand SEO principles        ✅ Achieved   │  │
│  │ LO2: Conduct keyword research         ✅ Achieved   │  │
│  │ LO3: Optimize website content         ✅ Achieved   │  │
│  │ LO4: Analyze SEO performance          ⏳ Pending    │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  💬 TEACHER FEEDBACK                                        │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ Excellent understanding of SEO fundamentals. Your   │  │
│  │ keyword research shows depth and practical          │  │
│  │ application. Content optimization strategies are    │  │
│  │ well-explained. To reach Distinction, focus more   │  │
│  │ on advanced analytics and competitive analysis.     │  │
│  │                                                     │  │
│  │ Strengths:                                          │  │
│  │ - Clear structure and presentation                  │  │
│  │ - Good use of real-world examples                   │  │
│  │ - Thorough research                                 │  │
│  │                                                     │  │
│  │ Areas for improvement:                              │  │
│  │ - Include more advanced metrics                     │  │
│  │ - Add competitor analysis section                   │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ ⚠️ Please acknowledge that you have read this       │  │
│  │    feedback by clicking the button below.           │  │
│  │                                                     │  │
│  │           [✅ I Acknowledge This Feedback]           │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  Status: ⏳ Not Acknowledged                                │
└─────────────────────────────────────────────────────────────┘

<!-- After acknowledgement -->
┌─────────────────────────────────────────────────────────────┐
│  Status: ✅ Acknowledged on 16 Feb 2026 10:45 AM            │
│  You can still view this feedback anytime.                  │
└─────────────────────────────────────────────────────────────┘
```

#### 🔔 Acknowledgement Logic

```javascript
// Frontend
async function acknowledgeFeedback(gradeId) {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
    
    try {
        const response = await fetch(`/api/v1/grades/${gradeId}/acknowledge`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                acknowledged_at: new Date().toISOString(),
                moodle_user_id: USER_ID
            })
        });
        
        if (response.ok) {
            // Update UI
            document.getElementById('ack-status').innerHTML = 
                '✅ Acknowledged on ' + new Date().toLocaleString();
            
            // Disable button
            btn.remove();
            
            // Show success message
            showNotification('Feedback acknowledged successfully!', 'success');
        }
    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = '✅ I Acknowledge This Feedback';
        showNotification('Error acknowledging feedback', 'error');
    }
}
```

```python
# Backend
@router.post("/grades/{grade_id}/acknowledge")
async def acknowledge_feedback(
    grade_id: str,
    ack_data: AcknowledgeRequest,
    db: Session = Depends(get_db)
):
    """Record student acknowledgement of feedback."""
    
    # Update local database
    grade = db.query(Grade).filter(Grade.zoho_grade_id == grade_id).first()
    if not grade:
        raise HTTPException(status_code=404, detail="Grade not found")
    
    grade.feedback_acknowledged_at = ack_data.acknowledged_at
    grade.acknowledged_by_user_id = ack_data.moodle_user_id
    db.commit()
    
    # Optional: Update Zoho with acknowledgement timestamp
    # await zoho_client.update_record(
    #     module="BTEC_Grades",
    #     record_id=grade.zoho_grade_id,
    #     data={"Feedback_Acknowledged": True, "Acknowledged_At": ack_data.acknowledged_at}
    # )
    
    return {"status": "success", "acknowledged_at": ack_data.acknowledged_at}
```

#### 📊 Data Sources

| Section | Zoho Module | Fields |
|---------|-------------|--------|
| **Class Details** | BTEC_Classes | Name, Class_Code, Start_Date, End_Date, Teacher_Name |
| **Enrollment** | BTEC_Enrollments | Classes (lookup), Enrolled_Students (lookup), Start_Date, End_Date |
| **Assignments/Grades** | BTEC_Grades | BTEC_Grade_Name, Attempt_Date, Attempt_Number, Grade_Status, Feedback |
| **Learning Outcomes** | BTEC_Grades | LO_1_Status, LO_2_Status, LO_3_Status, LO_4_Status (from subform) |

#### 🎯 Overall Grade Calculation

```python
def calculate_overall_grade(class_id: str, student_id: str) -> str:
    """
    Calculate overall grade based on last attempt of all assignments.
    
    BTEC Grading:
    - Distinction (D): All criteria met at highest level
    - Merit (M): All criteria met at good level
    - Pass (P): All criteria met at basic level
    - Fail (F): Not all criteria met
    """
    
    # Get all completed grades for this class
    grades = db.query(Grade).filter(
        Grade.class_id == class_id,
        Grade.student_id == student_id,
        Grade.grade_status == 'Marking completed'
    ).order_by(Grade.assignment_name, Grade.attempt_number.desc()).all()
    
    # Get latest attempt for each assignment
    latest_grades = {}
    for grade in grades:
        if grade.assignment_name not in latest_grades:
            latest_grades[grade.assignment_name] = grade.btec_grade_name
    
    if not latest_grades:
        return "In Progress"
    
    grade_values = list(latest_grades.values())
    
    # Count grade types
    d_count = sum(1 for g in grade_values if g.startswith('D'))
    m_count = sum(1 for g in grade_values if g.startswith('M'))
    p_count = sum(1 for g in grade_values if g.startswith('P'))
    
    # Determine overall grade
    if d_count == len(grade_values):
        return "Distinction (D)"
    elif d_count > 0 and (d_count + m_count) == len(grade_values):
        return "Distinction* (D*)"
    elif m_count == len(grade_values):
        return "Merit (M)"
    elif (m_count + d_count) > 0 and (m_count + d_count + p_count) == len(grade_values):
        return "Merit* (M*)"
    elif p_count == len(grade_values):
        return "Pass (P)"
    elif (p_count + m_count + d_count) == len(grade_values):
        return "Pass* (P*)"
    else:
        return "In Progress"
```

---

### 3.4 Page 4: Requests 📮

**Purpose:** Submit and track student requests

#### 📋 Structure

```
┌─────────────────────────────────────────────────────────────┐
│  📮 STUDENT REQUESTS                                        │
├─────────────────────────────────────────────────────────────┤
│  [+ Submit New Request]                                     │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  ➕ SUBMIT NEW REQUEST                                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Request Type: [Select Type ▼]                             │
│                 - Enroll Next Semester                      │
│                 - Class Drop                                │
│                 - Late Submission                           │
│                 - Student Card                              │
│                 - Change Information                        │
│                                                             │
│  <!-- Dynamic form based on type -->                        │
│                                                             │
│  [Submit Request]                                           │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  📋 MY REQUESTS                                             │
├─────────────────────────────────────────────────────────────┤
│  Filter: [All ▼] [Pending] [Approved] [Rejected]           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ 📝 Request #REQ-2026-089              [Pending ⏳]  │  │
│  ├─────────────────────────────────────────────────────┤  │
│  │ Type: Enroll Next Semester                          │  │
│  │ Submitted: 16 Feb 2026 10:30 AM                     │  │
│  │ Program: Digital Marketing L4                       │  │
│  │ Semester: Spring 2026                               │  │
│  │                                                     │  │
│  │ Requested Classes:                                  │  │
│  │ - Social Media Advanced                             │  │
│  │ - Content Strategy                                  │  │
│  │ - Digital Analytics                                 │  │
│  │                                                     │  │
│  │ Status: ⏳ Awaiting Review                          │  │
│  │ [View Details] [Cancel Request]                     │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ 🎴 Request #REQ-2026-067            [Approved ✅]  │  │
│  ├─────────────────────────────────────────────────────┤  │
│  │ Type: Student Card                                  │  │
│  │ Submitted: 10 Feb 2026 09:15 AM                     │  │
│  │ Approved: 12 Feb 2026 02:30 PM                      │  │
│  │                                                     │  │
│  │ Status: ✅ Approved - Card Ready                    │  │
│  │ [📥 Download Card] [View Details]                   │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐  │
│  │ ✏️ Request #REQ-2026-023             [Rejected ❌] │  │
│  ├─────────────────────────────────────────────────────┤  │
│  │ Type: Class Drop                                    │  │
│  │ Submitted: 05 Feb 2026 11:20 AM                     │  │
│  │ Rejected: 06 Feb 2026 03:45 PM                      │  │
│  │                                                     │  │
│  │ Reason for Rejection:                               │  │
│  │ "Drop deadline has passed. Please contact admin    │  │
│  │  for alternative options."                          │  │
│  │                                                     │  │
│  │ Status: ❌ Rejected                                 │  │
│  │ [View Details] [Submit New Request]                 │  │
│  └─────────────────────────────────────────────────────┘  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### 📝 Request Types & Dynamic Forms

##### 1️⃣ Enroll Next Semester

```html
<form id="enrollNextSemesterForm">
    <div class="form-group">
        <label>Program</label>
        <select name="program_id" required>
            <option value="">Select Program</option>
            <!-- Populated from active registrations -->
        </select>
    </div>
    
    <div class="form-group">
        <label>Semester</label>
        <select name="semester" required>
            <option value="Spring 2026">Spring 2026</option>
            <option value="Fall 2026">Fall 2026</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>Requested Classes (Select multiple)</label>
        <div id="requestedClasses">
            <!-- Checkboxes for available classes -->
            <input type="checkbox" name="classes[]" value="CLS-001"> Social Media Advanced<br>
            <input type="checkbox" name="classes[]" value="CLS-002"> Content Strategy<br>
            <input type="checkbox" name="classes[]" value="CLS-003"> Digital Analytics<br>
        </div>
    </div>
    
    <div class="form-group">
        <label>Additional Notes</label>
        <textarea name="reason" rows="3"></textarea>
    </div>
    
    <button type="submit">Submit Request</button>
</form>
```

##### 2️⃣ Class Drop

```html
<form id="classDropForm">
    <div class="form-group">
        <label>Class to Drop</label>
        <select name="class_id" required>
            <option value="">Select Class</option>
            <!-- Populated from current enrollments -->
        </select>
    </div>
    
    <div class="form-group">
        <label>Reason for Drop</label>
        <textarea name="reason" rows="3" required></textarea>
    </div>
    
    <button type="submit">Submit Request</button>
</form>
```

##### 3️⃣ Late Submission

```html
<form id="lateSubmissionForm">
    <div class="form-group">
        <label>Class</label>
        <select name="class_id" required>
            <!-- Populated from enrollments -->
        </select>
    </div>
    
    <div class="form-group">
        <label>Assignment</label>
        <select name="assignment_id" required>
            <!-- Populated based on selected class -->
        </select>
    </div>
    
    <div class="form-group">
        <label>Reason for Late Submission</label>
        <textarea name="reason" rows="3" required></textarea>
    </div>
    
    <div class="form-group">
        <label>Supporting Documents (Optional)</label>
        <input type="file" name="attachments[]" multiple>
    </div>
    
    <button type="submit">Submit Request</button>
</form>
```

##### 4️⃣ Student Card

```html
<form id="studentCardForm">
    <div class="alert alert-info">
        <strong>ℹ️ Eligibility:</strong> Student cards are only available for students with Active registration status.
    </div>
    
    <div class="form-group">
        <label>Reason for Request</label>
        <select name="reason" required>
            <option value="First Time">First Time</option>
            <option value="Lost">Lost Card</option>
            <option value="Damaged">Damaged Card</option>
            <option value="Replacement">Replacement</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>Additional Information</label>
        <textarea name="additional_info" rows="2"></textarea>
    </div>
    
    <button type="submit">Submit Request</button>
</form>
```

##### 5️⃣ Change Information

```html
<form id="changeInformationForm">
    <div class="form-group">
        <label>What information would you like to change?</label>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Current Value</th>
                        <th>New Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Phone Number</td>
                        <td><?php echo $current_phone; ?></td>
                        <td><input type="tel" name="new_phone"></td>
                    </tr>
                    <tr>
                        <td>Email</td>
                        <td><?php echo $current_email; ?></td>
                        <td><input type="email" name="new_email"></td>
                    </tr>
                    <tr>
                        <td>Address</td>
                        <td><?php echo $current_address; ?></td>
                        <td><textarea name="new_address" rows="2"></textarea></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="form-group">
        <label>Reason for Change</label>
        <textarea name="reason" rows="3" required></textarea>
    </div>
    
    <div class="form-group">
        <label>Supporting Documents (if applicable)</label>
        <input type="file" name="attachments[]" multiple>
    </div>
    
    <button type="submit">Submit Request</button>
</form>
```

#### 🎨 Status Badges

```html
<!-- Pending -->
<span class="badge badge-warning">⏳ Pending</span>

<!-- In Review -->
<span class="badge badge-primary">🔍 In Review</span>

<!-- Approved -->
<span class="badge badge-success">✅ Approved</span>

<!-- Rejected -->
<span class="badge badge-danger">❌ Rejected</span>

<!-- Cancelled -->
<span class="badge badge-secondary">⭕ Cancelled</span>

<!-- Completed -->
<span class="badge badge-info">✅ Completed</span>
```

#### 📊 Data Sources

| Field | Zoho Module | Zoho Field |
|-------|-------------|------------|
| Request Name | BTEC_Student_Requests | Name |
| Request Type | BTEC_Student_Requests | Request_Type |
| Student | BTEC_Student_Requests | Student (lookup) |
| Request Date | BTEC_Student_Requests | Request_Date |
| Status | BTEC_Student_Requests | Status |
| Reason | BTEC_Student_Requests | Reason |
| Academic Email | BTEC_Student_Requests | Academic_Email |
| Requested Classes | BTEC_Student_Requests | Requested_Classes (subform) |
| Change Info | BTEC_Student_Requests | Change_Information (subform) |
| Attachments | BTEC_Student_Requests | Payment_Receipt (fileupload) |

---

## 4. Database Schema

### 📊 Moodle Tables Structure

#### Table 1: `mdl_local_mzi_students`

**Purpose:** Store student personal information synced from Zoho

```sql
CREATE TABLE mdl_local_mzi_students (
    id BIGSERIAL PRIMARY KEY,
    
    -- Moodle Link
    moodle_user_id BIGINT NOT NULL UNIQUE,
    
    -- Zoho Link
    zoho_student_id VARCHAR(255) NOT NULL UNIQUE,
    
    -- Personal Info
    student_id VARCHAR(100) NOT NULL UNIQUE,  -- ST-000123
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    first_name_native VARCHAR(255),
    last_name_native VARCHAR(255),
    mother_name VARCHAR(255),
    father_name VARCHAR(255),
    
    -- Contact Info
    email VARCHAR(255),
    phone VARCHAR(50),
    academic_email VARCHAR(255),
    
    -- Identity
    nationality VARCHAR(100),
    birth_date DATE,
    birth_place VARCHAR(255),
    national_id_number VARCHAR(100),
    passport_number VARCHAR(100),
    
    -- Address
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    
    -- BTEC Info
    btec_registration_number VARCHAR(100),
    
    -- Media
    photo_url TEXT,
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    updated_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    -- Indexes
    CONSTRAINT fk_moodle_user FOREIGN KEY (moodle_user_id) 
        REFERENCES mdl_user(id) ON DELETE CASCADE
);

CREATE INDEX idx_mzi_students_zoho_id ON mdl_local_mzi_students(zoho_student_id);
CREATE INDEX idx_mzi_students_student_id ON mdl_local_mzi_students(student_id);
CREATE INDEX idx_mzi_students_moodle_user ON mdl_local_mzi_students(moodle_user_id);
```

#### Table 2: `mdl_local_mzi_registrations`

**Purpose:** Store program registrations

```sql
CREATE TABLE mdl_local_mzi_registrations (
    id BIGSERIAL PRIMARY KEY,
    
    -- Links
    student_id BIGINT NOT NULL,
    zoho_registration_id VARCHAR(255) NOT NULL UNIQUE,
    zoho_program_id VARCHAR(255),
    
    -- Program Info
    program_name VARCHAR(255),
    level VARCHAR(50),
    study_mode VARCHAR(50),
    
    -- Registration Details
    registration_date DATE,
    registration_number VARCHAR(100),
    expected_completion_date DATE,
    completion_date DATE,
    registration_status VARCHAR(50),  -- Active, Completed, Suspended, Cancelled
    
    -- Financial Summary
    total_fees DECIMAL(10,2),
    paid_amount DECIMAL(10,2),
    remaining_amount DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'USD',
    
    -- Notes
    notes TEXT,
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    updated_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    CONSTRAINT fk_student FOREIGN KEY (student_id) 
        REFERENCES mdl_local_mzi_students(id) ON DELETE CASCADE
);

CREATE INDEX idx_mzi_registrations_student ON mdl_local_mzi_registrations(student_id);
CREATE INDEX idx_mzi_registrations_zoho ON mdl_local_mzi_registrations(zoho_registration_id);
CREATE INDEX idx_mzi_registrations_status ON mdl_local_mzi_registrations(registration_status);
```

#### Table 3: `mdl_local_mzi_installments`

**Purpose:** Store payment installments from Zoho subform

```sql
CREATE TABLE mdl_local_mzi_installments (
    id BIGSERIAL PRIMARY KEY,
    
    -- Links
    registration_id BIGINT NOT NULL,
    zoho_installment_id VARCHAR(255),
    
    -- Installment Details
    due_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50),  -- Pending, Paid, Overdue
    paid_date DATE,
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    CONSTRAINT fk_registration FOREIGN KEY (registration_id) 
        REFERENCES mdl_local_mzi_registrations(id) ON DELETE CASCADE
);

CREATE INDEX idx_mzi_installments_registration ON mdl_local_mzi_installments(registration_id);
CREATE INDEX idx_mzi_installments_due_date ON mdl_local_mzi_installments(due_date);
```

#### Table 4: `mdl_local_mzi_payments`

**Purpose:** Store payment history

```sql
CREATE TABLE mdl_local_mzi_payments (
    id BIGSERIAL PRIMARY KEY,
    
    -- Links
    registration_id BIGINT NOT NULL,
    student_id BIGINT NOT NULL,
    zoho_payment_id VARCHAR(255) NOT NULL UNIQUE,
    
    -- Payment Details
    payment_date DATE NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    original_amount DECIMAL(10,2),
    original_currency VARCHAR(10),
    
    -- Payment Method
    payment_method VARCHAR(100),  -- Cash, Visa, Bank Transfer, etc.
    voucher_number VARCHAR(100),
    reference_number VARCHAR(100),
    
    -- Additional Info
    notes TEXT,
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    CONSTRAINT fk_payment_registration FOREIGN KEY (registration_id) 
        REFERENCES mdl_local_mzi_registrations(id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_student FOREIGN KEY (student_id) 
        REFERENCES mdl_local_mzi_students(id) ON DELETE CASCADE
);

CREATE INDEX idx_mzi_payments_registration ON mdl_local_mzi_payments(registration_id);
CREATE INDEX idx_mzi_payments_student ON mdl_local_mzi_payments(student_id);
CREATE INDEX idx_mzi_payments_zoho ON mdl_local_mzi_payments(zoho_payment_id);
CREATE INDEX idx_mzi_payments_date ON mdl_local_mzi_payments(payment_date DESC);
```

#### Table 5: `mdl_local_mzi_classes`

**Purpose:** Store BTEC class information

```sql
CREATE TABLE mdl_local_mzi_classes (
    id BIGSERIAL PRIMARY KEY,
    
    -- Links
    zoho_class_id VARCHAR(255) NOT NULL UNIQUE,
    zoho_program_id VARCHAR(255),
    
    -- Class Details
    class_code VARCHAR(100),
    class_name VARCHAR(255) NOT NULL,
    unit_name VARCHAR(255),
    level VARCHAR(50),
    
    -- Schedule
    start_date DATE,
    end_date DATE,
    
    -- Teacher
    teacher_name VARCHAR(255),
    teacher_email VARCHAR(255),
    
    -- Class Status
    class_status VARCHAR(50),  -- Active, Completed, Cancelled
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    updated_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    UNIQUE(class_code)
);

CREATE INDEX idx_mzi_classes_zoho ON mdl_local_mzi_classes(zoho_class_id);
CREATE INDEX idx_mzi_classes_code ON mdl_local_mzi_classes(class_code);
CREATE INDEX idx_mzi_classes_status ON mdl_local_mzi_classes(class_status);
```

#### Table 6: `mdl_local_mzi_enrollments`

**Purpose:** Link students to classes

```sql
CREATE TABLE mdl_local_mzi_enrollments (
    id BIGSERIAL PRIMARY KEY,
    
    -- Links
    student_id BIGINT NOT NULL,
    class_id BIGINT NOT NULL,
    registration_id BIGINT,
    zoho_enrollment_id VARCHAR(255) NOT NULL UNIQUE,
    
    -- Enrollment Details
    enrollment_date DATE,
    start_date DATE,
    end_date DATE,
    
    -- Status
    enrollment_status VARCHAR(50),  -- Active, Completed, Dropped, Suspended
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    updated_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    CONSTRAINT fk_enrollment_student FOREIGN KEY (student_id) 
        REFERENCES mdl_local_mzi_students(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_class FOREIGN KEY (class_id) 
        REFERENCES mdl_local_mzi_classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_registration FOREIGN KEY (registration_id) 
        REFERENCES mdl_local_mzi_registrations(id) ON DELETE SET NULL,
    
    UNIQUE(student_id, class_id)
);

CREATE INDEX idx_mzi_enrollments_student ON mdl_local_mzi_enrollments(student_id);
CREATE INDEX idx_mzi_enrollments_class ON mdl_local_mzi_enrollments(class_id);
CREATE INDEX idx_mzi_enrollments_zoho ON mdl_local_mzi_enrollments(zoho_enrollment_id);
```

#### Table 7: `mdl_local_mzi_grades`

**Purpose:** Store assignment grades

```sql
CREATE TABLE mdl_local_mzi_grades (
    id BIGSERIAL PRIMARY KEY,
    
    -- Links
    student_id BIGINT NOT NULL,
    class_id BIGINT NOT NULL,
    enrollment_id BIGINT,
    zoho_grade_id VARCHAR(255) NOT NULL UNIQUE,
    
    -- Assignment Details
    assignment_name VARCHAR(255),
    moodle_grade_id VARCHAR(100),
    
    -- Grade Info
    btec_grade_name VARCHAR(50),  -- P1, M1, D2, etc.
    numeric_grade DECIMAL(5,2),
    grade_status VARCHAR(50),  -- Not Marked, In Marking, Completed, etc.
    
    -- Attempt Info
    attempt_number INT DEFAULT 1,
    attempt_date DATE,
    submission_date DATE,
    grading_date DATE,
    
    -- Feedback
    feedback TEXT,
    feedback_acknowledged_at BIGINT,
    acknowledged_by_user_id BIGINT,
    
    -- Learning Outcomes (JSON)
    learning_outcomes JSONB,  -- {"LO1": "Achieved", "LO2": "Pending", ...}
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    updated_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    CONSTRAINT fk_grade_student FOREIGN KEY (student_id) 
        REFERENCES mdl_local_mzi_students(id) ON DELETE CASCADE,
    CONSTRAINT fk_grade_class FOREIGN KEY (class_id) 
        REFERENCES mdl_local_mzi_classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_grade_enrollment FOREIGN KEY (enrollment_id) 
        REFERENCES mdl_local_mzi_enrollments(id) ON DELETE SET NULL
);

CREATE INDEX idx_mzi_grades_student ON mdl_local_mzi_grades(student_id);
CREATE INDEX idx_mzi_grades_class ON mdl_local_mzi_grades(class_id);
CREATE INDEX idx_mzi_grades_zoho ON mdl_local_mzi_grades(zoho_grade_id);
CREATE INDEX idx_mzi_grades_status ON mdl_local_mzi_grades(grade_status);
CREATE INDEX idx_mzi_grades_ack ON mdl_local_mzi_grades(feedback_acknowledged_at) WHERE feedback_acknowledged_at IS NOT NULL;
```

#### Table 8: `mdl_local_mzi_requests`

**Purpose:** Store student requests

```sql
CREATE TABLE mdl_local_mzi_requests (
    id BIGSERIAL PRIMARY KEY,
    
    -- Links
    student_id BIGINT NOT NULL,
    zoho_request_id VARCHAR(255) NOT NULL UNIQUE,
    moodle_user_id BIGINT NOT NULL,
    
    -- Request Details
    request_name VARCHAR(255),
    request_type VARCHAR(100),  -- Enroll Next Semester, Class Drop, etc.
    request_date BIGINT NOT NULL,
    
    -- Status
    status VARCHAR(50),  -- Pending, Approved, Rejected, Cancelled, Completed
    
    -- Content
    reason TEXT,
    academic_email VARCHAR(255),
    fees_amount DECIMAL(10,2),
    
    -- Additional Data (JSON)
    requested_classes JSONB,  -- For Enroll Next Semester
    change_information JSONB,  -- For Change Information
    
    -- Attachments
    attachment_urls TEXT,  -- Comma-separated URLs
    
    -- Response
    admin_response TEXT,
    response_date BIGINT,
    responded_by VARCHAR(255),
    
    -- Metadata
    synced_at BIGINT NOT NULL,
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    updated_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT,
    
    CONSTRAINT fk_request_student FOREIGN KEY (student_id) 
        REFERENCES mdl_local_mzi_students(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_moodle_user FOREIGN KEY (moodle_user_id) 
        REFERENCES mdl_user(id) ON DELETE CASCADE
);

CREATE INDEX idx_mzi_requests_student ON mdl_local_mzi_requests(student_id);
CREATE INDEX idx_mzi_requests_zoho ON mdl_local_mzi_requests(zoho_request_id);
CREATE INDEX idx_mzi_requests_type ON mdl_local_mzi_requests(request_type);
CREATE INDEX idx_mzi_requests_status ON mdl_local_mzi_requests(status);
CREATE INDEX idx_mzi_requests_date ON mdl_local_mzi_requests(request_date DESC);
```

#### Table 9: `mdl_local_mzi_sync_log`

**Purpose:** Track sync operations

```sql
CREATE TABLE mdl_local_mzi_sync_log (
    id BIGSERIAL PRIMARY KEY,
    
    -- Sync Details
    sync_type VARCHAR(50) NOT NULL,  -- full, incremental, manual, webhook
    module_name VARCHAR(100),  -- BTEC_Students, BTEC_Registrations, etc.
    action VARCHAR(50),  -- create, update, delete
    
    -- Status
    status VARCHAR(50) NOT NULL,  -- success, failed, partial
    
    -- Metrics
    records_total INT DEFAULT 0,
    records_success INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    
    -- Error Info
    error_message TEXT,
    error_details JSONB,
    
    -- Timing
    started_at BIGINT NOT NULL,
    completed_at BIGINT,
    duration_seconds INT,
    
    -- Trigger
    triggered_by VARCHAR(100),  -- admin_user_id, webhook, cron
    
    -- Metadata
    created_at BIGINT NOT NULL DEFAULT EXTRACT(EPOCH FROM NOW())::BIGINT
);

CREATE INDEX idx_mzi_sync_log_type ON mdl_local_mzi_sync_log(sync_type);
CREATE INDEX idx_mzi_sync_log_module ON mdl_local_mzi_sync_log(module_name);
CREATE INDEX idx_mzi_sync_log_status ON mdl_local_mzi_sync_log(status);
CREATE INDEX idx_mzi_sync_log_date ON mdl_local_mzi_sync_log(started_at DESC);
```

---

## 5. Zoho Modules Mapping

### 📊 Complete Zoho → Moodle Mapping

#### Module 1: BTEC_Students

| Zoho Field | API Name | Moodle Table | Moodle Column |
|------------|----------|--------------|---------------|
| Student ID | Name | local_mzi_students | student_id |
| First Name | First_Name | local_mzi_students | first_name |
| Last Name | Last_Name | local_mzi_students | last_name |
| First Name (Native) | Frist_Name_Native | local_mzi_students | first_name_native |
| Last Name (Native) | Last_Name_Native | local_mzi_students | last_name_native |
| Mother Name | Mother_Name | local_mzi_students | mother_name |
| Father Name | Father_Name | local_mzi_students | father_name |
| Email | Email | local_mzi_students | email |
| Phone | Phone | local_mzi_students | phone |
| Birth Date | Birth_Date | local_mzi_students | birth_date |
| Birth Place | Birth_Place | local_mzi_students | birth_place |
| Nationality | Nationality | local_mzi_students | nationality |
| National ID | National_ID_Number | local_mzi_students | national_id_number |
| Passport Number | Passport_Number | local_mzi_students | passport_number |
| Address | Address | local_mzi_students | address |
| BTEC RegNum | BTEC_Registration_Number | local_mzi_students | btec_registration_number |
| Photo | Photo | local_mzi_students | photo_url |

#### Module 2: BTEC_Registrations

| Zoho Field | API Name | Moodle Table | Moodle Column |
|------------|----------|--------------|---------------|
| Registration ID | Name | local_mzi_registrations | registration_number |
| Student ID | Student_ID | local_mzi_registrations | student_id (FK) |
| Program | Program | local_mzi_registrations | program_name |
| Level | Level | local_mzi_registrations | level |
| Study Mode | Study_Mode | local_mzi_registrations | study_mode |
| Registration Date | Registration_Date | local_mzi_registrations | registration_date |
| Registration Status | Registration_Status | local_mzi_registrations | registration_status |
| Total Fees | Total_Fees | local_mzi_registrations | total_fees |
| Paid Amount | Paid_Amount | local_mzi_registrations | paid_amount |
| Remaining Amount | Remaining_Amount | local_mzi_registrations | remaining_amount |
| Currency | Currency | local_mzi_registrations | currency |
| **Installments (Subform)** | Installments | local_mzi_installments | (separate table) |
| - Due Date | Due_Date | local_mzi_installments | due_date |
| - Amount | Amount | local_mzi_installments | amount |
| - Status | Status | local_mzi_installments | status |

#### Module 3: BTEC_Payments

| Zoho Field | API Name | Moodle Table | Moodle Column |
|------------|----------|--------------|---------------|
| Payment ID | Name | local_mzi_payments | zoho_payment_id |
| Payment Amount | Payment_Amount | local_mzi_payments | payment_amount |
| Payment Date | Payment_Date | local_mzi_payments | payment_date |
| Original Amount | SRM_Original_Amount | local_mzi_payments | original_amount |
| Original Currency | SRM_Original_Currency | local_mzi_payments | original_currency |
| Voucher Number | SRM_Voucher_Number | local_mzi_payments | voucher_number |
| Payment Method | Payment_Method | local_mzi_payments | payment_method |

#### Module 4: BTEC_Classes

| Zoho Field | API Name | Moodle Table | Moodle Column |
|------------|----------|--------------|---------------|
| Zoho ID | Name | local_mzi_classes | zoho_class_id |
| Class Name | Class_Name | local_mzi_classes | class_name |
| Class Code | Class_Code | local_mzi_classes | class_code |
| Unit Name | Unit_Name | local_mzi_classes | unit_name |
| Level | Level | local_mzi_classes | level |
| Start Date | Start_Date | local_mzi_classes | start_date |
| End Date | End_Date | local_mzi_classes | end_date |
| Teacher Name | Teacher_Name | local_mzi_classes | teacher_name |
| Class Status | Class_Status | local_mzi_classes | class_status |

#### Module 5: BTEC_Enrollments

| Zoho Field | API Name | Moodle Table | Moodle Column |
|------------|----------|--------------|---------------|
| Enrollment ID | Name | local_mzi_enrollments | zoho_enrollment_id |
| Class | Classes | local_mzi_enrollments | class_id (FK) |
| Student | Enrolled_Students | local_mzi_enrollments | student_id (FK) |
| Start Date | Start_Date | local_mzi_enrollments | start_date |
| End Date | End_Date | local_mzi_enrollments | end_date |
| Status | Enrollment_Status | local_mzi_enrollments | enrollment_status |

#### Module 6: BTEC_Grades

| Zoho Field | API Name | Moodle Table | Moodle Column |
|------------|----------|--------------|---------------|
| Grade Record ID | Name | local_mzi_grades | zoho_grade_id |
| Moodle Grade ID | Moodle_Grade_ID | local_mzi_grades | moodle_grade_id |
| BTEC Grade Name | BTEC_Grade_Name | local_mzi_grades | btec_grade_name |
| Attempt Date | Attempt_Date | local_mzi_grades | attempt_date |
| Attempt Number | Attempt_Number | local_mzi_grades | attempt_number |
| Grade Status | Grade_Status | local_mzi_grades | grade_status |
| Feedback | Feedback | local_mzi_grades | feedback |
| LO Status | LO_Status (subform) | local_mzi_grades | learning_outcomes (JSONB) |

#### Module 7: BTEC_Student_Requests

| Zoho Field | API Name | Moodle Table | Moodle Column |
|------------|----------|--------------|---------------|
| Request Name | Name | local_mzi_requests | request_name |
| Student | Student | local_mzi_requests | student_id (FK) |
| Request Type | Request_Type | local_mzi_requests | request_type |
| Request Date | Request_Date | local_mzi_requests | request_date |
| Status | Status | local_mzi_requests | status |
| Reason | Reason | local_mzi_requests | reason |
| Academic Email | Academic_Email | local_mzi_requests | academic_email |
| Fees Amount | Fees_Amount | local_mzi_requests | fees_amount |
| Moodle User ID | Moodle_User_ID | local_mzi_requests | moodle_user_id |
| **Requested Classes** | Requested_Classes | local_mzi_requests | requested_classes (JSONB) |
| **Change Information** | Change_Information | local_mzi_requests | change_information (JSONB) |
| Payment Receipt | Payment_Receipt | local_mzi_requests | attachment_urls |

---

## 6. Backend API Endpoints

**⚠️ IMPORTANT:** Backend has **ONLY webhook and sync endpoints**. **NO REST APIs for Student Dashboard.**

Student UI reads data **directly from Moodle database** using PHP `$DB` API.

### 🔌 Backend Endpoints (Webhook Processing Only)

**Base URL:** `https://your-backend.com/api/v1`

#### 6.1 Webhook Receiver (Core Functionality)

##### POST /webhooks/student_updated

Receives Zoho webhook when student data changes

**Request (from Zoho):**
```json
{
  "module": "BTEC_Students",
  "id": "5398830000123456789",
  "Phone": "+963 999 999 999",
  "Email": "student@example.com"
}
```

**Backend Action:**
```python
# Writes directly to Moodle DB:
UPDATE mdl_local_mzi_students 
SET phone = '+963 999 999 999',
    email = 'student@example.com',
    synced_at = CURRENT_TIMESTAMP
WHERE zoho_student_id = '5398830000123456789';
```

**Response:**
```json
{
  "status": "success",
  "processed_at": "2026-02-16T10:45:00Z"
}
```

##### POST /webhooks/registration_created

Receives Zoho webhook when new registration is created

**Request (from Zoho):**
```json
{
  "module": "BTEC_Registrations",
  "id": "5398830000145678901",
  "Student_ID": "5398830000123456789",
  "Program": "Digital Marketing",
  "Total_Fees": 15000,
  "Registration_Status": "Active"
}
```

**Backend Action:**
```python
INSERT INTO mdl_local_mzi_registrations (
  zoho_registration_id, student_id, program_name,
  total_fees, registration_status, synced_at
) VALUES (...);
```

##### POST /webhooks/payment_recorded

Receives Zoho webhook when payment is recorded

**Backend Action:**
```python
# 1. Insert payment
INSERT INTO mdl_local_mzi_payments (...);

# 2. Update registration balance
UPDATE mdl_local_mzi_registrations 
SET paid_amount = paid_amount + 3000,
    remaining_amount = total_fees - paid_amount;
```

#### 6.2 Manual Sync Endpoints (Admin Triggered)

##### POST /sync/full

Trigger full sync of all modules (one-time setup)

**Request:**
```json
{
  "modules": ["BTEC_Students", "BTEC_Registrations", "BTEC_Payments"],
  "triggered_by": "admin_user_123"
}
```

**Response:**
```json
{
  "sync_id": "SYNC-2026-001",
  "status": "in_progress",
  "started_at": "2026-02-16T10:00:00Z"
}
```

##### POST /sync/incremental

Sync changes since last sync

**Request:**
```json
{
  "module": "BTEC_Students",
  "since": "2026-02-15T00:00:00Z"
}
```

#### 6.3 Request Submission (Reverse Direction)

##### POST /requests/submit_to_zoho

**When:** Student submits request in Moodle UI

**Flow:**
```
1. Student clicks "Request Class Drop" in UI
2. PHP inserts into mdl_local_mzi_requests (status = submitted)
3. PHP calls Backend: POST /requests/submit_to_zoho
4. Backend creates record in Zoho CRM
5. Admin approves in Zoho
6. Zoho sends webhook
7. Backend updates mdl_local_mzi_requests (status = approved)
```

**Request:**
```json
{
  "student_id": "ST-000123",
  "request_type": "Class Drop",
  "reason": "Schedule conflict"
}
```

**Backend Action:**
```python
# Create in Zoho
zoho_record_id = zoho_client.create_record(
    module="BTEC_Student_Requests",
    data={...}
)

# Update Moodle with Zoho ID
UPDATE mdl_local_mzi_requests 
SET zoho_request_id = '...'
WHERE id = ...;
```

---

### ❌ What Backend Does NOT Have

```
❌ GET /students/{id}/profile          - Not needed (PHP reads DB)
❌ GET /students/{id}/registrations    - Not needed (PHP reads DB)
❌ GET /registrations/{id}/financial   - Not needed (PHP reads DB)
❌ GET /enrollments?student_id={id}    - Not needed (PHP reads DB)
❌ GET /grades/{id}/feedback           - Not needed (PHP reads DB)
❌ POST /grades/{id}/acknowledge       - PHP updates DB directly
```

**Reason:** Student UI uses direct SQL queries. No API layer needed.

---

### 📋 Complete Backend Endpoint List (Final)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| **/webhooks/student_updated** | POST | Zoho → Backend → Moodle DB |
| **/webhooks/registration_created** | POST | Zoho → Backend → Moodle DB |
| **/webhooks/payment_recorded** | POST | Zoho → Backend → Moodle DB |
| **/webhooks/grade_updated** | POST | Zoho → Backend → Moodle DB |
| **/webhooks/request_status_changed** | POST | Zoho → Backend → Moodle DB |
| **/sync/full** | POST | Admin triggers full sync |
| **/sync/incremental** | POST | Sync changes since date |
| **/requests/submit_to_zoho** | POST | Moodle → Backend → Zoho |
| **/health** | GET | Health check |

**Total:** 9 endpoints (all write operations, no reads)
```json
{
  "status": "success",
  "processed_at": "2026-02-16T10:45:00Z"
}
```

---

## 7. Sync Strategy

### 🔄 Three-Tier Sync Approach

#### 7.1 Initial Full Sync (One-Time)

**Trigger:** Admin clicks "Sync All Data" for first time

**Process:**
```
1. Admin initiates sync from Moodle UI
2. Backend fetches ALL records from each Zoho module:
   - BTEC_Students (all students)
   - BTEC_Registrations (all registrations)
   - BTEC_Payments (all payments)
   - BTEC_Classes (all classes)
   - BTEC_Enrollments (all enrollments)
   - BTEC_Grades (all grades)
   - BTEC_Student_Requests (all requests)
   
3. Backend transforms Zoho data → Moodle structure
4. Backend inserts into Moodle PostgreSQL tables
5. Backend creates sync log entry
6. UI shows progress and completion
```

**Implementation:**

```python
# backend/app/api/v1/endpoints/sync.py

@router.post("/sync/full")
async def full_sync(
    sync_request: FullSyncRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_admin_user)
):
    """
    Perform full sync of all Zoho modules.
    
    This is typically run once during initial setup or after major changes.
    """
    
    sync_log = SyncLog(
        sync_type="full",
        status="in_progress",
        started_at=int(time.time()),
        triggered_by=f"admin_{current_user.id}"
    )
    db.add(sync_log)
    db.commit()
    
    results = {}
    
    # Module sync order (respects dependencies)
    modules = [
        "BTEC_Students",
        "BTEC_Registrations",
        "BTEC_Payments",
        "BTEC_Classes",
        "BTEC_Enrollments",
        "BTEC_Grades",
        "BTEC_Student_Requests"
    ]
    
    for module in modules:
        try:
            result = await sync_module(module, db)
            results[module] = result
            
            # Update progress
            await send_progress_update(module, result)
            
        except Exception as e:
            logger.error(f"Error syncing {module}: {str(e)}")
            results[module] = {"status": "failed", "error": str(e)}
    
    # Update sync log
    sync_log.status = "completed"
    sync_log.completed_at = int(time.time())
    sync_log.records_total = sum(r.get("total", 0) for r in results.values())
    sync_log.records_success = sum(r.get("success", 0) for r in results.values())
    sync_log.records_failed = sum(r.get("failed", 0) for r in results.values())
    db.commit()
    
    return {
        "sync_id": sync_log.id,
        "status": "completed",
        "results": results
    }


async def sync_module(module_name: str, db: Session) -> dict:
    """Sync a single Zoho module to Moodle database."""
    
    # Initialize Zoho client
    zoho = ZohoClient()
    
    # Fetch all records (paginated)
    all_records = []
    page = 1
    per_page = 200
    
    while True:
        response = await zoho.get_records(
            module=module_name,
            page=page,
            per_page=per_page
        )
        
        records = response.get("data", [])
        if not records:
            break
        
        all_records.extend(records)
        page += 1
        
        if len(records) < per_page:
            break
    
    # Transform and insert
    success_count = 0
    failed_count = 0
    
    for record in all_records:
        try:
            transformed = transform_zoho_record(module_name, record)
            upsert_to_moodle(module_name, transformed, db)
            success_count += 1
        except Exception as e:
            logger.error(f"Failed to sync record {record.get('id')}: {str(e)}")
            failed_count += 1
    
    return {
        "status": "completed",
        "total": len(all_records),
        "success": success_count,
        "failed": failed_count
    }
```

#### 7.2 Webhook-Driven Sync (Real-Time)

**Trigger:** Zoho sends webhook when record is created/updated/deleted

**Process:**
```
1. Zoho detects change (e.g., student updated in CRM)
2. Zoho sends webhook to backend endpoint
3. Backend validates webhook signature
4. Backend fetches updated record from Zoho API
5. Backend transforms and updates Moodle database
6. Backend logs sync event
```

**Zoho Webhook Configuration:**

```json
{
  "webhook_url": "https://your-backend.com/api/v1/sync/webhook",
  "modules": [
    "BTEC_Students",
    "BTEC_Registrations",
    "BTEC_Payments",
    "BTEC_Grades",
    "BTEC_Student_Requests"
  ],
  "events": ["create", "update", "delete"],
  "authentication": {
    "type": "hmac",
    "secret": "your_webhook_secret"
  }
}
```

**Implementation:**

```python
# backend/app/api/v1/endpoints/sync.py

@router.post("/sync/webhook")
async def handle_webhook(
    request: Request,
    db: Session = Depends(get_db)
):
    """
    Handle incoming webhooks from Zoho.
    
    Webhooks are sent when records are created, updated, or deleted in Zoho.
    """
    
    # Validate webhook signature
    signature = request.headers.get("X-Zoho-Signature")
    body = await request.body()
    
    if not verify_webhook_signature(signature, body):
        raise HTTPException(status_code=401, detail="Invalid webhook signature")
    
    # Parse webhook data
    webhook_data = await request.json()
    
    module = webhook_data.get("module")
    action = webhook_data.get("action")  # create, update, delete
    record_id = webhook_data.get("record_id")
    
    logger.info(f"Webhook received: {module} - {action} - {record_id}")
    
    # Create sync log
    sync_log = SyncLog(
        sync_type="webhook",
        module_name=module,
        action=action,
        started_at=int(time.time()),
        triggered_by="zoho_webhook"
    )
    db.add(sync_log)
    db.commit()
    
    try:
        if action in ["create", "update"]:
            # Fetch latest data from Zoho
            zoho = ZohoClient()
            record = await zoho.get_record(module, record_id)
            
            # Transform and upsert
            transformed = transform_zoho_record(module, record)
            upsert_to_moodle(module, transformed, db)
            
            sync_log.status = "success"
            sync_log.records_success = 1
            
        elif action == "delete":
            # Delete from Moodle database
            delete_from_moodle(module, record_id, db)
            
            sync_log.status = "success"
            sync_log.records_success = 1
        
    except Exception as e:
        logger.error(f"Webhook processing failed: {str(e)}")
        sync_log.status = "failed"
        sync_log.error_message = str(e)
        sync_log.records_failed = 1
    
    finally:
        sync_log.completed_at = int(time.time())
        db.commit()
    
    return {"status": "processed", "sync_log_id": sync_log.id}
```

#### 7.3 Manual Sync (On-Demand)

**Trigger:** Admin clicks "Sync Now" button in Moodle UI

**Process:**
```
1. Admin clicks sync button for specific module or all
2. Backend fetches updated records since last sync
3. Backend updates Moodle database
4. UI shows results
```

**Implementation:**

```python
@router.post("/sync/manual")
async def manual_sync(
    sync_request: ManualSyncRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(get_current_admin_user)
):
    """
    Perform manual sync of specific module(s).
    
    Used by admins to refresh data on-demand.
    """
    
    modules = sync_request.modules or ["BTEC_Students"]  # Default to students
    
    results = {}
    
    for module in modules:
        # Get last sync time
        last_sync = db.query(SyncLog).filter(
            SyncLog.module_name == module,
            SyncLog.status == "success"
        ).order_by(SyncLog.completed_at.desc()).first()
        
        last_sync_time = last_sync.completed_at if last_sync else 0
        
        # Fetch records modified since last sync
        zoho = ZohoClient()
        records = await zoho.get_records(
            module=module,
            modified_since=datetime.fromtimestamp(last_sync_time)
        )
        
        # Sync records
        result = await sync_records(module, records, db)
        results[module] = result
    
    return {
        "status": "completed",
        "results": results
    }
```

### 🔄 Sync Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                       SYNC STRATEGY                          │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 1️⃣ INITIAL FULL SYNC (One-time)                            │
├─────────────────────────────────────────────────────────────┤
│ Admin clicks "Sync All Data"                                │
│         ↓                                                    │
│ Backend fetches ALL records from ALL modules                │
│         ↓                                                    │
│ Transform & insert into Moodle DB                           │
│         ↓                                                    │
│ ✅ Database populated with historical data                  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 2️⃣ WEBHOOK SYNC (Real-time)                                │
├─────────────────────────────────────────────────────────────┤
│ Student updated in Zoho                                      │
│         ↓                                                    │
│ Zoho sends webhook → Backend                                │
│         ↓                                                    │
│ Backend validates & fetches latest data                     │
│         ↓                                                    │
│ Update Moodle DB record                                     │
│         ↓                                                    │
│ ✅ Student sees updated data immediately                    │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ 3️⃣ MANUAL SYNC (On-demand)                                 │
├─────────────────────────────────────────────────────────────┤
│ Admin clicks "Sync Now" button                              │
│         ↓                                                    │
│ Backend fetches records modified since last sync            │
│         ↓                                                    │
│ Update Moodle DB                                            │
│         ↓                                                    │
│ ✅ Admin sees refresh complete                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 8. UI Specifications

### 🎨 Design System

#### Color Palette

```css
/* Primary Colors */
--primary: #667eea;          /* Purple - Main brand */
--primary-dark: #5568d3;     /* Darker purple for hover */
--primary-light: #7c94f5;    /* Lighter purple for backgrounds */

/* Secondary Colors */
--secondary: #764ba2;        /* Deep purple - Accent */
--success: #4caf50;          /* Green - Success states */
--warning: #ff9800;          /* Orange - Warnings */
--danger: #f44336;           /* Red - Errors/Critical */
--info: #2196f3;             /* Blue - Information */

/* Neutral Colors */
--gray-100: #f8f9fa;
--gray-200: #e9ecef;
--gray-300: #dee2e6;
--gray-400: #ced4da;
--gray-500: #adb5bd;
--gray-600: #6c757d;
--gray-700: #495057;
--gray-800: #343a40;
--gray-900: #212529;

/* Background */
--bg-body: #f5f7fa;
--bg-card: #ffffff;
--bg-hover: #f0f2f5;
```

#### Typography

```css
/* Fonts */
font-family-base: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
font-family-code: 'Courier New', 'Menlo', monospace;

/* Font Sizes */
--font-xs: 0.75rem;    /* 12px */
--font-sm: 0.875rem;   /* 14px */
--font-base: 1rem;     /* 16px */
--font-lg: 1.125rem;   /* 18px */
--font-xl: 1.25rem;    /* 20px */
--font-2xl: 1.5rem;    /* 24px */
--font-3xl: 1.875rem;  /* 30px */
```

#### Spacing

```css
--spacing-xs: 0.25rem;  /* 4px */
--spacing-sm: 0.5rem;   /* 8px */
--spacing-md: 1rem;     /* 16px */
--spacing-lg: 1.5rem;   /* 24px */
--spacing-xl: 2rem;     /* 32px */
--spacing-2xl: 3rem;    /* 48px */
```

#### Components

##### Card Component

```css
.mzi-card {
    background: var(--bg-card);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
    transition: box-shadow 0.2s ease;
}

.mzi-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.mzi-card-header {
    border-bottom: 1px solid var(--gray-200);
    padding-bottom: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.mzi-card-title {
    font-size: var(--font-xl);
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
}
```

##### Badge Component

```css
.mzi-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    font-size: var(--font-sm);
    font-weight: 500;
    border-radius: 12px;
    line-height: 1;
}

.mzi-badge-success {
    background: #e8f5e9;
    color: #2e7d32;
}

.mzi-badge-warning {
    background: #fff3e0;
    color: #e65100;
}

.mzi-badge-danger {
    background: #ffebee;
    color: #c62828;
}

.mzi-badge-info {
    background: #e3f2fd;
    color: #1565c0;
}
```

##### Button Component

```css
.mzi-btn {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    font-size: var(--font-base);
    font-weight: 500;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mzi-btn-primary {
    background: var(--primary);
    color: white;
}

.mzi-btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.mzi-btn-secondary {
    background: var(--gray-200);
    color: var(--gray-700);
}

.mzi-btn-secondary:hover {
    background: var(--gray-300);
}
```

##### Progress Bar

```css
.mzi-progress {
    height: 24px;
    background: var(--gray-200);
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.mzi-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: var(--font-sm);
    font-weight: 600;
    transition: width 0.3s ease;
}
```

### 📱 Mobile Responsive

#### Breakpoints

```css
/* Mobile First Approach */

/* Extra Small (default) */
@media (min-width: 0px) {
    /* Mobile phones */
}

/* Small */
@media (min-width: 576px) {
    /* Large phones */
}

/* Medium */
@media (min-width: 768px) {
    /* Tablets */
}

/* Large */
@media (min-width: 992px) {
    /* Desktops */
}

/* Extra Large */
@media (min-width: 1200px) {
    /* Large desktops */
}
```

#### Mobile Navigation

```css
/* Mobile: Bottom Navigation */
@media (max-width: 767px) {
    .mzi-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        border-top: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-around;
        padding: 0.5rem;
        z-index: 1000;
    }
    
    .mzi-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        font-size: var(--font-xs);
        color: var(--gray-600);
        text-decoration: none;
    }
    
    .mzi-nav-item.active {
        color: var(--primary);
    }
}

/* Desktop: Top Tabs */
@media (min-width: 768px) {
    .mzi-nav {
        display: flex;
        border-bottom: 2px solid var(--gray-200);
        margin-bottom: var(--spacing-lg);
    }
    
    .mzi-nav-item {
        padding: var(--spacing-md) var(--spacing-lg);
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
    }
    
    .mzi-nav-item.active {
        border-bottom-color: var(--primary);
        color: var(--primary);
    }
}
```

#### Mobile Cards

```css
@media (max-width: 767px) {
    .mzi-card {
        padding: var(--spacing-md);
        margin-bottom: var(--spacing-md);
    }
    
    /* Stack elements vertically */
    .mzi-card .row {
        flex-direction: column;
    }
    
    /* Full width tables */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Simplify complex layouts */
    .mzi-student-info {
        flex-direction: column;
        text-align: center;
    }
    
    .mzi-student-photo {
        margin: 0 auto var(--spacing-md);
    }
}
```

---

## 9. Student Card Design

### 🎴 Card Specifications

**Format:** HTML/CSS → PDF  
**Size:** CR80 (85.6mm × 53.98mm) - Standard credit card size  
**Print Quality:** 300 DPI  
**Orientation:** Landscape  
**Sides:** Front and Back

### Design Details

See [STUDENT_CARD_DESIGN.md](STUDENT_CARD_DESIGN.md) for:
- Full HTML/CSS templates (Front & Back)
- PHP controller code
- QR code generation
- PDF generation options
- Print workflow

### Card Generation Workflow

```
Student Profile Page
    ↓ (clicks "Download Card")
Backend checks:
    - Status = Active ✅
    - Has approved card request
    ↓ (if approved)
Generate HTML from template
    ↓
Convert to PDF (wkhtmltopdf)
    ↓
Return PDF to browser
    ↓
Student downloads/prints
```

---

## 10. Security & Permissions

### 🔒 Access Control

#### User Roles

| Role | Permissions |
|------|-------------|
| **Student** | View own profile, programs, classes, grades, requests |
| **Teacher** | View enrolled students, update grades, view class rosters |
| **Admin** | Full access: view all students, manage sync, approve requests |

#### Permission Matrix

| Action | Student | Teacher | Admin |
|--------|---------|---------|-------|
| View own profile | ✅ | ✅ | ✅ |
| View other profiles | ❌ | ✅ (enrolled) | ✅ |
| Submit request | ✅ | ❌ | ❌ |
| Approve request | ❌ | ❌ | ✅ |
| View own grades | ✅ | ❌ | ✅ |
| Update grades | ❌ | ✅ | ✅ |
| Trigger sync | ❌ | ❌ | ✅ |
| Download card | ✅ (own) | ❌ | ✅ (all) |

### 🔐 Data Privacy

#### Sensitive Fields

- **National ID Number:** Hidden by default, show on click
- **Passport Number:** Admin only
- **Phone Number:** Masked for non-owners
- **Address:** Student and Admin only
- **Financial Data:** Student and Admin only

#### Encryption

```python
# Encrypt sensitive fields before storing
from cryptography.fernet import Fernet

def encrypt_field(value: str) -> str:
    """Encrypt sensitive data before storing in database."""
    f = Fernet(settings.ENCRYPTION_KEY)
    return f.encrypt(value.encode()).decode()

def decrypt_field(encrypted_value: str) -> str:
    """Decrypt sensitive data when retrieving."""
    f = Fernet(settings.ENCRYPTION_KEY)
    return f.decrypt(encrypted_value.encode()).decode()
```

### 🛡️ API Security

#### Authentication

```python
# Token-based authentication
from fastapi import Depends, HTTPException
from fastapi.security import HTTPBearer

security = HTTPBearer()

async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """Validate JWT token and return user."""
    token = credentials.credentials
    
    try:
        payload = jwt.decode(token, settings.SECRET_KEY, algorithms=["HS256"])
        user_id = payload.get("user_id")
        
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid token")
        
        user = db.query(User).filter(User.id == user_id).first()
        if not user:
            raise HTTPException(status_code=401, detail="User not found")
        
        return user
        
    except jwt.ExpiredSignatureError:
        raise HTTPException(status_code=401, detail="Token expired")
    except jwt.JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")
```

#### Rate Limiting

```python
from slowapi import Limiter
from slowapi.util import get_remote_address

limiter = Limiter(key_func=get_remote_address)

@app.get("/api/v1/students/{student_id}")
@limiter.limit("10/minute")  # Max 10 requests per minute
async def get_student(student_id: str):
    pass
```

---

## 11. Implementation Plan

### 📅 4-Week Implementation Timeline

#### **Week 1: Database & Backend Foundation** 🛠️

##### Day 1-2: Database Setup
- [ ] Create all 9 Moodle tables
- [ ] Add indexes and foreign keys
- [ ] Create sample data for testing
- [ ] Write migration scripts

##### Day 3-4: Backend Core
- [ ] Setup FastAPI project structure
- [ ] Implement Zoho API client
- [ ] Create data transformation functions
- [ ] Write SQLAlchemy models

##### Day 5: Sync Engine
- [ ] Implement full sync logic
- [ ] Create webhook handler
- [ ] Add sync logging
- [ ] Test with sample data

##### Day 6-7: API Endpoints
- [ ] Students endpoints
- [ ] Registrations endpoints
- [ ] Enrollments/Classes endpoints
- [ ] Grades endpoints
- [ ] Requests endpoints

**Deliverable:** Working backend API with full sync capability ✅

---

#### **Week 2: Moodle Plugin UI** 🎨

##### Day 1-2: Profile Page
- [ ] Create profile.php
- [ ] Display student information
- [ ] Add request buttons
- [ ] Implement National ID toggle
- [ ] Test on mobile

##### Day 3-4: My Programs Page
- [ ] Create programs.php
- [ ] Display registrations cards
- [ ] Show financial summaries
- [ ] Add installments tables
- [ ] Add payments history
- [ ] Implement sorting logic

##### Day 5-6: Classes & Grades
- [ ] Create classes.php
- [ ] Display class cards
- [ ] Show assignments table
- [ ] Create grade modal
- [ ] Add feedback display
- [ ] Implement acknowledgement

##### Day 7: Requests Page
- [ ] Create requests.php
- [ ] Build dynamic forms
- [ ] Add request submission
- [ ] Display requests list
- [ ] Add status badges

**Deliverable:** Complete student-facing UI ✅

---

#### **Week 3: Admin UI & Sync Dashboard** 👨‍💼

##### Day 1-2: Students Management
- [ ] Create admin/students.php
- [ ] Add students table with filters
- [ ] Implement search functionality
- [ ] Add view student details modal
- [ ] Create sync controls

##### Day 3-4: Sync Dashboard
- [ ] Create admin/sync.php
- [ ] Add "Sync All" button
- [ ] Show sync progress indicators
- [ ] Display sync history log
- [ ] Add error handling UI

##### Day 5-6: Requests Management
- [ ] Create admin/requests.php
- [ ] Display all requests table
- [ ] Add filter by type/status
- [ ] Implement approve/reject actions
- [ ] Add admin response form

##### Day 7: Student Card
- [ ] Create student_card.php
- [ ] Design card template (front/back)
- [ ] Add QR code generation
- [ ] Implement PDF generation
- [ ] Test printing

**Deliverable:** Complete admin dashboard + student card ✅

---

#### **Week 4: Testing, Polish & Deployment** 🚀

##### Day 1-3: Testing
- [ ] Backend API tests (pytest)
- [ ] Database integrity tests
- [ ] Sync accuracy verification
- [ ] UI functionality tests
- [ ] Mobile responsive tests
- [ ] Cross-browser testing
- [ ] Load testing

##### Day 4-5: Documentation
- [ ] User Guide (Students)
- [ ] Admin Manual
- [ ] API Documentation
- [ ] Technical Architecture Docs
- [ ] Troubleshooting Guide

##### Day 6: Deployment Prep
- [ ] Production database setup
- [ ] Environment configuration
- [ ] SSL/Security setup
- [ ] Backup strategy
- [ ] Monitoring setup

##### Day 7: Go Live
- [ ] Deploy backend to production
- [ ] Install Moodle plugin
- [ ] Run initial full sync
- [ ] Configure Zoho webhooks
- [ ] Monitor for issues
- [ ] Celebrate! 🎉

**Deliverable:** Production-ready system ✅

---

### 🎯 Success Criteria

| Metric | Target |
|--------|--------|
| **Sync Accuracy** | 99.9% data accuracy |
| **Sync Speed** | < 30 seconds for webhook sync |
| **Page Load Time** | < 2 seconds for dashboard |
| **Mobile Score** | 90+ on Google Lighthouse |
| **API Uptime** | 99.5% availability |
| **Error Rate** | < 0.1% of requests |

---

## 12. Testing Strategy

### 🧪 Test Plan

#### Unit Tests

```python
# tests/test_sync.py

import pytest
from app.services.sync import transform_zoho_record

def test_transform_student_record():
    """Test Zoho student record transformation."""
    
    zoho_record = {
        "id": "5398830000123456789",
        "First_Name": "Ahmed",
        "Last_Name": "Ali",
        "Email": "ahmed@test.com",
        "Phone": "+963999999999"
    }
    
    result = transform_zoho_record("BTEC_Students", zoho_record)
    
    assert result["zoho_student_id"] == "5398830000123456789"
    assert result["first_name"] == "Ahmed"
    assert result["last_name"] == "Ali"
    assert result["email"] == "ahmed@test.com"


def test_calculate_overall_grade():
    """Test overall grade calculation."""
    
    grades = [
        {"btec_grade_name": "D1"},
        {"btec_grade_name": "D2"},
        {"btec_grade_name": "D3"}
    ]
    
    result = calculate_overall_grade(grades)
    
    assert result == "Distinction (D)"
```

#### Integration Tests

```python
# tests/test_api.py

import pytest
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)

def test_get_student_profile():
    """Test student profile endpoint."""
    
    response = client.get(
        "/api/v1/students/ST-000123",
        headers={"Authorization": f"Bearer {test_token}"}
    )
    
    assert response.status_code == 200
    data = response.json()
    assert data["student_id"] == "ST-000123"
    assert "first_name" in data
    assert "email" in data


def test_submit_request():
    """Test request submission."""
    
    request_data = {
        "student_id": "ST-000123",
        "request_type": "Student Card",
        "reason": "First time"
    }
    
    response = client.post(
        "/api/v1/requests",
        json=request_data,
        headers={"Authorization": f"Bearer {test_token}"}
    )
    
    assert response.status_code == 201
    data = response.json()
    assert data["status"] == "success"
    assert "request_id" in data
```

#### UI Tests

```javascript
// tests/ui/test_profile.js

describe('Student Profile Page', () => {
    
    it('should display student information', () => {
        cy.visit('/local/moodle_zoho_sync/ui/student/profile.php');
        cy.contains('Ahmed Ali Hassan');
        cy.contains('ST-000123');
    });
    
    it('should toggle National ID visibility', () => {
        cy.get('#nationalID').should('not.be.visible');
        cy.contains('Show National ID').click();
        cy.get('#nationalID').should('be.visible');
    });
    
    it('should open request form', () => {
        cy.contains('Request Student Card').click();
        cy.url().should('include', '/requests.php');
    });
    
});
```

---

## 📝 Appendices

### A. Glossary

| Term | Definition |
|------|------------|
| **BTEC** | Business and Technology Education Council - UK vocational qualification |
| **CRM** | Customer Relationship Management (Zoho CRM) |
| **Enrollment** | Student registration in a specific class |
| **LO** | Learning Outcome - Specific skills/knowledge to achieve |
| **Registration** | Student enrollment in a program (e.g., Diploma) |
| **Sync** | Synchronization of data between Zoho and Moodle |
| **Webhook** | HTTP callback sent by Zoho when data changes |

### B. Contact Information

| Role | Name | Email |
|------|------|-------|
| **Project Manager** | TBD | pm@abchorizon.com |
| **Lead Developer** | TBD | dev@abchorizon.com |
| **System Admin** | TBD | admin@abchorizon.com |

### C. Related Documents

- [Student Card Design](STUDENT_CARD_DESIGN.md)
- [API Documentation](API_DOCUMENTATION.md)
- [Database Schema](DATABASE_SCHEMA.md)
- [Deployment Guide](DEPLOYMENT_GUIDE.md)

---

## ✅ Final Checklist

- [x] Complete specification document
- [ ] Database tables created
- [ ] Backend API implemented
- [ ] Moodle plugin developed
- [ ] Student card designed
- [ ] Testing completed
- [ ] Documentation written
- [ ] Deployment ready

---

**Document Version:** 1.0.0  
**Last Updated:** February 16, 2026  
**Status:** ✅ Ready for Implementation

---

**🎯 Next Steps:**

1. **Review** this document with the team
2. **Setup** development environment
3. **Create** database tables
4. **Begin** Week 1 implementation

**Let's build this! 🚀**
