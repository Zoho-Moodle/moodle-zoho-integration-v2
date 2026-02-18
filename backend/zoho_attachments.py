"""
Zoho Attachment Handler
Downloads student photos from Zoho CRM attachments
"""
import asyncio
import httpx
import json
import os
from pathlib import Path
from typing import Optional, Dict

class ZohoAttachmentHandler:
    """Handle downloading attachments from Zoho CRM"""
    
    def __init__(self, access_token: str):
        self.access_token = access_token
        self.base_url = "https://www.zohoapis.com/crm/v2"
        self.headers = {
            "Authorization": f"Zoho-oauthtoken {access_token}"
        }
    
    async def get_attachments(self, module: str, record_id: str) -> list:
        """Get list of attachments for a record"""
        url = f"{self.base_url}/{module}/{record_id}/Attachments"
        
        async with httpx.AsyncClient(timeout=30.0) as client:
            try:
                response = await client.get(url, headers=self.headers)
                
                if response.status_code == 200:
                    result = response.json()
                    return result.get("data", [])
                elif response.status_code == 204:
                    return []  # No attachments
                else:
                    print(f"❌ Error getting attachments: {response.status_code}")
                    return []
                    
            except Exception as e:
                print(f"❌ Exception getting attachments: {str(e)}")
                return []
    
    async def download_attachment(self, module: str, record_id: str, attachment_id: str, 
                                 save_path: Optional[str] = None) -> Optional[Dict]:
        """Download a specific attachment and return its info"""
        url = f"{self.base_url}/{module}/{record_id}/Attachments/{attachment_id}"
        
        async with httpx.AsyncClient(timeout=60.0) as client:
            try:
                response = await client.get(url, headers=self.headers)
                
                if response.status_code == 200:
                    # If save_path is provided, save the file
                    if save_path:
                        Path(save_path).parent.mkdir(parents=True, exist_ok=True)
                        with open(save_path, 'wb') as f:
                            f.write(response.content)
                        
                        return {
                            "success": True,
                            "path": save_path,
                            "size": len(response.content),
                            "content_type": response.headers.get("content-type")
                        }
                    else:
                        # Return the content directly
                        return {
                            "success": True,
                            "content": response.content,
                            "size": len(response.content),
                            "content_type": response.headers.get("content-type")
                        }
                else:
                    print(f"❌ Error downloading attachment: {response.status_code}")
                    return None
                    
            except Exception as e:
                print(f"❌ Exception downloading attachment: {str(e)}")
                return None
    
    async def find_and_download_photo(self, module: str, record_id: str, 
                                     student_id: str, save_dir: str = "student_photos") -> Optional[str]:
        """Find Personal_photo attachment and download it"""
        
        # Get all attachments for this record
        attachments = await self.get_attachments(module, record_id)
        
        if not attachments:
            print(f"   No attachments found for {student_id}")
            return None
        
        # Find Personal_photo attachment
        personal_photo = None
        for attachment in attachments:
            file_name = attachment.get("File_Name", "")
            if "Personal_photo" in file_name or "personal_photo" in file_name.lower():
                personal_photo = attachment
                break
        
        if not personal_photo:
            print(f"   Personal_photo not found for {student_id}")
            return None
        
        # Get attachment details
        attachment_id = personal_photo.get("id")
        file_name = personal_photo.get("File_Name")
        file_type = personal_photo.get("$file_type", "")
        
        print(f"   📷 Found photo: {file_name} ({file_type})")
        
        # Determine file extension
        if ".jpg" in file_name.lower() or ".jpeg" in file_name.lower():
            ext = ".jpg"
        elif ".png" in file_name.lower():
            ext = ".png"
        elif ".pdf" in file_name.lower():
            ext = ".pdf"
        else:
            ext = os.path.splitext(file_name)[1]
        
        # Create save path
        save_path = os.path.join(save_dir, f"{student_id}{ext}")
        
        # Download the file
        result = await self.download_attachment(module, record_id, attachment_id, save_path)
        
        if result and result.get("success"):
            print(f"   ✅ Downloaded to: {save_path}")
            return save_path
        else:
            print(f"   ❌ Failed to download")
            return None


async def test_photo_download():
    """Test downloading Omar's photo"""
    from app.infra.zoho import create_zoho_client
    
    # Get Zoho client with fresh token
    zoho = create_zoho_client()
    access_token = await zoho.get_access_token()
    
    # Create handler
    handler = ZohoAttachmentHandler(access_token)
    
    # Omar's record
    omar_id = "5398830000033528295"
    omar_student_id = "A01B3660C"
    
    print("="*60)
    print("🔍 Testing Photo Download for Omar")
    print("="*60)
    
    # Download photo
    photo_path = await handler.find_and_download_photo(
        module="Students",
        record_id=omar_id,
        student_id=omar_student_id,
        save_dir="student_photos"
    )
    
    if photo_path:
        print(f"\n✅ Success! Photo saved to: {photo_path}")
    else:
        print(f"\n❌ Failed to download photo")
    
    print("="*60)


if __name__ == "__main__":
    asyncio.run(test_photo_download())
