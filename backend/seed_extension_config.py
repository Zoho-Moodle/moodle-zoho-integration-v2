"""
Seed Extension Configuration Data

Creates sample module settings and field mappings for Students module.
"""

import uuid
import psycopg2
from app.core.config import settings

MODULES = [
    "students", "programs", "classes", "enrollments",
    "units", "registrations", "payments", "grades"
]

STUDENT_MAPPINGS = [
    {
        "canonical_field": "academic_email",
        "zoho_field_api_name": "Academic_Email",
        "required": True,
        "default_value": None,
        "transform_rules": {}
    },
    {
        "canonical_field": "username",
        "zoho_field_api_name": "Academic_Email",
        "required": True,
        "default_value": None,
        "transform_rules": {"type": "before_at", "description": "Extract username before @ symbol"}
    },
    {
        "canonical_field": "display_name",
        "zoho_field_api_name": "Display_Name",
        "required": False,
        "default_value": None,
        "transform_rules": {}
    },
    {
        "canonical_field": "phone",
        "zoho_field_api_name": "Phone_Number",
        "required": False,
        "default_value": None,
        "transform_rules": {}
    },
    {
        "canonical_field": "status",
        "zoho_field_api_name": "Status",
        "required": True,
        "default_value": "Active",
        "transform_rules": {}
    },
    {
        "canonical_field": "profile_image_url",
        "zoho_field_api_name": "Profile_Image",
        "required": False,
        "default_value": None,
        "transform_rules": {"type": "image_url_resolver"}
    }
]


def seed_extension_config():
    """Seed extension configuration"""
    try:
        db_url = settings.DATABASE_URL
        parts = db_url.replace("postgresql+psycopg2://", "").split("@")
        user_pass = parts[0].split(":")
        host_db = parts[1].split("/")
        host_port = host_db[0].split(":")
        
        conn = psycopg2.connect(
            host=host_port[0],
            port=host_port[1] if len(host_port) > 1 else "5432",
            database=host_db[1],
            user=user_pass[0],
            password=user_pass[1]
        )
        
        cursor = conn.cursor()
        
        # Create module settings for all modules
        print("Creating module settings...")
        for module in MODULES:
            module_id = str(uuid.uuid4())
            enabled = module != "grades"  # Grades disabled by default
            
            cursor.execute("""
                INSERT INTO module_settings (id, tenant_id, module_name, enabled, schedule_mode)
                VALUES (%s, %s, %s, %s, %s)
                ON CONFLICT (tenant_id, module_name) DO NOTHING
            """, (module_id, "default", module, enabled, "manual"))
        
        conn.commit()
        print(f"✅ Created settings for {len(MODULES)} modules")
        
        # Create field mappings for Students
        print("\nCreating student field mappings...")
        for mapping in STUDENT_MAPPINGS:
            mapping_id = str(uuid.uuid4())
            import json
            transform_json = json.dumps(mapping["transform_rules"]) if mapping["transform_rules"] else '{}'
            
            cursor.execute("""
                INSERT INTO field_mappings 
                (id, tenant_id, module_name, canonical_field, zoho_field_api_name, 
                 required, default_value, transform_rules_json)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s::jsonb)
                ON CONFLICT (tenant_id, module_name, canonical_field) 
                DO UPDATE SET 
                    zoho_field_api_name = EXCLUDED.zoho_field_api_name,
                    required = EXCLUDED.required,
                    default_value = EXCLUDED.default_value,
                    transform_rules_json = EXCLUDED.transform_rules_json
            """, (
                mapping_id, "default", "students",
                mapping["canonical_field"], mapping["zoho_field_api_name"],
                mapping["required"], mapping["default_value"],
                transform_json
            ))
        
        conn.commit()
        print(f"✅ Created {len(STUDENT_MAPPINGS)} field mappings for students")
        
        # Show summary
        cursor.execute("SELECT COUNT(*) FROM module_settings WHERE tenant_id = 'default'")
        module_count = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM field_mappings WHERE tenant_id = 'default'")
        mapping_count = cursor.fetchone()[0]
        
        print(f"\n📊 Configuration Summary:")
        print(f"   - Modules configured: {module_count}")
        print(f"   - Field mappings: {mapping_count}")
        print(f"   - Tenant: default")
        
        cursor.close()
        conn.close()
        
    except Exception as e:
        print(f"❌ Error seeding configuration: {e}")
        raise


if __name__ == "__main__":
    seed_extension_config()
