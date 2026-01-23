#!/usr/bin/env python3
"""
تحميل البيانات التجريبية في الـ debug API
"""

import requests
import json

BASE_URL = "http://localhost:8001/v1/debug"

def load_sample_data():
    """تحميل البيانات التجريبية"""
    url = f"{BASE_URL}/test-load-sample-data"
    
    response = requests.post(url)
    print(f"Status: {response.status_code}")
    print(f"Response: {response.json()}")

def get_sample(module_name, count=3):
    """احصل على عينة من موديول"""
    url = f"{BASE_URL}/module/{module_name}/sample?count={count}"
    
    response = requests.get(url)
    if response.status_code == 200:
        data = response.json()
        print(f"\n✅ {module_name}:")
        print(json.dumps(data, indent=2, ensure_ascii=False))
    else:
        print(f"❌ خطأ في {module_name}: {response.status_code}")

if __name__ == "__main__":
    print("🔄 تحميل البيانات التجريبية...")
    load_sample_data()
    
    print("\n\n📊 استخراج العينات:")
    get_sample("BTEC_Enrollments", 3)
    get_sample("BTEC_Classes", 3)
    get_sample("Products", 3)
