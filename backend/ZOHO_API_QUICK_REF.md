# Zoho API Quick Reference

> **Source**: ZOHO_API_CONTRACT.md v1.0 CLEAN

---

## 🎯 Module Names (API)

| Module | API Name |
|--------|----------|
| Programs | `Products` ⚠️ |
| Units | `BTEC` ⚠️ |
| Students | `BTEC_Students` |
| Teachers | `BTEC_Teachers` |
| Registrations | `BTEC_Registrations` |
| Classes | `BTEC_Classes` |
| Enrollments | `BTEC_Enrollments` |
| Payments | `BTEC_Payments` |
| Grades | `BTEC_Grades` |

⚠️ **Common Mistakes:**
- Programs = `Products` (NOT `BTEC_Programs`)
- Units = `BTEC` (NOT `BTEC_Units`)

---

## 📝 Common Fields

### BTEC_Students
```python
{
    "Name": "STU-2026-001",           # Student ID
    "Academic_Email": "student@...",
    "Student_Moodle_ID": "12345",
    "Program": "584301700000...",     # Lookup to Products
    "Synced_to_Moodle": True
}
```

### Products (Programs)
```python
{
    "Product_Name": "BTEC Level 3",
    "Product_Code": "BTEC-L3-IT",
    "Product_Active": True,
    "crmmoodle__Moodle_ID": "567"
}
```

### BTEC (Units)
```python
{
    "Name": "Unit 1 - Programming",
    "Unit_Code": "U1",
    "Unit_Number": "1",
    "Program": "584301700000...",     # Lookup to Products
    "P1_description": "Explain...",   # Grading template
    "P2_description": "Describe...",
    # ... P3-P19, M1-M9, D1-D6
}
```

### BTEC_Classes
```python
{
    "Class_Name": "Programming 101",
    "Class_Short_Name": "PROG101",
    "Class_Status": "Active",
    "Teacher": "584301700000...",     # Lookup to BTEC_Teachers
    "Unit": "584301700000...",        # Lookup to BTEC
    "Moodle_Class_ID": "789"
}
```

### BTEC_Enrollments
```python
{
    "Name": "ENR-2026-001",
    "Enrolled_Students": "584301700000...",  # Lookup to BTEC_Students
    "Classes": "584301700000...",            # Lookup to BTEC_Classes
    "Moodle_Course_ID": "456",
    "Synced_to_Moodle": True
}
```

### BTEC_Payments
```python
{
    "Name": "PAY-2026-001",
    "Student_ID": "584301700000...",      # Lookup to BTEC_Students
    "Registration_ID": "584301700000...", # Lookup to BTEC_Registrations
    "Installment_No": 1,
    "Payment_Amount": 1000.00,
    "Payment_Date": "2026-01-25",
    "Payment_Method": "Bank Transfer",
    "Synced_to_Moodle": True
}
```

### BTEC_Grades (Header + Subform)
```python
{
    # Header
    "Name": "GRD-2026-001",
    "Student": "584301700000...",              # Lookup to BTEC_Students
    "Class": "584301700000...",                # Lookup to BTEC_Classes
    "BTEC_Unit": "584301700000...",            # Lookup to BTEC
    "Grade": "Pass",                            # Pass/Merit/Distinction
    "Grade_Status": "Submitted",
    "Attempt_Date": "2026-01-25",
    "Attempt_Number": 1,
    "Feedback": "Good work...",
    "Moodle_Grade_ID": "12345",
    "Moodle_Grade_Composite_Key": "123_456",   # student_id + course_id
    
    # Subform: Learning_Outcomes_Assessm (one row per criterion)
    "Learning_Outcomes_Assessm": [
        {
            "LO_Code": "P1",
            "LO_Title": "Explain components...",
            "LO_Score": "Achieved",             # Achieved/Not Achieved
            "LO_Definition": "Full description...",
            "LO_Feedback": "Well done"
        },
        {
            "LO_Code": "M1",
            "LO_Title": "Analyze impact...",
            "LO_Score": "Not Achieved",
            "LO_Definition": "Full description...",
            "LO_Feedback": "Needs improvement"
        }
    ]
}
```

---

## 🎓 Grading Template Fields (BTEC module)

### Pass (P1-P19)
```python
P1_description, P2_description, P3_description, ..., P19_description
```

### Merit (M1-M9)
```python
M1_description, M2_description, M3_description, ..., M9_description
```

### Distinction (D1-D6)
```python
D1_description, D2_description, D3_description, ..., D6_description
```

**Usage:**
```python
# Fetch unit
unit = zoho.get_record('BTEC', unit_id)

# Get template
pass_criteria = [unit[f'P{i}_description'] for i in range(1, 20) if unit.get(f'P{i}_description')]
merit_criteria = [unit[f'M{i}_description'] for i in range(1, 10) if unit.get(f'M{i}_description')]
dist_criteria = [unit[f'D{i}_description'] for i in range(1, 7) if unit.get(f'D{i}_description')]
```

---

## 🚫 Forbidden

### ❌ SRM_* Fields (Legacy)
```python
# DON'T USE:
SRM_Payment_Method     # ❌
SRM_Student_ID         # ❌
SRM_anything           # ❌
```

### ❌ Wrong Module Names
```python
# DON'T USE:
BTEC_Programs  # ❌ Use: Products
BTEC_Units     # ❌ Use: BTEC
```

### ❌ Student Subforms (except Learning_Outcomes_Assessm)
```python
# DON'T USE:
BTEC_Students with Payment_History subform  # ❌
BTEC_Students with Grade_History subform    # ❌

# ONLY ALLOWED:
BTEC_Grades with Learning_Outcomes_Assessm  # ✅
```

---

## 🔗 API Endpoints

```
Base: https://www.zohoapis.com/crm/v2

GET    /{module}/{record_id}           # Get single record
GET    /{module}/search?criteria=...   # Search records
POST   /{module}                       # Create record
PUT    /{module}/{record_id}           # Update record
DELETE /{module}/{record_id}           # Delete record
```

**Auth Header:**
```
Authorization: Zoho-oauthtoken <access_token>
```

---

## 📋 Code Template

```python
from app.infra.zoho.client import ZohoClient

# Fetch student
student = await zoho.get_record('BTEC_Students', student_id)

# Fetch unit template
unit = await zoho.get_record('BTEC', unit_id)
template = {
    'P1': unit.get('P1_description'),
    'M1': unit.get('M1_description'),
    # ...
}

# Create grade
grade_data = {
    "Student": student_id,
    "Class": class_id,
    "BTEC_Unit": unit_id,
    "Grade": "Pass",
    "Moodle_Grade_Composite_Key": f"{student_id}_{course_id}",
    "Learning_Outcomes_Assessm": [
        {"LO_Code": "P1", "LO_Score": "Achieved", ...}
    ]
}
result = await zoho.create_record('BTEC_Grades', grade_data)
```

---

**✅ Contract Version: v1.0 CLEAN**  
**Last Updated: 2026-01-25**
