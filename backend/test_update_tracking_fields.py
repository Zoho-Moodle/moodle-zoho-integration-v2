"""
Test script to update tracking fields directly in Zoho
"""
import asyncio
import sys
import os
from datetime import datetime
from dotenv import load_dotenv

# Load .env file
load_dotenv()

# Simple Zoho client for testing
import httpx

class SimpleZohoClient:
    def __init__(self, client_id, client_secret, refresh_token):
        self.client_id = client_id
        self.client_secret = client_secret
        self.refresh_token = refresh_token
        self.access_token = None
        self.api_domain = "https://www.zohoapis.com"
    
    async def get_access_token(self):
        """Get access token from refresh token"""
        url = "https://accounts.zoho.com/oauth/v2/token"
        params = {
            "refresh_token": self.refresh_token,
            "client_id": self.client_id,
            "client_secret": self.client_secret,
            "grant_type": "refresh_token"
        }
        async with httpx.AsyncClient() as client:
            response = await client.post(url, params=params)
            data = response.json()
            if "access_token" in data:
                self.access_token = data["access_token"]
                return self.access_token
            else:
                raise Exception(f"Failed to get access token: {data}")
    
    async def update_record(self, module, record_id, data):
        """Update a record in Zoho"""
        if not self.access_token:
            await self.get_access_token()
        
        url = f"{self.api_domain}/crm/v2/{module}/{record_id}"
        headers = {
            "Authorization": f"Zoho-oauthtoken {self.access_token}",
            "Content-Type": "application/json"
        }
        payload = {"data": [data]}
        
        async with httpx.AsyncClient() as client:
            response = await client.put(url, headers=headers, json=payload)
            return response.json()
    
    async def get_record(self, module, record_id):
        """Get a record from Zoho"""
        if not self.access_token:
            await self.get_access_token()
        
        url = f"{self.api_domain}/crm/v2/{module}/{record_id}"
        headers = {
            "Authorization": f"Zoho-oauthtoken {self.access_token}"
        }
        
        async with httpx.AsyncClient() as client:
            response = await client.get(url, headers=headers)
            data = response.json()
            if "data" in data and len(data["data"]) > 0:
                return data["data"][0]
            return data

async def test_update_tracking_fields():
    """Test updating Synced_to_Moodle and Last_Sync_Date fields"""
    
    print("=" * 60)
    print("Testing Direct Zoho Tracking Field Update")
    print("=" * 60)
    
    # Get credentials from environment
    client_id = os.getenv("ZOHO_CLIENT_ID")
    client_secret = os.getenv("ZOHO_CLIENT_SECRET")
    refresh_token = os.getenv("ZOHO_REFRESH_TOKEN")
    
    if not all([client_id, client_secret, refresh_token]):
        print("❌ Missing Zoho credentials in .env file")
        return
    
    # Initialize Zoho client
    zoho = SimpleZohoClient(client_id, client_secret, refresh_token)
    
    # Test student ID (from your previous tests)
    student_id = "5398830000123893227"
    
    print(f"\nStudent ID: {student_id}")
    print("\n" + "-" * 60)
    
    # Test 1: Try with Boolean True and ISO datetime with timezone
    print("\nTest 1: Boolean True + ISO DateTime (YYYY-MM-DDTHH:MM:SS+00:00)")
    try:
        result = await zoho.update_record(
            module="BTEC_Students",
            record_id=student_id,
            data={
                "Synced_to_Moodle": True,
                "Last_Sync_Date": datetime.now().strftime("%Y-%m-%dT%H:%M:%S+00:00")
            }
        )
        print(f"✅ SUCCESS: {result}")
    except Exception as e:
        print(f"❌ FAILED: {type(e).__name__}: {e}")
    
    await asyncio.sleep(2)
    
    # Test 2: Try with string "true" instead of Boolean
    print("\nTest 2: String 'true' + ISO DateTime")
    try:
        result = await zoho.update_record(
            module="BTEC_Students",
            record_id=student_id,
            data={
                "Synced_to_Moodle": "true",
                "Last_Sync_Date": datetime.now().strftime("%Y-%m-%dT%H:%M:%S+00:00")
            }
        )
        print(f"✅ SUCCESS: {result}")
    except Exception as e:
        print(f"❌ FAILED: {type(e).__name__}: {e}")
    
    await asyncio.sleep(2)
    
    # Test 3: Try without timezone (simpler datetime)
    print("\nTest 3: Boolean True + Simple DateTime (YYYY-MM-DD HH:MM:SS)")
    try:
        result = await zoho.update_record(
            module="BTEC_Students",
            record_id=student_id,
            data={
                "Synced_to_Moodle": True,
                "Last_Sync_Date": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            }
        )
        print(f"✅ SUCCESS: {result}")
    except Exception as e:
        print(f"❌ FAILED: {type(e).__name__}: {e}")
    
    await asyncio.sleep(2)
    
    # Test 4: Update only Synced_to_Moodle (no datetime)
    print("\nTest 4: Only Synced_to_Moodle field")
    try:
        result = await zoho.update_record(
            module="BTEC_Students",
            record_id=student_id,
            data={
                "Synced_to_Moodle": True
            }
        )
        print(f"✅ SUCCESS: {result}")
    except Exception as e:
        print(f"❌ FAILED: {type(e).__name__}: {e}")
    
    await asyncio.sleep(2)
    
    # Test 5: Try ISO format without timezone
    print("\nTest 5: Boolean True + ISO DateTime without timezone")
    try:
        result = await zoho.update_record(
            module="BTEC_Students",
            record_id=student_id,
            data={
                "Synced_to_Moodle": True,
                "Last_Sync_Date": datetime.now().strftime("%Y-%m-%dT%H:%M:%S")
            }
        )
        print(f"✅ SUCCESS: {result}")
    except Exception as e:
        print(f"❌ FAILED: {type(e).__name__}: {e}")
    
    print("\n" + "=" * 60)
    print("Testing Complete!")
    print("=" * 60)
    
    # Fetch the record to see current values
    print("\nFetching student record to verify...")
    try:
        student_data = await zoho.get_record(
            module="BTEC_Students",
            record_id=student_id
        )
        print(f"\nCurrent values:")
        print(f"  Synced_to_Moodle: {student_data.get('Synced_to_Moodle', 'N/A')}")
        print(f"  Last_Sync_Date: {student_data.get('Last_Sync_Date', 'N/A')}")
    except Exception as e:
        print(f"❌ Could not fetch record: {e}")

if __name__ == "__main__":
    try:
        asyncio.run(test_update_tracking_fields())
    except KeyboardInterrupt:
        print("\n\nTest interrupted by user")
        sys.exit(0)
