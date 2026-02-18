"""
Quick script to update Omar's student_id field
"""
import asyncio
import httpx

BACKEND_URL = "http://localhost:8001/api/v1/webhooks/student-dashboard/student-updated"

# Omar's student data with student_id field
OMAR_DATA = {
    "id": "5398830000033528295",
    "Name": "A01B3660C",  # This is the Student ID field
    "Student_Moodle_ID": 3,
    "First_Name": "Omar",
    "Last_Name": "Tariq",
    "Academic_Email": "A01B3660C@abchorizon.com",
    "Phone_Number": "905054210712",
    "Nationality": "Syria",
    "Birth_Date": "2023-12-14",
    "Gender": "Male",
    "Address": "Istanbul, Turkey",
    "Emergency_Contact_Name": "Tariq Father",
    "Emergency_Phone_Number": "905054210712",
    "Status": "Active",
    "Photo_URL": "",
    "Created_Time": "2023-12-14T10:00:00+03:00",
    "Modified_Time": "2025-02-16T15:30:00+03:00"
}

async def update_student():
    """Update Omar's student record with student_id"""
    async with httpx.AsyncClient(timeout=30.0) as client:
        try:
            print(f"🔄 Updating Omar's student_id to: {OMAR_DATA['Name']}")
            
            response = await client.post(
                BACKEND_URL,
                json=OMAR_DATA
            )
            
            if response.status_code == 200:
                result = response.json()
                print(f"✅ Student updated successfully!")
                print(f"   Student ID: {OMAR_DATA['Name']}")
                print(f"   Moodle User ID: {OMAR_DATA['Student_Moodle_ID']}")
                print(f"   Response: {json.dumps(result, indent=2)}")
            else:
                print(f"❌ Failed to update student!")
                print(f"   Status: {response.status_code}")
                print(f"   Response: {response.text}")
                
        except Exception as e:
            print(f"❌ Error updating student: {str(e)}")

if __name__ == "__main__":
    import json
    print("=" * 60)
    print("🚀 Updating Omar's Student ID")
    print("=" * 60)
    asyncio.run(update_student())
    print("=" * 60)
