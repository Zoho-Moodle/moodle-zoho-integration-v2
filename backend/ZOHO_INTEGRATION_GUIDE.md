# 🔗 Zoho Integration Guide

> **Contract Compliance**: This guide ensures 100% compliance with `ZOHO_API_CONTRACT.md`

---

## ⚠️ CRITICAL: Contract-First Development

**ALL code MUST follow ZOHO_API_CONTRACT.md strictly.**

- ✅ Use ONLY module names defined in contract
- ✅ Use ONLY field API names defined in contract
- ❌ NO `SRM_*` fields (legacy - forbidden)
- ❌ NO invented field names
- ❌ NO Student subforms except `Learning_Outcomes_Assessm`

---

## 📋 Module Mapping (API Names)

| Business Name | Zoho API Name | Event URL |
|--------------|---------------|-----------|
| BTEC Programs | **`Products`** | `/v1/events/zoho/program` |
| BTEC Units | **`BTEC`** | `/v1/events/zoho/unit` |
| BTEC Students | `BTEC_Students` | `/v1/events/zoho/student` |
| BTEC Teachers | `BTEC_Teachers` | `/v1/events/zoho/teacher` |
| BTEC Registrations | `BTEC_Registrations` | `/v1/events/zoho/registration` |
| BTEC Classes | `BTEC_Classes` | `/v1/events/zoho/class` |
| BTEC Enrollments | `BTEC_Enrollments` | `/v1/events/zoho/enrollment` |
| BTEC Payments | `BTEC_Payments` | `/v1/events/zoho/payment` |
| BTEC Grades | `BTEC_Grades` | `/v1/events/zoho/grade` |

**⚠️ Note:**
- Programs use `Products` module (NOT `BTEC_Programs`)
- Units use `BTEC` module (NOT `BTEC_Units`)

---

## 🎓 Grading System Integration

### Template Source: BTEC Module

**API Name:** `BTEC` (Units module)

**Grading Template Fields (Normal Fields on BTEC record):**
```python
# Pass criteria (P1-P19)
P1_description, P2_description, ..., P19_description

# Merit criteria (M1-M9)
M1_description, M2_description, ..., M9_description

# Distinction criteria (D1-D6)
D1_description, D2_description, ..., D6_description
```

**Example API Call:**
```python
# Fetch unit with grading template
unit = zoho_client.get_record('BTEC', unit_id)

# Extract template
grading_template = {
    'pass': [unit[f'P{i}_description'] for i in range(1, 20) if unit.get(f'P{i}_description')],
    'merit': [unit[f'M{i}_description'] for i in range(1, 10) if unit.get(f'M{i}_description')],
    'distinction': [unit[f'D{i}_description'] for i in range(1, 7) if unit.get(f'D{i}_description')]
}
```

---

### Results Source: Moodle Gradebook

**Flow:**
1. Teacher grades submission in Moodle
2. Moodle Observer triggers event
3. Event sent to `/v1/events/moodle/grade`
4. Backend maps Moodle grade to P/M/D criteria
5. Stored in `BTEC_Grades` with subform

---

### Storage: BTEC_Grades Module

**API Name:** `BTEC_Grades`

**Header Fields:**
```python
{
    "Name": "GRD-2026-123",                     # Grade Record ID
    "Student": "5843017000000123456",           # Lookup to BTEC_Students
    "Class": "5843017000000789012",             # Lookup to BTEC_Classes
    "BTEC_Unit": "5843017000000345678",         # Lookup to BTEC (Units)
    "Grade": "Pass",                             # Pick List: Pass/Merit/Distinction
    "Grade_Status": "Submitted",                 # Pick List
    "Attempt_Date": "2026-01-25",
    "Attempt_Number": 1,
    "Feedback": "Good work on P1-P5...",
    "Moodle_Grade_ID": "12345",
    "Moodle_Grade_Composite_Key": "123456_789"  # student_id + course_id
}
```

**Subform: Learning_Outcomes_Assessm**
```python
{
    "Learning_Outcomes_Assessm": [
        {
            "LO_Code": "P1",
            "LO_Title": "Explain the components...",
            "LO_Score": "Achieved",              # Achieved/Not Achieved
            "LO_Definition": "Explain the...",    # From BTEC template
            "LO_Feedback": "Well explained"
        },
        {
            "LO_Code": "P2",
            "LO_Title": "Describe the process...",
            "LO_Score": "Achieved",
            "LO_Definition": "Describe the...",
            "LO_Feedback": "Good detail"
        },
        {
            "LO_Code": "M1",
            "LO_Title": "Analyze the impact...",
            "LO_Score": "Not Achieved",
            "LO_Definition": "Analyze the...",
            "LO_Feedback": "Needs more analysis"
        }
    ]
}
```

**Important:**
- **One row per criterion** (P1, P2, M1, etc.)
- `LO_Code` identifies the criterion (P1-P19, M1-M9, D1-D6)
- `LO_Title` and `LO_Definition` come from BTEC template
- `LO_Score` and `LO_Feedback` come from Moodle grading

---

## 🔐 API Authentication

### OAuth 2.0 Flow

```python
# app/infra/zoho/auth.py

class ZohoAuthClient:
    BASE_URL = "https://accounts.zoho.com/oauth/v2"
    
    def __init__(self, client_id: str, client_secret: str, refresh_token: str):
        self.client_id = client_id
        self.client_secret = client_secret
        self.refresh_token = refresh_token
        self.access_token = None
        self.expires_at = None
    
    async def get_access_token(self) -> str:
        """Get fresh access token."""
        if self.access_token and datetime.now() < self.expires_at:
            return self.access_token
        
        # Refresh token
        params = {
            'refresh_token': self.refresh_token,
            'client_id': self.client_id,
            'client_secret': self.client_secret,
            'grant_type': 'refresh_token'
        }
        
        response = await httpx.post(f"{self.BASE_URL}/token", params=params)
        data = response.json()
        
        self.access_token = data['access_token']
        self.expires_at = datetime.now() + timedelta(seconds=data['expires_in'] - 300)
        
        return self.access_token
```

---

## 📡 API Client Implementation

### Base Zoho Client

```python
# app/infra/zoho/client.py

from typing import Dict, List, Optional
import httpx
from app.infra.zoho.auth import ZohoAuthClient

class ZohoClient:
    """
    Zoho CRM API client following ZOHO_API_CONTRACT.md
    
    ⚠️ IMPORTANT: Use ONLY module names defined in contract:
    - Products (BTEC Programs)
    - BTEC (BTEC Units)
    - BTEC_Students, BTEC_Teachers, BTEC_Classes, etc.
    """
    
    BASE_URL = "https://www.zohoapis.com/crm/v2"
    
    def __init__(self, auth_client: ZohoAuthClient):
        self.auth = auth_client
    
    async def get_record(self, module: str, record_id: str) -> Dict:
        """
        Fetch single record by ID.
        
        Args:
            module: Zoho module API name (e.g., 'BTEC_Students', 'Products', 'BTEC')
            record_id: Zoho record ID
        
        Returns:
            Record data as dict
        
        Example:
            student = await client.get_record('BTEC_Students', '5843017000000123456')
            unit = await client.get_record('BTEC', '5843017000000789012')
            program = await client.get_record('Products', '5843017000000345678')
        """
        token = await self.auth.get_access_token()
        
        headers = {
            'Authorization': f'Zoho-oauthtoken {token}',
            'Content-Type': 'application/json'
        }
        
        url = f"{self.BASE_URL}/{module}/{record_id}"
        
        async with httpx.AsyncClient() as client:
            response = await client.get(url, headers=headers)
            response.raise_for_status()
            
            data = response.json()
            return data['data'][0]
    
    async def search_records(
        self,
        module: str,
        criteria: str,
        fields: Optional[List[str]] = None
    ) -> List[Dict]:
        """
        Search records with COQL query.
        
        Args:
            module: Zoho module API name
            criteria: Search criteria (e.g., "Academic_Email = 'student@example.com'")
            fields: List of field API names to return
        
        Example:
            students = await client.search_records(
                'BTEC_Students',
                "Academic_Email = 'john@example.com'"
            )
        """
        token = await self.auth.get_access_token()
        
        headers = {
            'Authorization': f'Zoho-oauthtoken {token}',
            'Content-Type': 'application/json'
        }
        
        params = {'criteria': criteria}
        if fields:
            params['fields'] = ','.join(fields)
        
        url = f"{self.BASE_URL}/{module}/search"
        
        async with httpx.AsyncClient() as client:
            response = await client.get(url, headers=headers, params=params)
            response.raise_for_status()
            
            data = response.json()
            return data.get('data', [])
    
    async def create_record(self, module: str, data: Dict) -> Dict:
        """
        Create new record.
        
        Args:
            module: Zoho module API name
            data: Record data with field API names
        
        Example:
            grade = await client.create_record('BTEC_Grades', {
                "Student": "5843017000000123456",
                "Class": "5843017000000789012",
                "BTEC_Unit": "5843017000000345678",
                "Grade": "Pass",
                "Moodle_Grade_Composite_Key": "123_456",
                "Learning_Outcomes_Assessm": [
                    {"LO_Code": "P1", "LO_Score": "Achieved", ...}
                ]
            })
        """
        token = await self.auth.get_access_token()
        
        headers = {
            'Authorization': f'Zoho-oauthtoken {token}',
            'Content-Type': 'application/json'
        }
        
        url = f"{self.BASE_URL}/{module}"
        
        payload = {'data': [data]}
        
        async with httpx.AsyncClient() as client:
            response = await client.post(url, headers=headers, json=payload)
            response.raise_for_status()
            
            result = response.json()
            return result['data'][0]
    
    async def update_record(self, module: str, record_id: str, data: Dict) -> Dict:
        """
        Update existing record.
        
        Args:
            module: Zoho module API name
            record_id: Zoho record ID
            data: Updated fields (field API names only)
        
        Example:
            await client.update_record('BTEC_Students', record_id, {
                "Student_Moodle_ID": "12345",
                "Synced_to_Moodle": True
            })
        """
        token = await self.auth.get_access_token()
        
        headers = {
            'Authorization': f'Zoho-oauthtoken {token}',
            'Content-Type': 'application/json'
        }
        
        url = f"{self.BASE_URL}/{module}/{record_id}"
        
        payload = {'data': [data]}
        
        async with httpx.AsyncClient() as client:
            response = await client.put(url, headers=headers, json=payload)
            response.raise_for_status()
            
            result = response.json()
            return result['data'][0]
```

---

## 🧪 Example: Grade Sync Service

```python
# app/services/grade_sync_service.py

from typing import Dict, List
from app.infra.zoho.client import ZohoClient

class GradeSyncService:
    """
    Sync grades from Moodle to Zoho BTEC_Grades module.
    Follows ZOHO_API_CONTRACT.md strictly.
    """
    
    def __init__(self, zoho_client: ZohoClient):
        self.zoho = zoho_client
    
    async def sync_grade(self, moodle_grade_data: Dict) -> Dict:
        """
        Sync single grade from Moodle to Zoho.
        
        Flow:
        1. Fetch grading template from BTEC (Units) module
        2. Map Moodle grade to P/M/D criteria
        3. Build BTEC_Grades header + Learning_Outcomes_Assessm subform
        4. Create/Update in Zoho
        """
        
        # 1. Get unit grading template
        unit_id = moodle_grade_data['zoho_unit_id']
        unit = await self.zoho.get_record('BTEC', unit_id)  # ✅ Correct module name
        
        grading_template = self._extract_template(unit)
        
        # 2. Map Moodle grade to criteria
        subform_rows = self._build_learning_outcomes(
            moodle_grade_data['criteria_grades'],
            grading_template
        )
        
        # 3. Build grade record
        composite_key = f"{moodle_grade_data['student_id']}_{moodle_grade_data['course_id']}"
        
        grade_data = {
            "Student": moodle_grade_data['zoho_student_id'],
            "Class": moodle_grade_data['zoho_class_id'],
            "BTEC_Unit": unit_id,
            "Grade": moodle_grade_data['overall_grade'],  # Pass/Merit/Distinction
            "Grade_Status": "Submitted",
            "Attempt_Date": moodle_grade_data['graded_date'],
            "Attempt_Number": 1,
            "Feedback": moodle_grade_data['feedback'],
            "Moodle_Grade_ID": str(moodle_grade_data['grade_id']),
            "Moodle_Grade_Composite_Key": composite_key,
            "Learning_Outcomes_Assessm": subform_rows  # ✅ Only allowed subform
        }
        
        # 4. Check if exists (by composite key)
        existing = await self.zoho.search_records(
            'BTEC_Grades',
            f"Moodle_Grade_Composite_Key = '{composite_key}'"
        )
        
        if existing:
            # Update
            result = await self.zoho.update_record(
                'BTEC_Grades',
                existing[0]['id'],
                grade_data
            )
        else:
            # Create
            result = await self.zoho.create_record('BTEC_Grades', grade_data)
        
        return result
    
    def _extract_template(self, unit: Dict) -> Dict[str, List[str]]:
        """Extract P/M/D descriptions from BTEC unit."""
        template = {
            'pass': [],
            'merit': [],
            'distinction': []
        }
        
        # Pass criteria (P1-P19) ✅ Field names from contract
        for i in range(1, 20):
            field = f'P{i}_description'
            if unit.get(field):
                template['pass'].append({
                    'code': f'P{i}',
                    'description': unit[field]
                })
        
        # Merit criteria (M1-M9) ✅ Field names from contract
        for i in range(1, 10):
            field = f'M{i}_description'
            if unit.get(field):
                template['merit'].append({
                    'code': f'M{i}',
                    'description': unit[field]
                })
        
        # Distinction criteria (D1-D6) ✅ Field names from contract
        for i in range(1, 7):
            field = f'D{i}_description'
            if unit.get(field):
                template['distinction'].append({
                    'code': f'D{i}',
                    'description': unit[field]
                })
        
        return template
    
    def _build_learning_outcomes(
        self,
        criteria_grades: List[Dict],
        template: Dict[str, List[str]]
    ) -> List[Dict]:
        """
        Build Learning_Outcomes_Assessm subform rows.
        One row per P/M/D criterion.
        """
        subform_rows = []
        
        for criterion in criteria_grades:
            code = criterion['code']  # e.g., "P1", "M2", "D3"
            
            # Find template definition
            level = code[0].lower()  # 'p', 'm', or 'd'
            level_map = {'p': 'pass', 'm': 'merit', 'd': 'distinction'}
            
            template_item = next(
                (t for t in template[level_map[level]] if t['code'] == code),
                None
            )
            
            if template_item:
                subform_rows.append({
                    "LO_Code": code,                          # ✅ Field from contract
                    "LO_Title": template_item['description'][:100],  # ✅ Field from contract
                    "LO_Score": criterion['achieved'],        # ✅ Field from contract (Achieved/Not Achieved)
                    "LO_Definition": template_item['description'],   # ✅ Field from contract
                    "LO_Feedback": criterion.get('feedback', '')     # ✅ Field from contract
                })
        
        return subform_rows
```

---

## ✅ Contract Compliance Checklist

Before committing code, verify:

- [ ] **Module Names**: Using exact API names from contract
  - [ ] `Products` for BTEC Programs
  - [ ] `BTEC` for BTEC Units
  - [ ] `BTEC_Students`, `BTEC_Teachers`, etc.

- [ ] **Field Names**: Using exact API names from contract
  - [ ] `Academic_Email` (NOT `Email`)
  - [ ] `Student_Moodle_ID` (NOT `Moodle_ID`)
  - [ ] `Learning_Outcomes_Assessm` (NOT `Learning_Outcomes`)

- [ ] **Grading Fields**: Only from contract
  - [ ] `P1_description` ... `P19_description`
  - [ ] `M1_description` ... `M9_description`
  - [ ] `D1_description` ... `D6_description`

- [ ] **Subforms**: Only allowed subforms
  - [ ] `Learning_Outcomes_Assessm` ONLY for grades
  - [ ] NO other Student subforms

- [ ] **Forbidden Fields**: None used
  - [ ] NO `SRM_*` fields
  - [ ] NO invented field names

- [ ] **Composite Key**: Used correctly
  - [ ] `Moodle_Grade_Composite_Key` = `student_id + course_id`

---

## 🚨 Common Mistakes to Avoid

### ❌ Wrong Module Name
```python
# BAD
units = zoho.get_records('BTEC_Units')  # ❌ WRONG!

# GOOD
units = zoho.get_records('BTEC')  # ✅ Correct per contract
```

### ❌ Wrong Field Name
```python
# BAD
student_data = {
    'Email': 'student@example.com',  # ❌ WRONG!
    'Moodle_ID': '12345'             # ❌ WRONG!
}

# GOOD
student_data = {
    'Academic_Email': 'student@example.com',  # ✅ Correct
    'Student_Moodle_ID': '12345'              # ✅ Correct
}
```

### ❌ Using Forbidden Subforms
```python
# BAD
student_data = {
    'Name': 'John Doe',
    'Payment_History': [...]  # ❌ Forbidden subform!
}

# GOOD
# Payments go in BTEC_Payments module, not Student subform
payment_data = {
    'Student_ID': student_id,  # Lookup to BTEC_Students
    'Payment_Amount': 1000
}
```

### ❌ Using SRM_* Legacy Fields
```python
# BAD
payment = {
    'SRM_Payment_Method': 'Cash'  # ❌ Legacy field - FORBIDDEN!
}

# GOOD
payment = {
    'Payment_Method': 'Cash'  # ✅ Correct field per contract
}
```

---

## 📚 Reference Documents

1. **ZOHO_API_CONTRACT.md** - Single source of truth (MUST READ)
2. **ARCHITECTURE.md** - System architecture (updated to match contract)
3. **EVENT_DRIVEN_ARCHITECTURE.md** - Event flow design

**Order of precedence:**
1. ZOHO_API_CONTRACT.md (highest)
2. This guide
3. ARCHITECTURE.md

If any conflict exists, **ZOHO_API_CONTRACT.md wins**.

---

**✅ This integration is 100% contract-compliant and ready for implementation!**
