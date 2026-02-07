# Quick Reference: Legacy vs New Plugin - Critical Gaps

**Document:** Quick reference for key differences  
**Full Report:** See [LEGACY_VS_NEW_PLUGIN_ANALYSIS.md](LEGACY_VS_NEW_PLUGIN_ANALYSIS.md)

---

## 🚨 CRITICAL GAPS (P0) - Must Fix Before Production

| # | What's Missing | Impact | Where to Fix |
|---|---------------|--------|--------------|
| 1 | **Zoho → Moodle enrollment sync** | Students enrolled in Zoho won't appear in Moodle | Create `classes/task/pull_zoho_enrollments.php` |
| 2 | **Learning Outcomes in grades** | BTEC rubric criteria (P1-P10, M1-M9, D1-D6) not sent | Update `classes/data_extractor.php::extract_grade_data()` |
| 3 | **Zoho Moodle ID update** | Zoho records won't link to Moodle users | Backend must search by Academic_Email and update ID |
| 4 | **Backend dependency** | Single point of failure | Add health monitoring + queue fallback |

---

## ⚠️ HIGH PRIORITY GAPS (P1) - Should Fix Soon

| # | What's Missing | Impact | Where to Fix |
|---|---------------|--------|--------------|
| 5 | **BTEC grade conversion** | Numeric grades instead of Pass/Merit/Distinction | Add conversion logic in `data_extractor.php` |
| 6 | **Student dashboard data** | Dashboard UI exists but shows no data | Implement `ui/ajax/get_student_data.php` |
| 7 | **Grader role detection** | Can't distinguish Teacher vs IV grades | Add role check in grade observer |
| 8 | **SharePoint integration** | Teams recordings won't sync | Create separate plugin if needed |
| 9 | **Finance management** | Can't view/edit student finances | Create `ui/admin/finance_management.php` |
| 10 | **Event compatibility** | Different grade event triggers | Test `user_graded` vs `submission_graded` |

---

## ✅ What's BETTER in New Plugin (Keep These!)

1. Backend API integration (cleaner architecture)
2. UUID-based idempotency (no duplicates)
3. Retry logic with backoff (more reliable)
4. Database event logging (queryable)
5. Admin dashboard with stats
6. Scheduled Moodle tasks
7. Better configuration UI
8. User role detection

---

## 📋 Quick Action Checklist

### Before Testing:
- [ ] Ensure Backend is running and healthy
- [ ] Backend implements Zoho ID update logic
- [ ] Backend handles learning outcomes subform
- [ ] Backend converts grades to BTEC levels

### Before Production:
- [ ] Fix all P0 items (4 items)
- [ ] Fix all P1 items (6 items)
- [ ] Test enrollment sync both directions
- [ ] Test grade sync with rubric
- [ ] Test dashboard with real student
- [ ] Monitor Backend health for 1 week
- [ ] Parallel run with legacy (1 week)
- [ ] Data validation: compare Zoho records

### Deployment Strategy:
1. **Phase 1:** Install new plugin (parallel with legacy)
2. **Phase 2:** Enable only user sync (low risk)
3. **Phase 3:** Enable enrollment sync (test thoroughly)
4. **Phase 4:** Enable grade sync (critical - test with BTEC rubrics)
5. **Phase 5:** Enable dashboard for pilot users
6. **Phase 6:** Disable legacy after 2 weeks validation
7. **Phase 7:** Uninstall legacy plugin

---

## 📊 Compatibility Score Card

| Feature Category | Compatibility | Status |
|-----------------|---------------|---------|
| User Sync | 70% | Partial - needs Zoho ID update |
| Grade Sync (Basic) | 60% | Partial - needs BTEC conversion |
| Grade Sync (Detailed) | 0% | ❌ Missing learning outcomes |
| Enrollment (M→Z) | 80% | Good - event-driven |
| Enrollment (Z→M) | 0% | ❌ Not implemented |
| Student Dashboard | 30% | UI only, no data |
| Finance Management | 0% | ❌ Not implemented |
| SharePoint | 0% | ❌ Not implemented |
| Architecture | 90% | ✅ Much better |
| **Overall** | **55%** | **Needs work** |

---

## 🎯 Development Effort Estimate

| Priority | Items | Effort | Timeline |
|----------|-------|--------|----------|
| P0 (Critical) | 4 items | 5-7 days | Week 1 |
| P1 (High) | 6 items | 7-10 days | Week 2-3 |
| P2 (Nice-to-have) | 6 items | 5-7 days | Week 4 |
| Testing | Full suite | 3-5 days | Week 4 |
| **Total** | **16 items** | **20-29 days** | **1 month** |

---

## 📞 Decision Points

### Decision 1: Dashboard Data Strategy
**Question:** Cache locally (fast but stale) or real-time API (fresh but slow)?  
**Recommendation:** Hybrid - cache with 1-hour TTL

### Decision 2: SharePoint Integration
**Question:** Rebuild in new plugin or deprecate?  
**Recommendation:** If needed, create separate `local_mb_teams_recordings` plugin

### Decision 3: Finance Management
**Question:** Manage in Moodle or Zoho only?  
**Recommendation:** Check with users - if rarely used, skip it

### Decision 4: Migration Path
**Question:** Big bang or gradual rollout?  
**Recommendation:** Gradual - parallel run for 2 weeks

---

## 🔗 Key Files to Review

### Legacy Plugin (Read-Only Reference):
- `mb_zoho_sync (read Only)/classes/observers.php` - All event handlers
- `mb_zoho_sync (read Only)/sync_enrollments.php` - Enrollment sync logic
- `mb_zoho_sync (read Only)/ajax/get_student_data.php` - Dashboard data

### New Plugin (Active Development):
- `moodle_plugin/classes/observer.php` - Event handlers
- `moodle_plugin/classes/data_extractor.php` - Data extraction
- `moodle_plugin/classes/webhook_sender.php` - HTTP client
- `moodle_plugin/ui/dashboard/student.php` - Dashboard UI

### Backend (Must Review):
- Check if Backend implements Zoho ID updates
- Check if Backend handles learning outcomes
- Check if Backend converts grades to BTEC levels

---

## ❗ Don't Deploy to Production Until:

1. ✅ All P0 items fixed
2. ✅ Backend tested with new payloads
3. ✅ Parallel run completed successfully
4. ✅ Data validation shows 100% match
5. ✅ Rollback plan documented
6. ✅ Users trained on new dashboard
7. ✅ Monitoring alerts configured

---

**Last Updated:** February 4, 2026  
**Status:** Analysis Complete, Implementation Pending  
**Next Step:** Review with stakeholders and prioritize work
