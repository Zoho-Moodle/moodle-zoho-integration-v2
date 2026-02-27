# Cleanup Audit Report
**Date:** February 27, 2026  
**Scope:** Full project — root, backend/, moodle_plugin/, mb_zoho_sync/  
**Status:** Pre-cleanup (report only — nothing deleted yet)

---

## Summary

| Category | Count | Action |
|----------|-------|--------|
| Markdown docs (dev-phase only) | ~95 files | 🗑 Delete |
| One-time Python scripts | ~28 files | 🗑 Delete |
| Duplicate/redundant endpoints | 4 files | ⚠️ Review |
| Redundant services | 10 files | ⚠️ Review |
| Dangerous SQL/shell scripts | 12 files | 🗑 Delete |
| Old plugin (read-only ref) | 1 folder | 🗑 Delete |
| Empty folders | 1 | 🗑 Delete |
| Log/temp/test DB files | 3 | 🗑 Delete |
| Files to KEEP (production code) | ~60 files | ✅ Keep |

---

## 1. PROJECT ROOT `/`

### 🗑 DELETE — Dev-phase markdown files (analysis/planning only)

These were written during development to track analysis, planning, and migration. Not needed in production.

| File | Reason |
|------|--------|
| `ADMIN_UI_FULL_ANALYSIS_REPORT.md` | Dev analysis |
| `CODEBASE_FULL_ANALYSIS_REPORT.md` | Dev analysis |
| `CODEBASE_READINESS_REPORT.md` | Dev analysis |
| `COMPARISON_REPORT_LOCAL_VS_SERVER.md` | Dev analysis |
| `DASHBOARD_DEBUG_FIX.txt` | Dev debug note |
| `DASHBOARD_READ_WRITE_VERIFIED_MAP.md` | Dev analysis |
| `DATA_FLOW_MAP.md` | Superseded by DEPLOYMENT_GUIDE.md |
| `FINAL_VALIDATION_REPORT.md` | Dev phase report |
| `IMPLEMENTATION_PRIORITIZATION.md` | Dev planning |
| `LEGACY_VS_NEW_PLUGIN_ANALYSIS.md` | Dev analysis |
| `MAPPING_AUDIT_REPORT.md` | Dev analysis |
| `MIGRATION_PLAN.md` | Migration complete |
| `QUICK_REFERENCE_GAPS.md` | Dev notes |
| `REBUILD_BLUEPRINT.md` | Dev planning |
| `SINGLE_DATABASE_ARCHITECTURE.md` | Dev planning |
| `STUDENT_CARD_DESIGN.md` | Dev design doc |
| `STUDENT_DASHBOARD_BUILD_PLAN.md` | Dev planning |
| `STUDENT_DASHBOARD_COMPLETE_SPEC.md` | Dev spec |
| `STUDENT_UI_UPLOAD_GUIDE.md` | Dev guide, superseded |
| `SYSTEM_ARCHITECTURE_ANALYSIS.md` | Dev analysis |
| `UPLOAD_AND_UPGRADE_GUIDE.md` | Dev guide, superseded |
| `UPLOAD_JAVASCRIPT_FILE.txt` | One-time instruction |
| `WEBHOOKS_IMPLEMENTATION.md` | Dev notes |
| `WS_AUDIT_REPORT.md` | Dev analysis |

### 🗑 DELETE — Dangerous/one-time SQL and shell scripts

These scripts manipulate or destroy DB data. They were used during setup/migration only.

| File | Reason |
|------|--------|
| `check_database.ps1` | One-time DB check |
| `CHECK_DATABASE_BEFORE_UPGRADE.sql` | One-time check |
| `cleanup_database.sh` | ⚠️ Destructive — deletes DB data |
| `delete_student_dashboard.sh` | ⚠️ Destructive |
| `delete_student_dashboard_local.ps1` | ⚠️ Destructive |
| `DROP_NEW_TABLES.sql` | ⚠️ Destructive — drops tables |
| `DROP_TABLES_COMMAND.ps1` | ⚠️ Destructive |
| `RESTORE_CRITICAL_TABLES.sql` | Emergency recovery — now obsolete |
| `restore_tables.ps1` | Emergency recovery — now obsolete |
| `TRUNCATE_NEW_TABLES.sql` | ⚠️ Destructive |
| `TRUNCATE_PROGRAMS_DATA.sql` | ⚠️ Destructive |
| `verify_status.sql` | One-time check |
| `verify_upgrade.ps1` | One-time check |

### 🗑 DELETE — Leftover files

| File | Reason |
|------|--------|
| `test.db` | Test SQLite database, not production |
| `server.log` | Log file, should be gitignored |
| `STUDENT_DASHBOARD_FILES_TO_DELETE.txt` | Task list, already done |

### 🗑 DELETE — Old plugin reference (entire folder)

| Path | Reason |
|------|--------|
| `mb_zoho_sync (read Only)/` | Old v1/v2 Moodle plugin. Kept as read-only reference during migration. Migration is complete. The new plugin is in `moodle_plugin/`. |

### ✅ KEEP — Root files

| File | Reason |
|------|--------|
| `DEPLOYMENT_GUIDE.md` | ✅ New comprehensive guide |
| `upload_to_server.ps1` | Useful deployment utility |
| `.gitignore`, `.gitattributes` | VCS config |
| `.venv/`, `.venv-1/`, `.venv-2/` | Virtual envs |
| `backend/` | Main application |
| `moodle_plugin/` | Current plugin |

---

## 2. `backend/` ROOT — Python Scripts

### 🗑 DELETE — One-time fix/migration scripts

These ran once to fix DB columns, migrate data, or patch a specific student record ("omar"). No longer needed.

| File | Reason |
|------|--------|
| `fix.py` | One-time fix |
| `fix_db.py` | One-time fix |
| `fix_email_column.py` | One-time column migration |
| `fix_userid.py` | One-time migration |
| `fix_moodle_user_id.sql` | One-time SQL migration |
| `restore_email_constraint.py` | One-time migration |
| `migrate_db.py` | One-time migration, superseded by SQLAlchemy |
| `update_omar_sql.py` | Personal one-off — specific student "Omar" |
| `update_omar_student_id.py` | Personal one-off |
| `manual_sync_omar.py` | Personal one-off |
| `download_omar_photo.py` | Personal one-off |

### 🗑 DELETE — One-time DB setup scripts (superseded by SQLAlchemy `create_all`)

The app now creates tables automatically via `Base.metadata.create_all()` on startup.

| File | Reason |
|------|--------|
| `create_tables.py` | Superseded by lifespan in main.py |
| `create_extension_tables.py` | Superseded |
| `create_event_log_table.py` | Superseded |
| `setup_db.py` | Superseded |
| `seed_extension_config.py` | One-time seed |

### 🗑 DELETE — One-time sync/discovery scripts (superseded by API endpoints)

| File | Reason |
|------|--------|
| `initial_sync.py` | Superseded by `POST /api/v1/admin/full-sync` |
| `sync_students_from_zoho.py` | Superseded by sync endpoints |
| `quick_sync_students.py` | Superseded |
| `extract_zoho_fields.py` | Superseded by Setup Wizard zoho-fields endpoint |
| `check_classes.py` | Debug/discovery, done |
| `check_rules.py` | Debug/discovery, done |
| `list_moodle_categories.py` | One-time utility |
| `probe_test.py` | Dev probe |

### 🗑 DELETE — Loose test files in backend root (tests/ folder exists)

| File | Reason |
|------|--------|
| `test_auto_photo_upload.py` | Should be in tests/ or deleted |
| `test_moodle_enrollments_ingestion.py` | Should be in tests/ or deleted |
| `test_moodle_grades_ingestion.py` | Should be in tests/ or deleted |
| `test_moodle_users_ingestion.py` | Should be in tests/ or deleted |
| `test_moodle_webhooks.py` | Should be in tests/ or deleted |
| `test_update_tracking_fields.py` | Should be in tests/ or deleted |
| `load_test_data.py` | Dev only |
| `PHASE2_3_SUMMARY.py` | Misnamed — a summary doc with .py extension |

### 🗑 DELETE — Misc dev leftovers

| File | Reason |
|------|--------|
| `zoho_attachments.py` | Appears unused — attachments feature not in router |
| `zoho_rules_state.json` | One-time state snapshot |
| `_check_sync.py` | Temp diagnostic created by Copilot, not production code |
| `Moodle_Int.zip` | Zip archive leftover |
| `ngrok.zip` | Zip archive, ngrok already extracted |
| `ZET-debug.log` | Log file |
| `run_server.py` | ⚠️ **Duplicate** of `start_server.py` (different file, same purpose) |
| `migrations/` | Empty folder |
| `.pytest_cache/` | Generated, gitignored |

### ⚠️ REVIEW — Possibly useful utilities (keep or move to tools/)

| File | Decision needed |
|------|----------------|
| `zoho_api_names.json` | ✅ Keep — field reference data used by code |
| `enrollments_sample.json` | May keep as reference |
| `classes_sample.json` | May keep as reference |
| `registrations_sample.json` | May keep as reference |
| `admin_users.json` | ✅ **Keep** — admin credentials file |
| `Postman_Collection.json` | ✅ Keep — useful for API testing |

### ✅ KEEP — Production files

| File | Reason |
|------|--------|
| `start_server.py` | Entry point |
| `requirements.txt` | Dependencies |
| `requirements_complete.txt` | ⚠️ Duplicate of requirements.txt — review if identical |
| `.env` + `.env.example` | Config |
| `pytest.ini` | Test config |
| `README.md` | Main README |

---

## 3. `backend/` ROOT — Markdown Files (65 total)

65 markdown files exist in `backend/`. Only a handful are worth keeping. The rest are phase reports, debug notes, and planning docs.

### ✅ KEEP

| File | Reason |
|------|--------|
| `README.md` | Main project README |
| `00_READ_ME_FIRST.md` | Onboarding doc |
| `DEPLOYMENT_GUIDE.md` | Deployment reference |
| `API_DOCUMENTATION.md` | API reference |
| `ARCHITECTURE.md` | Architecture overview |
| `DATABASE_SETUP.md` | DB reference |
| `QUICK_START.md` or `QUICK_START_GUIDE.md` | Pick one |

### 🗑 DELETE — Everything else (58+ files)

Examples of redundant/obsolete docs:

| File | Reason |
|------|--------|
| `PHASE1_IMPLEMENTATION_COMPLETE.md` | Phase log |
| `PHASE2_3_COMPLETE.md`, `PHASE2_3_DOCUMENTATION.md`, `PHASE2_3_QUICK_START.md` | Phase logs |
| `PHASE4_COMPLETE.md`, `PHASE4_DATABASE_SETUP.md`, `PHASE4_IMPLEMENTATION.md`, `PHASE4_QUICKSTART.md` | Phase logs |
| `CLEANUP_COMPLETE.md` | Phase log |
| `CONTRACT_COMPLIANCE_COMPLETE.md` | Phase log |
| `FINAL_ARCHITECTURE_SIGN_OFF.md` | Phase log |
| `FINAL_COMPLETION_REPORT.md` | Phase log |
| `FIXES_REPORT.md` | Phase log |
| `DATABASE_ERROR_FIX.md`, `DATABASE_ERROR_SOLUTION.txt`, `DATABASE_FIX.txt`, `DATABASE_FIX_SUMMARY.md` | Old debugging notes |
| `DATABASE_MIGRATION.sql` | One-time migration SQL |
| `db_complete_schema.sql`, `db_phase4_create.sql` | Superseded by SQLAlchemy models |
| `INSTANT_FIX.txt`, `FINAL_SUMMARY.txt`, `STEP_BY_STEP_NOW.md`, `START_HERE_NOW.md` | Dev notes |
| `DASHBOARD_ARCHITECTURE_ANALYSIS.md` | Dev analysis |
| `DATA_MAPPING_STRATEGY.md` | Dev planning |
| `BACKEND_SYNC_MAPPING.md` | Dev planning |
| `AUTO_PHOTO_UPLOAD_GUIDE.md` | Photo feature superseded |
| `COMPLETE_DISCOVERY_GUIDE.md` | Dev discovery |
| `COMPREHENSIVE_EXTRACTOR_GUIDE.md` | Dev discovery |
| `DEBUG_ENDPOINTS_GUIDE.md`, `DEBUG_USAGE_GUIDE.md` | Dev debug notes |
| `DEPLOYMENT_CHECKLIST.md` | Superseded by DEPLOYMENT_GUIDE.md |
| `EDUCATIONAL_GUIDE.md` | Dev notes |
| `EVENT_DRIVEN_ARCHITECTURE.md`, `EVENT_ROUTER_COMPLETE.md` | Phase docs |
| `EXTENSION_API_CHANGELOG.md`, `EXTENSION_API_QUICK_REF.md`, `EXTENSION_IMPLEMENTATION_SUMMARY.md` | Phase docs |
| `FILE_INVENTORY.md`, `INDEX.md` | Dev nav files |
| `FIELD_VALIDATION_REPORT_AR.md` | Dev report |
| `FINAL_MATCHING_SUMMARY.md`, `FINAL_MATCHING_GUIDE*.md` | Dev reports |
| `IMPLEMENTATION_SUMMARY.md`, `NEXT_STEPS_SUMMARY.md`, `SUCCESS_SUMMARY.md` | Phase logs |
| `MOODLE_PLUGIN_ARCHITECTURE_AR.md`, `MOODLE_PLUGIN_GUIDE.md` | Moved to moodle_plugin/ |
| `PRODUCTION_SETUP_GUIDE.md` | Superseded by DEPLOYMENT_GUIDE.md |
| `PROJECT_JOURNEY_COMPLETE.md`, `PROJECT_SUMMARY.md` | Journey logs |
| `SAMPLE_WEBHOOKS.md` | Dev reference |
| `SOFTWARE_ENGINEERING_ANALYSIS_AR.md` | Dev analysis |
| `TESTING_GUIDE_DETAILED.md`, `TESTING_NOTES.md` | Dev testing notes |
| `USAGE_EXAMPLES.md` | Superseded |
| `ZOHO_API_QUICK_REF.md`, `ZOHO_CLIENT_IMPLEMENTATION.md`, `ZOHO_DEBUG_SETUP.md` | Dev guides |
| `ZOHO_DISCOVERY_SYSTEM.md`, `ZOHO_FIELD_NAMES_REFERENCE.md`, `ZOHO_FORMAT_SPEC.md` | Dev guides |
| `ZOHO_INTEGRATION_GUIDE.md` | Dev guide |
| `COMPREHENSIVE_ZOHO_FUNCTION.zdeluge`, `COMPREHENSIVE_ZOHO_FUNCTION_SIMPLE.zdeluge` | Zoho Deluge scripts (old) |
| `ZOHO_FINAL_EXTRACTOR.zdeluge`, `ZOHO_FIXED_DISCOVERY.zdeluge` | Old Deluge scripts |
| `student_photos/` | Photo upload artifacts |
| `.vscode-extensions.txt` | Dev config |

---

## 4. `backend/app/api/v1/endpoints/` — Endpoint Files

### ⚠️ OVERLAPPING — Events vs Dashboard Sync

| File | Status | Notes |
|------|--------|-------|
| `events.py` | ⚠️ Mostly dead code | The `process_zoho_event_task` function explicitly says `# AUDIT ONLY — do NOT call event_handler`. The real sync is `webhooks_dashboard_sync.py`. However it's still registered in the router. Consider removing from router or deleting. |
| `webhooks_dashboard_sync.py` | ✅ Active | Main Zoho → Moodle DB sync path |
| `student_dashboard_webhooks.py` | ⚠️ Check if registered | Appears to duplicate `webhooks_dashboard_sync.py` — verify if this is the same file or different |

### ⚠️ DUPLICATE DEBUG — Two debug files

| File | Status | Notes |
|------|--------|-------|
| `debug.py` | ⚠️ Old | Original debug endpoint |
| `debug_enhanced.py` | ✅ Active (in router) | Updated version — `debug.py` is not in the router and can be deleted |

### ⚠️ REVIEW — Moodle ingestion endpoints

These receive data FROM Moodle INTO the backend. Verify if currently used:

| File | Notes |
|------|-------|
| `moodle_users.py` | Moodle → Backend user push |
| `moodle_enrollments.py` | Moodle → Backend enrollment push |
| `moodle_grades.py` | Moodle → Backend grade push |
| `moodle_events.py` | Moodle → Backend events |

### ⚠️ REVIEW — BTEC Units webhook

| File | Notes |
|------|-------|
| `webhooks_btec_units.py` | Separate from `webhooks_dashboard_sync.py` — verify if still needed |

### ✅ KEEP — Core endpoints

| File | Role |
|------|-----|
| `webhooks_shared.py` | Shared utilities (FIELD_MAPPINGS, transform, call_moodle_ws) |
| `webhooks_dashboard_sync.py` | Main Zoho → Moodle DB sync |
| `sync_students.py` + all other sync_*.py | Bulk sync endpoints |
| `full_sync.py` | Full sync orchestrator |
| `zoho_webhook_setup.py` | Setup Zoho webhooks |
| `submit_request.py` | Student requests |
| `create_course.py` | Course creation |
| `btec_templates.py` | BTEC template sync |
| `health.py` | Health check |
| `extension_*.py` (4 files) | Extension API for Moodle plugin |
| `webhooks.py` | Moodle → Zoho events |

---

## 5. `backend/app/services/` — Service Files

### ⚠️ DUPLICATE SERVICES — Two versions of each service

These appear to be old (Phase 1-2) vs new (Phase 3-4) implementations:

| Old (Phase 1/2) | New (Phase 3/4) | Keep |
|-----------------|------------------|------|
| `student_service.py` | `btec_students_service.py` | ✅ Keep new, check if old is still imported |
| `student_profile_service.py` | (used by `event_handler_service.py`) | ⚠️ Keep if events.py is kept |
| `enrollment_service.py` | `enrollment_sync_service.py` | ⚠️ Verify which is active |
| `grade_service.py` | `grade_sync_service.py` | ⚠️ Verify which is active |
| `payment_service.py` | `payment_sync_service.py` | ⚠️ Verify which is active |
| `class_service.py` | (in class-related sync) | ⚠️ Verify |
| `registration_service.py` | (in sync_registrations) | ⚠️ Verify |

### ⚠️ USED ONLY BY DEAD CODE

| File | Used by | Notes |
|------|---------|-------|
| `event_handler_service.py` | `events.py` (audit-only, largely dead) | If `events.py` route is removed from router, this becomes dead |
| `student_profile_service.py` | `event_handler_service.py` only | Same chain |
| `grade_sync_service.py` | `event_handler_service.py` only | Same chain |
| `enrollment_sync_service.py` | `event_handler_service.py` only | Same chain |
| `payment_sync_service.py` | `event_handler_service.py` only | Same chain |
| `zoho_notification_service.py` | ❓ Verify usage | May be dead |
| `zoho_workflow_service.py` | ❓ Verify usage | May be dead |

### ✅ KEEP — Mapper files (all active)

All `*_mapper.py` files are used by the sync endpoints for data transformation.

---

## 6. `backend/app/infra/db/models/`

### ✅ All models are used

All model files (`student.py`, `registration.py`, `class_.py`, `enrollment.py`, `grade.py`, `payment.py`, `program.py`, `unit.py`, `extension.py`, `event_log.py`) are registered and active.

---

## 7. `backend/admin/templates/`

### ✅ All templates are used

| Template | Route |
|----------|-------|
| `base.html` | Layout |
| `login.html` | `/admin/login` |
| `dashboard.html` | `/admin/dashboard` |
| `setup.html` | `/admin/setup` |
| `mappings.html` | `/admin/mappings` ✅ (new) |
| `settings.html` | `/admin/settings` |
| `users.html` | `/admin/users` |
| `logs.html` | `/admin/logs` |
| `sync_control.html` | `/admin/sync` |
| `sync_history.html` | `/admin/sync/history` |
| `data_browser.html` | `/admin/data` |
| `services.html` | `/admin/services` |

---

## 8. `moodle_plugin/` — Plugin Files

### 🗑 DELETE — Dev-phase markdown files in moodle_plugin/

| File | Reason |
|------|--------|
| `ARABIC_SUMMARY_v3.4.1.md` | Phase report |
| `BUILD_COMPLETE.md` | Phase log |
| `CHANGELOG_v3.4.1.md` | Dev changelog |
| `COMPLETION_REPORT_v3.2.0.md` | Phase log |
| `CRITICAL_FIXES_REQUIRED.md` | Dev notes |
| `CRITICAL_FIX_GRADING.md` | Dev notes |
| `DEPLOYMENT_READY_v3.4.1.md` | Phase log |
| `DETAILED_EXPLANATION.md` | Dev notes |
| `ENHANCED_MONITORING_IMPLEMENTATION.md` | Phase log |
| `FIXES_APPLIED.md` | Phase log |
| `GRADE_LOGIC_COMPLETE.md` | Phase log |
| `INDEX.md` | Dev nav |
| `MOODLE_PLUGIN_COMPLETE_ARCHITECTURE.md` | Dev reference |
| `NAMESPACE_FIX_COMPLETE.md` | Phase log |
| `NEXT_STEPS_AR.md` | Dev notes |
| `P1_FIXES_COMPLETE.md` | Phase log |
| `PRODUCTION_HARDENING_COMPLETE.md` | Phase log |
| `QUICK_SETUP_ENHANCED_MONITORING.md` | Dev guide |
| `STUDENT_DASHBOARD_UX_COMPLETE.md` | Phase log |
| `TECHNICAL_IMPLEMENTATION.md` | Dev reference |
| `TESTING_GUIDE_CRITICAL.md` | Dev testing |
| `UI_ENHANCEMENT_COMPLETE.md` | Phase log |
| `VERSION_3_2_0_RELEASE_NOTES.md` | Phase log |
| `تطوير_صفحة_الطالب.md` | Dev notes (Arabic) |
| `تقرير_1st_2nd_Attempt.md` | Dev notes (Arabic) |
| `خطة_تطبيق_Hybrid_Grading.md` | Dev notes (Arabic) |
| `دليل_اختبار_Scheduled_Task.md` | Dev notes (Arabic) |

### 🗑 DELETE — One-time/debug PHP files in moodle_plugin/

| File | Reason |
|------|--------|
| `check_webhook_file.php` | Debug |
| `fix_retrying_events.php` | One-time fix |
| `manual_observer_registration.sql` | One-time SQL |
| `manual_retry.php` | One-time utility |
| `reset_event.php` | One-time utility |
| `show_webhook_content.php` | Debug |
| `test_grading_observer.php` | Test |
| `update_webhook_sender.php` | One-time update |
| `add_action_column.sql` | One-time migration |

### ✅ KEEP — Plugin production files

| Path | Reason |
|------|--------|
| `version.php` | ✅ Plugin version |
| `lib.php` | ✅ Plugin hooks |
| `settings.php` | ✅ Plugin settings |
| `db/` | ✅ DB upgrade scripts |
| `lang/` | ✅ Language strings |
| `classes/` | ✅ PHP classes |
| `amd/` | ✅ JavaScript modules |
| `pix/` | ✅ Icons |
| `ui/` | ✅ All UI pages |
| `README.md`, `README_INSTALLATION.md` | ✅ Keep |

---

## 9. `mb_zoho_sync (read Only)/` — OLD Plugin

### 🗑 DELETE — Entire folder

This is the **old v1/v2 plugin** used before the current project. It's kept as "read only" reference. Migration is complete. The new plugin (`moodle_plugin/`) fully replaces it.

Deleting it will reduce repo size significantly (~60+ files).

---

## 10. Priority Cleanup Order

### 🔴 HIGH — Do first (risk-free, no code logic)

1. Delete all `*.md` and `*.txt` dev-phase files in `backend/` (58+ files)
2. Delete all `*.md` dev-phase files in `moodle_plugin/` (27+ files)
3. Delete all `*.md` dev-phase files in project root (24 files)
4. Delete `mb_zoho_sync (read Only)/` folder entirely
5. Delete `test.db`, `server.log`, `ZET-debug.log`, `ngrok.zip`, `Moodle_Int.zip`
6. Delete `migrations/` (empty folder)
7. Delete `_check_sync.py` (temp file)

### 🟡 MEDIUM — Scripts (safe to delete, already superseded)

8. Delete one-time fix scripts: `fix*.py`, all `*omar*.py`
9. Delete superseded DB setup: `create_tables.py`, `create_extension_tables.py`, `create_event_log_table.py`, `setup_db.py`, `seed_extension_config.py`
10. Delete superseded sync scripts: `initial_sync.py`, `quick_sync_students.py`, `sync_students_from_zoho.py`
11. Delete loose test files from backend root (move to `tests/` or delete)
12. Delete `run_server.py` (duplicate of `start_server.py`)
13. Delete `zoho_rules_state.json`, `classes_sample.json`, `enrollments_sample.json`, `registrations_sample.json` (old sample snapshots)

### 🔵 LOW — Code cleanup (requires verification)

14. Remove `events.py` from `router.py` (or keep for monitoring — verify with team)
15. Delete `debug.py` (not in router, replaced by `debug_enhanced.py`)
16. Verify and clean dead services: `event_handler_service.py` chain
17. Verify `student_dashboard_webhooks.py` vs `webhooks_dashboard_sync.py` — are they the same?
18. Check `moodle_users.py`, `moodle_enrollments.py`, `moodle_grades.py` are still needed

---

## 11. Files Count Summary

```
Current state:
  backend/ root .py files:     35   →  Keep: 5    Delete: ~28   Move: 2
  backend/ root .md files:     65   →  Keep: 7    Delete: 58
  backend/ root .json files:    8   →  Keep: 4    Delete: 4
  backend/app/endpoints/:      33   →  Keep: 26   Review: 7
  backend/app/services/:       25   →  Keep: 14   Review: 11
  moodle_plugin/ .md files:    28   →  Keep: 2    Delete: 26
  moodle_plugin/ .php (debug): 9    →  Keep: 0    Delete: 9
  project root .md/.txt files: 27   →  Keep: 1    Delete: 26
  project root .sql/.ps1:      13   →  Keep: 0    Delete: 13
  mb_zoho_sync/ (entire):      60+  →  Keep: 0    Delete all

ESTIMATED CLEANUP: ~220+ files deleted, ~60 files kept
```

---

*Report generated February 27, 2026. Review and approve before executing any deletions.*
