"""
Test automatic photo upload to Moodle
Tests sending student data with photo as base64
"""
import asyncio
import httpx
import json
import base64
import os

BACKEND_URL = "http://localhost:8001/api/v1/webhooks/student-dashboard/student-updated"

async def test_photo_upload():
    """Test uploading Omar's data with photo"""
    
    print("="*60)
    print("🧪 Testing Automatic Photo Upload")
    print("="*60)
    
    # Path to Omar's photo
    photo_path = "student_photos/A01B3660C.jpg"
    
    if not os.path.exists(photo_path):
        print(f"❌ Photo not found: {photo_path}")
        print("Run: python download_omar_photo.py first")
        return
    
    # Read and encode photo
    with open(photo_path, 'rb') as f:
        photo_base64 = base64.b64encode(f.read()).decode('utf-8')
    
    print(f"✅ Photo loaded: {len(photo_base64)} characters")
    
    # Omar's data with photo
    omar_data = {
        "id": "5398830000033528295",
        "Name": "A01B3660C",
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
        "Created_Time": "2023-12-14T10:00:00+03:00",
        "Modified_Time": "2025-02-16T15:30:00+03:00",
        
        # Photo data
        "Photo_Data": photo_base64,
        "Photo_Filename": "A01B3660C.jpg"
    }
    
    print(f"\n📤 Sending data to backend...")
    print(f"   Endpoint: {BACKEND_URL}")
    print(f"   Student ID: {omar_data['Name']}")
    print(f"   Photo size: {len(photo_base64)} chars")
    
    async with httpx.AsyncClient(timeout=60.0) as client:
        try:
            response = await client.post(BACKEND_URL, json=omar_data)
            
            if response.status_code == 200:
                result = response.json()
                print(f"\n✅ SUCCESS!")
                print(f"   Response: {json.dumps(result, indent=2)}")
                print(f"\n🎉 Photo uploaded automatically!")
                print(f"   Check profile: https://lms.abchorizon.com/local/moodle_zoho_sync/ui/student/profile.php")
            else:
                print(f"\n❌ Failed!")
                print(f"   Status: {response.status_code}")
                print(f"   Response: {response.text}")
        
        except Exception as e:
            print(f"\n❌ Error: {str(e)}")
    
    print("="*60)


if __name__ == "__main__":
    asyncio.run(test_photo_upload())
