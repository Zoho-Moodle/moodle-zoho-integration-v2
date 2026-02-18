"""
Quick script to download Omar's photo from Zoho
"""
import asyncio
from zoho_attachments import ZohoAttachmentHandler
from app.infra.zoho import create_zoho_client

async def download_omar_photo():
    """Download Omar's Personal_photo attachment"""
    
    print("="*60)
    print("📷 Downloading Omar's Photo from Zoho CRM")
    print("="*60)
    
    # Get Zoho access token
    zoho = create_zoho_client()
    access_token = await zoho.auth.get_access_token()
    
    print(f"✅ Got access token: {access_token[:20]}...")
    
    # Create attachment handler
    handler = ZohoAttachmentHandler(access_token)
    
    # Omar's details
    omar_zoho_id = "5398830000033528295"
    omar_student_id = "A01B3660C"
    
    # Download photo
    print(f"\n🔍 Searching for Personal_photo attachment...")
    photo_path = await handler.find_and_download_photo(
        module="BTEC_Students",
        record_id=omar_zoho_id,
        student_id=omar_student_id,
        save_dir="student_photos"
    )
    
    print("\n" + "="*60)
    if photo_path:
        print(f"✅ SUCCESS!")
        print(f"📁 Photo saved locally to: {photo_path}")
        print(f"\n📤 UPLOAD INSTRUCTIONS:")
        print(f"1. Upload this folder to your Moodle server:")
        print(f"   Local: backend/student_photos/")
        print(f"   Server: /home/moodledata/lms.abchorizon.com/student_photos/")
        print(f"   Or: moodle_root/student_photos/")
        print(f"\n2. Make sure the web server can access it")
        print(f"   chmod 755 /path/to/student_photos")
        print(f"\n3. Update database:")
        print(f"   UPDATE mdl_local_mzi_students")
        print(f"   SET photo_url = '/student_photos/{omar_student_id}.jpg'")
        print(f"   WHERE student_id = '{omar_student_id}';")
        print(f"\n4. Access URL will be:")
        print(f"   https://lms.abchorizon.com/student_photos/{omar_student_id}.jpg")
    else:
        print(f"❌ FAILED to download photo")
        print("Possible reasons:")
        print("- No attachment named 'Personal_photo' found")
        print("- Attachment exists but couldn't be downloaded")
        print("- Network/permission issues")
    print("="*60)

if __name__ == "__main__":
    asyncio.run(download_omar_photo())
