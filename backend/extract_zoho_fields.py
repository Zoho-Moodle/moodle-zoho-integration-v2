"""
📊 Script لاستخراج معلومات الحقول من جميع الموديولات الـ debug API
ونقارنها مع ما هو موجود في المشروع
"""

import requests
import json

BASE_URL = "http://localhost:8001/v1/debug"

# الموديولات المتاحة
MODULES = [
    "Contacts",
    "Products",
    "BTEC_Classes",
    "BTEC_Enrollments",
    "BTEC_Registrations",
    "BTEC_Payments",
    "BTEC",
    "BTEC_Grades"
]

def get_module_info(module_name):
    """احصل على معلومات الحقول لموديول معين"""
    url = f"{BASE_URL}/module/{module_name}/fields"
    
    try:
        response = requests.get(url)
        if response.status_code == 200:
            return response.json()
        else:
            print(f"❌ خطأ في {module_name}: {response.status_code}")
            return None
    except Exception as e:
        print(f"❌ خطأ الاتصال: {str(e)}")
        return None

def get_sample_record(module_name):
    """احصل على عينة من السجلات"""
    url = f"{BASE_URL}/module/{module_name}/sample?count=1"
    
    try:
        response = requests.get(url)
        if response.status_code == 200:
            return response.json()
        else:
            return None
    except Exception as e:
        return None

def main():
    print("=" * 80)
    print("🔍 استخراج معلومات الحقول من جميع الموديولات")
    print("=" * 80)
    
    all_modules_info = {}
    
    for module in MODULES:
        print(f"\n📌 الموديول: {module}")
        print("-" * 80)
        
        # احصل على معلومات الحقول
        fields_info = get_module_info(module)
        
        if fields_info:
            total_fields = fields_info.get("total_fields", 0)
            print(f"   📋 عدد الحقول: {total_fields}")
            
            # احصل على عينة
            sample = get_sample_record(module)
            if sample and sample.get("records"):
                record = sample["records"][0]
                print(f"   📊 الحقول في السجل:")
                for field_name in sorted(record.keys()):
                    value = record[field_name]
                    field_type = type(value).__name__
                    print(f"      - {field_name}: {field_type}")
            
            all_modules_info[module] = fields_info
        else:
            print(f"   ❌ فشل الحصول على المعلومات")
    
    # حفظ المعلومات في ملف
    with open("backend/ZOHO_FIELDS_MAPPING.json", "w", encoding="utf-8") as f:
        json.dump(all_modules_info, f, ensure_ascii=False, indent=2)
    
    print("\n" + "=" * 80)
    print("✅ تم حفظ المعلومات في: backend/ZOHO_FIELDS_MAPPING.json")
    print("=" * 80)

if __name__ == "__main__":
    main()
