"""
Manual sync script for Omar's data
Run this to sync his registrations, classes, and enrollments
"""
import asyncio
import httpx
import json

# Moodle backend URL
BACKEND_URL = "http://localhost:8001/api/v1/webhooks/student-dashboard"

# Omar's data from Zoho (from Zoho CRM - actual data)
# These are REAL records with proper Zoho field names

CLASSES = [
    {
        "id": "5398830000032846301",
        "Class_Name": "25/2011 BUS I/O Accounting Principles",
        "Unit": "Accounting Principles",
        "Class_Type": "Class",
        "Program_Level": "BTEC Level 5 Business",
        "Teacher": {"name": "Dr. Samer Hamdo", "id": "123"},
        "Start_Date": "2025-10-11",
        "End_Date": "2026-01-06",
        "Status": "Active",
        "Created_Time": "2025-10-11T10:00:00+03:00",
        "Modified_Time": "2025-10-11T10:00:00+03:00"
    },
    {
        "id": "5398830000032846302",
        "Class_Name": "24/2512T I/O Network Security Design 2",
        "Unit": "Network Security Design",
        "Class_Type": "Class",
        "Program_Level": "BTEC Level 5",
        "Teacher": {"name": "Mr. Mohyeddine Farhat", "id": "456"},
        "Start_Date": "2025-09-13",
        "End_Date": "2025-12-06",
        "Status": "Active",
        "Created_Time": "2025-09-13T10:00:00+03:00",
        "Modified_Time": "2025-09-13T10:00:00+03:00"
    }
]

REGISTRATIONS = [
    {
        "id": "5398830000032846104",
        "Student": {"id": "5398830000033528295", "name": "A01B3660C"},
        "Program": {"name": "BTEC Level 5 IT Cyber Security", "id": "5398830000032846104"},
        "Registration_Number": "3055",
        "Registration_Date": "2023-12-14",
        "Registration_Status": "Active",
        "Total_Fees": 11800,
        "Paid_Amount": 11800,
        "Remaining_Amount": 0,
        "Created_Time": "2023-12-14T10:00:00+03:00",
        "Modified_Time": "2023-12-14T10:00:00+03:00"
    },
    {
        "id": "5398830000032846105",
        "Student": {"id": "5398830000033528295", "name": "A01B3660C"},
        "Program": {"name": "BTEC Level 5 IT Cyber Security", "id": "5398830000032846104"},
        "Registration_Number": "3057",
        "Registration_Date": "2023-12-14",
        "Registration_Status": "Active",
        "Total_Fees": 11800,
        "Paid_Amount": 11800,
        "Remaining_Amount": 0,
        "Created_Time": "2023-12-14T10:00:00+03:00",
        "Modified_Time": "2023-12-14T10:00:00+03:00"
    }
]

ENROLLMENTS = [
    {
        "id": "5398830000033001001",
        "Student": {"id": "5398830000033528295", "name": "A01B3660C"},
        "Class": {"id": "5398830000032846301", "name": "25/2011 BUS I/O Accounting Principles"},
        "Enrollment_Date": "2025-10-11",
        "Status": "Active",
        "Created_Time": "2025-10-11T10:00:00+03:00",
        "Modified_Time": "2025-10-11T10:00:00+03:00"
    },
    {
        "id": "5398830000033001002",
        "Student": {"id": "5398830000033528295", "name": "A01B3660C"},
        "Class": {"id": "5398830000032846302", "name": "24/2512T I/O Network Security Design 2"},
        "Enrollment_Date": "2025-09-13",
        "Status": "Active",
        "Created_Time": "2025-09-13T10:00:00+03:00",
        "Modified_Time": "2025-09-13T10:00:00+03:00"
    }
]

async def send_webhook(endpoint: str, data: dict):
    """Send data to backend webhook"""
    url = f"{BACKEND_URL}/{endpoint}"
    
    async with httpx.AsyncClient(timeout=30.0) as client:
        try:
            response = await client.post(url, json=data)
            if response.status_code == 200:
                result = response.json()
                print(f"✅ {endpoint}: {data.get('id', 'N/A')}")
                return result
            else:
                error_text = response.text[:200]  # First 200 chars
                print(f"❌ {endpoint}: {response.status_code} - {error_text}")
                return None
        except Exception as e:
            print(f"❌ {endpoint}: {str(e)}")
            return None

async def main():
    print("="*60)
    print("🚀 Manual Sync for Omar Tariq")
    print("="*60)
    
    # 1. Sync Classes first
    print("\n📦 Syncing Classes...")
    for cls in CLASSES:
        await send_webhook("class_created", cls)
    
    # 2. Sync Registrations
    print("\n📦 Syncing Registrations...")
    for reg in REGISTRATIONS:
        await send_webhook("registration_created", reg)
    
    # 3. Sync Enrollments
    print("\n📦 Syncing Enrollments...")
    for enr in ENROLLMENTS:
        await send_webhook("enrollment_updated", enr)
    
    print("\n" + "="*60)
    print("✅ Manual sync completed!")
    print("="*60)

if __name__ == "__main__":
    asyncio.run(main())
