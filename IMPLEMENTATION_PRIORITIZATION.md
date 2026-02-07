# Implementation Prioritization Guide - BTEC Educational Context
**Context:** ABC Horizon BTEC Programs  
**Date:** February 4, 2026  
**Stakeholders:** Students, Teachers, Internal Verifiers, Admins, Finance Team

---

## 🎯 Prioritization Framework

### Critical Success Factors for BTEC Institution:
1. **Accreditation Compliance** - BTEC requires detailed assessment records (P/M/D criteria)
2. **Student Access** - Students must be enrolled to access courses
3. **Assessment Tracking** - Teachers and IVs need accurate grade records
4. **Student Experience** - Students need visibility into their progress
5. **Financial Records** - Payment tracking for enrollment validity
6. **Operational Efficiency** - Admin tools for troubleshooting

---

## 📊 Gap Analysis by Business Impact

### Legend:
- 🔴 **Blocker** - System won't function correctly
- 🟡 **Critical** - Major feature gap, workaround possible
- 🟢 **Important** - Quality of life improvement
- ⚪ **Nice-to-have** - Can defer indefinitely

---

## PRIORITY 1️⃣: MUST FIX BEFORE ANY DEPLOYMENT (Week 1-2)

### 🔴 P1.1: BTEC Learning Outcomes in Grade Sync
**Gap:** Grade payload doesn't include rubric criteria breakdown (P1-P10, M1-M9, D1-D6)

**Why This is #1 Priority:**
- ❗ **BTEC Accreditation Risk**: Pearson/BTEC requires evidence of criterion-based assessment
- ❗ **Internal Verification**: IVs need to see which criteria were met
- ❗ **Audit Trail**: Missing assessment breakdown = compliance failure
- ❗ **Cannot Retroactively Fix**: Once grades submitted without criteria, data is lost

**Business Impact:**
- **Risk Level:** CRITICAL - Could affect program accreditation
- **Affects:** All BTEC students, teachers, IVs, external verifiers
- **Workaround:** None - manual entry in Zoho is impractical at scale

**User Story:**
> As a BTEC teacher, when I grade a student's assignment using the rubric, I need all P/M/D criteria results sent to Zoho so that Internal Verifiers and External Verifiers can audit the assessment and confirm it meets BTEC standards.

**Implementation Steps:**
1. Update `data_extractor.php::extract_grade_data()` to query rubric tables
2. Extract criterion IDs and descriptions from `gradingform_rubric_criteria`
3. Get student's grade for each criterion from `gradingform_rubric_fillings`
4. Map Moodle criteria → BTEC criteria (P1, P2, M1, etc.) using naming convention
5. Add `learning_outcomes` array to payload:
   ```php
   $grade_data['learning_outcomes'] = [
       ['criterion' => 'P1', 'description' => 'Explain concepts...', 'achieved' => true],
       ['criterion' => 'P2', 'description' => 'Describe process...', 'achieved' => true],
       ['criterion' => 'M1', 'description' => 'Analyze data...', 'achieved' => false],
       // etc.
   ];
   ```
6. Backend must populate Zoho `Learning_Outcomes_Assessm` subform

**Acceptance Test:**
- [ ] Teacher grades assignment with rubric (P1✓, P2✓, M1✗, D1✓)
- [ ] Webhook sent to Backend contains `learning_outcomes` array
- [ ] Zoho `BTEC_Grades` record shows breakdown in subform
- [ ] Internal Verifier can see which criteria were achieved

**Effort:** 3-4 days  
**Developer:** Mid-level (familiar with Moodle grading API)

---

### 🔴 P1.2: BTEC Grade Conversion (Pass/Merit/Distinction)
**Gap:** Grades sent as 0-100 numeric instead of BTEC letter grades

**Why This is #2 Priority:**
- ❗ **BTEC Standard**: BTEC uses Pass/Merit/Distinction/Refer classification
- ❗ **Reporting**: Zoho dashboards expect BTEC grades for statistics
- ❗ **Student Transcripts**: Official transcripts must show BTEC grades
- ✅ **Easy to Fix Retroactively**: Can recalculate from numeric grades if needed

**Business Impact:**
- **Risk Level:** HIGH - Affects reporting and transcripts
- **Affects:** All BTEC students, administrators, external reporting
- **Workaround:** Backend could convert, but plugin should send correct format

**User Story:**
> As a BTEC administrator, when I generate reports on student performance, I need to see Pass/Merit/Distinction grades, not numeric percentages, so that reports match BTEC standards and can be submitted to Pearson.

**Implementation Steps:**
1. Add admin setting: "Grade Scale Type" (BTEC 0-4, Percentage 0-100, Points)
2. Create `btec_grade_converter.php` helper class:
   ```php
   if ($grademax == 4) { // BTEC scale
       $btec_grade = self::convert_to_btec($finalgrade);
       // 0-1.99 = Refer, 2-2.99 = Pass, 3-3.99 = Merit, 4 = Distinction
   }
   ```
3. Add both to payload: `finalgrade` (0-100) and `btec_grade` (letter)
4. Backend uses `btec_grade` if present, otherwise `finalgrade`

**Acceptance Test:**
- [ ] Assignment graded: 4/4 → sends "Distinction"
- [ ] Assignment graded: 3.5/4 → sends "Merit"
- [ ] Assignment graded: 2.1/4 → sends "Pass"
- [ ] Assignment graded: 1.5/4 → sends "Refer"
- [ ] Non-BTEC course graded: sends numeric grade only

**Effort:** 1-2 days  
**Developer:** Junior-level (simple conversion logic)

---

### 🟡 P1.3: Backend Health Monitoring + Fallback Queue
**Gap:** If Backend is down, webhooks fail silently

**Why This is #3 Priority:**
- ❗ **Data Loss Risk**: Events lost if Backend unavailable
- ✅ **System Resilience**: Production systems need monitoring
- ⚠️ **Silent Failures**: Admins won't know sync is broken

**Business Impact:**
- **Risk Level:** MEDIUM-HIGH - Can cause sync gaps
- **Affects:** All sync operations
- **Workaround:** Manual retry via admin dashboard

**User Story:**
> As a Moodle administrator, if the Backend API goes down, I need the plugin to queue failed webhooks locally and automatically retry when Backend is back online, so that no student grades are lost.

**Implementation Steps:**
1. Enhance `health_monitor.php` scheduled task:
   - Test Backend connection every 5 minutes
   - Log last successful connection timestamp
   - Update config: `backend_last_healthy`
2. Add admin dashboard widget: "Backend Status"
   - Green: Healthy (< 5 min since last success)
   - Yellow: Degraded (5-30 min)
   - Red: Down (> 30 min)
3. Email notification if down > 30 minutes
4. Automatic retry: `retry_failed_webhooks` task already exists, ensure it runs every 15 min

**Acceptance Test:**
- [ ] Stop Backend → Status shows "Red" within 5 minutes
- [ ] Admin receives email notification
- [ ] Grade event → stored in `mb_zoho_event_log` with status='failed'
- [ ] Start Backend → Status shows "Green"
- [ ] Retry task runs → Failed event retried successfully

**Effort:** 2-3 days  
**Developer:** Mid-level (monitoring + notifications)

---

## PRIORITY 2️⃣: CRITICAL FOR STUDENT EXPERIENCE (Week 2-3)

### 🟡 P2.1: Student Dashboard Data Implementation
**Gap:** Dashboard UI exists but shows no data

**Why This Priority:**
- Students need visibility into their progress
- Reduces support tickets ("Where are my grades?")
- Improves student engagement
- **BUT:** Not blocking core teaching operations

**Business Impact:**
- **Risk Level:** MEDIUM - Affects student experience
- **Affects:** All students
- **Workaround:** Students can view grades in gradebook (Moodle native)

**User Story:**
> As a BTEC student, when I click "My Dashboard", I want to see my enrolled programs, classes, grades, and payment status in one place, so I can track my progress without contacting admin.

**Decision Required:** Cache locally OR real-time API?

#### **Option A: Local Cache (Recommended)**
**Pros:**
- Fast response time (local DB query)
- Works if Backend temporarily down
- Matches legacy behavior

**Cons:**
- Data can be stale (up to cache TTL)
- Need sync task to refresh cache
- Additional DB tables

**Implementation:**
```sql
-- Add cache tables
CREATE TABLE mb_zoho_student_cache (
    userid INT,
    zoho_id VARCHAR(20),
    data LONGTEXT, -- JSON
    last_sync TIMESTAMP
);
```

**Sync frequency:** Every 1 hour via scheduled task

#### **Option B: Real-Time API (Not Recommended for MVP)**
**Pros:**
- Always fresh data
- No cache maintenance

**Cons:**
- Slow (depends on Backend + Zoho response time)
- Backend becomes critical for dashboard
- Higher server load

**Recommendation:** Start with **Option A (Local Cache)** for MVP, consider Option B later if needed

**Implementation Steps:**
1. Create cache tables (student_cache, grades_cache, etc.)
2. Create scheduled task: `sync_dashboard_cache.php` (runs hourly)
3. Task calls Backend: `GET /api/v1/dashboard/users?moodle_ids=1,2,3...`
4. Store JSON response in cache table
5. Update `ui/ajax/get_student_data.php`:
   - Query cache table
   - Format as HTML
   - Add cache timestamp footer

**Acceptance Test:**
- [ ] Student opens dashboard → sees data within 2 seconds
- [ ] Data matches Zoho (within 1-hour freshness)
- [ ] If cache miss → shows "Syncing..." message
- [ ] Cache invalidated when student's Zoho data changes

**Effort:** 4-5 days  
**Developer:** Mid-level (API integration + caching)

---

### 🟡 P2.2: Grader Role Detection (Teacher vs IV)
**Gap:** Can't distinguish between Teacher grades and Internal Verifier grades

**Why This Priority:**
- BTEC requires separate tracking of teacher assessment and IV verification
- IVs need to sign off on grades
- Audit trail must show who graded and who verified

**Business Impact:**
- **Risk Level:** MEDIUM - Affects IV workflow
- **Affects:** Teachers, Internal Verifiers
- **Workaround:** Manual tracking in Zoho

**User Story:**
> As an Internal Verifier, when I grade a student's assignment, I need my name recorded in the IV field in Zoho (not the Teacher field), so that external auditors can see the verification chain.

**Implementation Steps:**
1. Update `data_extractor.php::extract_grade_data()`:
   ```php
   $grader_userid = $event->relateduserid; // Get grader from event
   $grader_role = self::get_grader_role($grader_userid, $courseid);
   
   $grade_data['grader_user_id'] = $grader_userid;
   $grade_data['grader_fullname'] = fullname($grader_user);
   $grade_data['grader_role'] = $grader_role; // 'teacher' or 'iv'
   ```

2. Create helper method:
   ```php
   private function get_grader_role($userid, $courseid) {
       $context = context_course::instance($courseid);
       $roles = get_user_roles($context, $userid);
       
       foreach ($roles as $role) {
           if ($role->shortname === 'internalverifier') return 'iv';
           if ($role->shortname === 'editingteacher') return 'teacher';
       }
       return 'other';
   }
   ```

3. Backend maps:
   - If `grader_role = 'teacher'` → populate Zoho `Grader_Name`
   - If `grader_role = 'iv'` → populate Zoho `IV_Name`

**Acceptance Test:**
- [ ] Teacher grades assignment → Zoho `Grader_Name` = "John Smith"
- [ ] IV re-grades same assignment → Zoho `IV_Name` = "Jane Doe"
- [ ] Both fields preserved (not overwritten)

**Effort:** 1-2 days  
**Developer:** Junior-level (role checking)

---

### 🟡 P2.3: Ensure Backend Updates Zoho Moodle ID Fields
**Gap:** When user created, Zoho doesn't get `Student_Moodle_ID` or `Teacher_Moodle_ID`

**Why This Priority:**
- Required for linking Zoho records to Moodle users
- Needed for enrollment sync (both directions)
- Needed for dashboard lookups

**Business Impact:**
- **Risk Level:** MEDIUM - Affects data linking
- **Affects:** All user sync operations
- **Workaround:** Manual update in Zoho (not scalable)

**User Story:**
> As a system, when a new Moodle user is created, I need their Zoho record updated with the Moodle user ID, so that future sync operations can link the records correctly.

**Implementation:** This is **Backend work** (not plugin), but plugin must send correct data

**Plugin Requirements:**
- ✅ Already sending `userid` and `role` in user_created webhook
- ✅ Backend must implement the search and update logic

**Backend Requirements Document:**
```
Endpoint: POST /api/v1/webhooks (event_type: user_created)
Expected Backend Behavior:
1. Extract: username, userid, role from event_data
2. Search Zoho:
   - If role = 'student': Search BTEC_Students by Academic_Email = username
   - If role = 'teacher': Search BTEC_Teachers by Academic_Email = username
3. If found:
   - UPDATE record: Student_Moodle_ID = userid (or Teacher_Moodle_ID)
4. Return: { success: true, zoho_id: "xxx" }
```

**Acceptance Test:**
- [ ] Create user in Moodle: username="john.doe@test.com", userid=123
- [ ] Webhook sent to Backend
- [ ] Backend searches Zoho BTEC_Students by Academic_Email
- [ ] Backend updates: Student_Moodle_ID = "123"
- [ ] Verify in Zoho: Student record has Moodle ID

**Effort:** 2-3 days (Backend developer)  
**Plugin Work:** 0 days (already implemented)

---

## PRIORITY 3️⃣: OPERATIONAL IMPROVEMENTS (Week 3-4)

### 🟢 P3.1: Zoho → Moodle Enrollment Sync (Reverse Direction)
**Gap:** Students enrolled in Zoho don't auto-enroll in Moodle

**Why This Priority:**
- Important for admin workflow (enroll in Zoho, auto-sync to Moodle)
- **BUT:** Most enrollment happens in Moodle first (teacher enrolls student)
- Can be done manually if needed

**Business Impact:**
- **Risk Level:** LOW-MEDIUM - Affects admin workflow efficiency
- **Affects:** Administrators
- **Workaround:** Manual enrollment in Moodle

**User Story:**
> As an administrator, when I enroll a student in a class in Zoho CRM, I want them automatically enrolled in the corresponding Moodle course, so I don't have to do double data entry.

**Decision Required:** Is this workflow common in your institution?
- If **YES** (Zoho is source of truth) → Priority 3
- If **NO** (Moodle is source of truth) → Defer to Phase 2

**Implementation Steps:**
1. Create scheduled task: `pull_zoho_enrollments.php` (runs every 15 min)
2. Call Backend: `GET /api/v1/zoho/enrollments?modified_since={last_sync}`
3. For each enrollment:
   - Map `Enrolled_Students.Student_Moodle_ID` → Moodle user
   - Map `Classes.Moodle_Class_ID` → Moodle course
   - Check if already enrolled (idempotency)
   - Call `enrol_user($userid, $courseid, $studentroleid)`
4. Log success/failure
5. Store last sync timestamp

**Acceptance Test:**
- [ ] Enroll student in Zoho: Link student record + class record
- [ ] Wait 15 minutes (or trigger task manually)
- [ ] Verify: Student enrolled in Moodle course
- [ ] Re-run task → No duplicate enrollment

**Effort:** 3-4 days  
**Developer:** Mid-level (Moodle enrollment API)

---

### 🟢 P3.2: Workflow State Sync (Assignment Marking Status)
**Gap:** Assignment workflow state not synced to Zoho

**Why This Priority:**
- Useful for tracking marking progress
- **BUT:** Not required for BTEC compliance
- Nice-to-have for teacher workflow visibility

**Business Impact:**
- **Risk Level:** LOW - Quality of life improvement
- **Affects:** Teachers, IVs
- **Workaround:** Check status in Moodle

**Defer to:** Phase 2 (after core features working)

---

## PRIORITY 4️⃣: DEFER TO PHASE 2 (Month 2+)

### ⚪ P4.1: Finance Management UI
**Gap:** No finance management page in new plugin

**Decision Required:** Where is finance data managed?
- If **Zoho is master** → Not needed in Moodle
- If **Moodle also used** → Add to Phase 2

**Recommendation:** Check with finance team
- Most likely: Finance managed in Zoho only
- Plugin just displays read-only data on student dashboard

**If Needed:**
- Add to Phase 2 after core teaching features stable
- Effort: 5-7 days

---

### ⚪ P4.2: SharePoint/Teams Recordings Integration
**Gap:** No Teams recording sync

**Decision Required:** Is this feature actively used?
- Check legacy logs: How many recordings synced in last 3 months?
- If **active** → Create separate plugin in Phase 2
- If **rarely used** → Deprecate

**Recommendation:**
1. Ask users if they need this
2. If yes: Create separate `local_mb_teams_recordings` plugin
3. If no: Document as deprecated feature

**Effort:** 7-10 days (if rebuilt)

---

## 📅 Recommended Implementation Timeline

### **Week 1: BTEC Compliance Foundation**
**Goal:** Ensure grades meet BTEC accreditation standards

| Day | Task | Developer | Output |
|-----|------|-----------|--------|
| 1-2 | P1.1: Learning Outcomes - Plugin Side | Mid-level | Rubric extraction working |
| 2-3 | P1.1: Learning Outcomes - Backend Side | Backend dev | Zoho subform populated |
| 4 | P1.2: BTEC Grade Conversion | Junior | Pass/Merit/Distinction sent |
| 5 | Testing & Bug Fixes | Both | Test on staging with real BTEC course |

**Deliverable:** Grades synced with full BTEC assessment breakdown

---

### **Week 2: System Reliability & User Experience**
**Goal:** Ensure system won't lose data and students can see progress

| Day | Task | Developer | Output |
|-----|------|-----------|--------|
| 1-2 | P1.3: Backend Health Monitoring | Mid-level | Admin dashboard shows status |
| 3-4 | P2.1: Student Dashboard - Cache Setup | Mid-level | Cache tables + sync task |
| 4-5 | P2.1: Student Dashboard - UI Implementation | Mid-level | Students see their data |

**Deliverable:** Reliable sync + working student dashboard

---

### **Week 3: IV Workflow & Data Linking**
**Goal:** Support Internal Verifier workflow and complete data integration

| Day | Task | Developer | Output |
|-----|------|-----------|--------|
| 1 | P2.2: Grader Role Detection | Junior | Teacher vs IV grades tracked |
| 2-3 | P2.3: Coordinate with Backend Team | Backend dev | Zoho ID updates working |
| 4-5 | Testing: End-to-End Scenarios | QA/Both | All P1+P2 items verified |

**Deliverable:** Complete BTEC workflow functional

---

### **Week 4: Testing & Deployment Prep**
**Goal:** Validate everything works and prepare for production

| Day | Task | Team | Output |
|-----|------|------|--------|
| 1-2 | Comprehensive Testing | QA | Test all 15 scenarios |
| 3 | Parallel Run Setup | DevOps | New plugin running alongside legacy |
| 4 | Data Validation Scripts | Developer | Compare Zoho records from both plugins |
| 5 | Documentation & Training | All | User guides, admin guides |

**Deliverable:** Ready for parallel production run

---

### **Week 5-6: Parallel Run & Validation**
**Goal:** Prove new plugin matches legacy behavior

| Week | Activity | Success Criteria |
|------|----------|------------------|
| 5 | Both plugins enabled, compare data | 100% match on grade records |
| 6 | Monitor for issues, gradual rollout | No critical issues reported |

**Go/No-Go Decision:** End of Week 6

---

### **Month 2+: Phase 2 Features**
- P3.1: Reverse enrollment sync (if needed)
- P4.1: Finance management (if needed)
- P4.2: SharePoint integration (if needed)
- Performance optimizations
- Advanced reporting

---

## 🎯 Success Metrics

### Week 1-2 Metrics:
- [ ] 100% of grades include learning outcomes breakdown
- [ ] 100% of grades show correct BTEC classification
- [ ] Backend uptime monitored, alerts working
- [ ] 0 grade sync errors in logs

### Week 3-4 Metrics:
- [ ] Student dashboard loads in < 2 seconds
- [ ] Dashboard data accuracy: 100% match with Zoho
- [ ] IV grades correctly attributed
- [ ] New users have Zoho Moodle ID within 5 minutes

### Week 5-6 Metrics (Parallel Run):
- [ ] Data comparison: 100% match between legacy and new
- [ ] No critical bugs reported
- [ ] Teacher feedback: "New system works as well as old"
- [ ] Support tickets: No increase

---

## 💰 Cost-Benefit Analysis

### Investment:
- **Development:** 3-4 weeks (1-2 developers)
- **Testing:** 1 week (QA + developers)
- **Parallel Run:** 2 weeks (monitoring only)
- **Total:** ~6-7 weeks to production

### Benefits:
1. **Compliance:** BTEC accreditation maintained (priceless)
2. **Reliability:** Retry logic, health monitoring (reduce support calls)
3. **Scalability:** Backend API can handle growth
4. **Maintainability:** Cleaner code = faster future changes
5. **Student Experience:** Dashboard improves student engagement

### Risk if Delayed:
- **High Risk:** P1.1 (Learning Outcomes) - could affect accreditation
- **Medium Risk:** P1.2 (BTEC Grades) - reporting issues
- **Low Risk:** Everything else - workarounds available

---

## 🚦 Go/No-Go Decision Criteria

### ✅ GO TO PRODUCTION if:
- All P1 items (1.1, 1.2, 1.3) completed and tested
- Parallel run shows 100% data match
- No critical bugs in last 3 days
- Rollback plan documented and tested
- Users trained on new dashboard

### 🛑 NO-GO if:
- Learning outcomes not working (BTEC risk)
- Backend health monitoring not functional (data loss risk)
- More than 3 critical bugs in last 3 days
- Parallel run shows data discrepancies
- Rollback not tested

---

## 📋 Action Items for Tomorrow

### For Product Owner:
1. [ ] Review this prioritization with stakeholders
2. [ ] Confirm: Do we need Zoho → Moodle enrollment sync? (P3.1)
3. [ ] Confirm: Finance management needed in Moodle? (P4.1)
4. [ ] Confirm: SharePoint integration still used? (P4.2)
5. [ ] Approve Week 1-2 budget and timeline

### For Technical Lead:
1. [ ] Assign developers to P1.1, P1.2, P1.3
2. [ ] Coordinate with Backend team on learning outcomes format
3. [ ] Set up staging environment for testing
4. [ ] Create test BTEC course with rubric
5. [ ] Schedule daily standups for next 2 weeks

### For QA Lead:
1. [ ] Prepare test scenarios for BTEC grading flow
2. [ ] Create test data: students, courses, enrollments
3. [ ] Set up Zoho sandbox for testing
4. [ ] Document expected vs actual in test cases

---

## 🤔 Questions to Answer This Week

1. **BTEC Rubric Mapping:** How do we map Moodle rubric criteria to BTEC criteria (P1, P2, M1, etc.)?
   - Option A: By criterion name (must contain "P1", "M1", etc.)
   - Option B: By criterion position (first 10 = P, next 9 = M, etc.)
   - Option C: By custom field in rubric definition

2. **Dashboard Cache Frequency:** How often to refresh student dashboard cache?
   - Option A: Every 1 hour (recommended)
   - Option B: Every 4 hours (less server load)
   - Option C: On-demand only (button to refresh)

3. **Backend Health Threshold:** When to alert admins?
   - Option A: Immediately on failure (may cause alert fatigue)
   - Option B: After 3 consecutive failures (~15 min)
   - Option C: After 30 minutes of downtime

4. **Parallel Run Duration:** How long to run both plugins?
   - Option A: 1 week (minimum)
   - Option B: 2 weeks (recommended)
   - Option C: 1 month (very safe)

**Recommendation:** Schedule 1-hour planning meeting to answer these questions

---

## 📞 Escalation Path

| Severity | Issue Type | Escalate To | Response Time |
|----------|-----------|-------------|---------------|
| P0 | Data loss, accreditation risk | CTO + Product Owner | Immediate |
| P1 | Feature not working, workaround available | Technical Lead | 4 hours |
| P2 | Bug, doesn't block work | Developer | 1 day |
| P3 | Enhancement request | Product backlog | Next sprint |

---

**Ready to start?** Begin with Week 1 tasks, focus on P1.1 (Learning Outcomes) first - it's the highest risk item for your BTEC accreditation.
