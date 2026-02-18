# Zoho API Field Names Reference

This document is generated from `zoho_api_names.json` which is the **single source of truth** for all Zoho CRM field names.

**Generated:** Auto-updated from Zoho API
**Tool:** `python backend/tools/export_zoho_api_names.py`

---

## ✅ VALIDATED CORRECTIONS APPLIED

### 1. BTEC_Students Module

| ❌ OLD (Wrong) | ✅ NEW (Correct) | Notes |
|---------------|-----------------|-------|
| `Student_ID` | `Name` | Name field stores Student ID |
| `Phone` | `Phone_Number` | Updated in all sync services |
| `Photo` | `$Photo_id` | System field with $ prefix |
| `Profile_Image` | `$Photo_id` | Does not exist, use $Photo_id |
| `Department` | `Branch_ID` | Department doesn't exist |

**Verified fields:**
- ✓ `Student_Moodle_ID` - Student Moodle ID
- ✓ `Synced_to_Moodle` - Synced to Moodle
- ✓ `First_Name` - First Name
- ✓ `Last_Name` - Last Name
- ✓ `Display_Name` - Display Name
- ✓ `Academic_Email` - Academic Email
- ✓ `Status` - Status
- ✓ `Mobile` - Mobile (alternative to Phone_Number)
- ✓ `Phone_Number` - Phone Number
- ✓ `Emergency_Phone_Number` - Emergency Phone Number

---

### 2. BTEC_Registrations Module

| Field Name | Label | Status |
|------------|-------|--------|
| `Program` | Program | ✓ Verified |
| `Study_Mode` | Study Mode | ✓ Verified |
| `Student_Status` | Student Status | ✓ Verified |
| `Registration_Date` | Registration Date | ✓ Verified |
| `Program_Price` | Program Price | ✓ Verified |
| `Remaining_Amount` | Remaining Amount | ✓ Verified |
| `Payment_Schedule` | Payment Schedule | ✓ Verified (subform) |

---

### 3. BTEC_Payments Module

| ❌ OLD (Wrong) | ✅ NEW (Correct) | Notes |
|---------------|-----------------|-------|
| `Amount` | `Payment_Amount` | Updated in dashboard API |
| `Reference_Number` | ❌ Does not exist | Removed from code |
| `Payment_Status` | ❌ Does not exist | Removed from code |

**Verified fields:**
- ✓ `Student_ID` - Student ID (lookup to BTEC_Students)
- ✓ `Registration_ID` - Registration ID (lookup to BTEC_Registrations)
- ✓ `Payment_Date` - Payment Date
- ✓ `Payment_Amount` - Payment Amount
- ✓ `Payment_Method` - Payment Method
- ✓ `Payment_Type` - Payment Type
- ✓ `Created_Date` - Created Date
- ✓ `Updated_Date` - Updated Date
- ✓ `Last_Sync_Date` - Last Sync Date (To Moodle)
- ✓ `SRM_Original_Amount` - Original Amount

---

### 4. BTEC_Enrollments Module

| ❌ OLD (Wrong) | ✅ NEW (Correct) | Notes |
|---------------|-----------------|-------|
| `Class` | `Classes` | Updated in dashboard API |
| `Program` | `Enrolled_Program` | Updated in dashboard API |
| `Unit` | ❌ Does not exist | Removed from response |
| `Class_Status` | ❌ Does not exist | Removed from response |
| `Moodle_Class_ID` | `Moodle_Course_ID` | Updated in dashboard API |

**Verified fields:**
- ✓ `Classes` - Class (lookup field)
- ✓ `Class_Name` - Class Name
- ✓ `Class_Teacher` - Class Teacher
- ✓ `Enrolled_Program` - Enrolled Program
- ✓ `Enrolled_Students` - Student (lookup)
- ✓ `Student_Name` - Student Name
- ✓ `Start_Date` - Start Date
- ✓ `End_Date` - End Date
- ✓ `Moodle_Course_ID` - Moodle Course ID
- ✓ `Synced_to_Moodle` - Synced to Moodle

---

### 5. BTEC_Student_Requests Module

| ❌ OLD (Wrong) | ✅ NEW (Correct) | Notes |
|---------------|-----------------|-------|
| `Details` | `Reason` | Updated in GET/POST endpoints |
| `Attachment` | `Payment_Receipt` | Updated for payment uploads |
| `Processed_By` | ❌ Does not exist | Removed from response |
| `Response_Notes` | ❌ Does not exist | Removed from response |

**Verified fields:**
- ✓ `Student` - Student (lookup to BTEC_Students)
- ✓ `Moodle_User_ID` - Moodle User ID
- ✓ `Request_Type` - Request Type
- ✓ `Reason` - Reason (was Details)
- ✓ `Status` - Status
- ✓ `Request_Date` - Request Date
- ✓ `Payment_Receipt` - Payment Receipt (was Attachment)
- ✓ `Requested_Classes` - Requested Classes
- ✓ `Academic_Email` - Academic Email
- ✓ `Fees_Amount` - Fees Amount
- ✓ `Change_Information` - Change Information
- ✓ `Created_Time` - Created Time

---

### 6. BTEC_Grades Module

**Verified fields:**
- ✓ `Student` - Student (lookup)
- ✓ `Student_Name` - Student Name
- ✓ `Moodle_Grade_ID` - Moodle Grade ID
- ✓ `Moodle_Grade_Composite_Key` - Moodle Grade Composite_Key
- ✓ `Class` - Class ID (lookup)
- ✓ `Class_Name` - Class Name
- ✓ `BTEC_Grade_Name` - BTEC Grade Name
- ✓ `Grade_Status` - Grade Status
- ✓ `Grade` - Grade
- ✓ `Attempt_Date` - Attempt Date

---

### 7. BTEC_Classes Module

**Verified fields:**
- ✓ `Name` - Zoho ID
- ✓ `Class_Name` - Class Name
- ✓ `Class_Short_Name` - Class Short Name
- ✓ `Moodle_Class_ID` - Moodle Class ID
- ✓ `Class_Status` - Class Status
- ✓ `BTEC_Program` - BTEC Program
- ✓ `Enrolled_Students` - Enrolled Students
- ✓ `Start_Date` - Start Date
- ✓ `End_Date` - End Date
- ✓ `Classroom` - Classroom
- ✓ `First_Submission_Grade` - Submission Assessment
- ✓ `Resubmission_Grade` - Resubmission Assessment

---

## 📝 FILES UPDATED

### Backend API Endpoints
- ✅ `backend/app/api/v1/endpoints/student_dashboard.py`
  - Profile endpoint: Fixed Student_ID → Name, Phone → Phone_Number, Photo → $Photo_id
  - Finance endpoint: Fixed Amount → Payment_Amount, removed Reference_Number and Payment_Status
  - Classes endpoint: Fixed Class → Classes, Program → Enrolled_Program, Moodle_Class_ID → Moodle_Course_ID, added Start_Date/End_Date
  - Requests GET endpoint: Fixed Details → Reason, Attachment → Payment_Receipt, removed Processed_By and Response_Notes
  - Requests POST endpoint: Fixed Details → Reason, Attachment → Payment_Receipt, added Moodle_User_ID

### Sync Services
- ✅ `backend/app/ingress/zoho/parser.py`
  - Fixed Phone → Phone_Number

- ✅ `backend/app/ingress/zoho/btec_students_parser.py`
  - Fixed Profile_Image → $Photo_id
  - Fixed Department → Branch_ID

- ✅ `backend/app/services/event_handler_service.py`
  - Fixed Phone → Phone_Number (2 occurrences)

---

## 🔄 HOW TO KEEP THIS UPDATED

```bash
# 1. Export latest field names from Zoho
cd backend
python tools/export_zoho_api_names.py

# 2. Validate your code against the export
python tools/validate_field_names.py

# 3. Update this reference document if needed
```

---

## 🚨 IMPORTANT RULES

1. **ALWAYS** use `zoho_api_names.json` as the source of truth
2. **NEVER** guess field names - check the JSON file first
3. **Run export tool** after any Zoho schema changes
4. **Validate** code before committing using `validate_field_names.py`
5. **Update this document** when making corrections

---

## 🔍 QUICK SEARCH GUIDE

To find correct field names:
```bash
# Search all modules for a keyword
python -c "import json; d=json.load(open('backend/zoho_api_names.json')); [print(f'{m}: {f[\"api_name\"]}') for m in d['modules'] for f in d['modules'][m] if 'KEYWORD' in f['api_name'].lower()]"

# Or use the validation tool
python backend/tools/validate_field_names.py
```

---

**Last Validated:** February 12, 2026  
**Validation Tool:** `backend/tools/validate_field_names.py`  
**Source:** `backend/zoho_api_names.json` (auto-generated from Zoho CRM API)
