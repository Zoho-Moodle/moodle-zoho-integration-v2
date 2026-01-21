"""
Database Migration Script

Script لإعداد قاعدة البيانات وإضافة الحقول الناقصة
"""

import sys
from sqlalchemy import create_engine, inspect
from app.core.config import settings
from app.infra.db.base import Base
from app.infra.db.models.student import Student


def check_table_exists(engine):
    """التحقق من وجود جدول students"""
    inspector = inspect(engine)
    tables = inspector.get_table_names()
    return 'students' in tables


def get_existing_columns(engine):
    """الحصول على الحقول الموجودة في جدول students"""
    inspector = inspect(engine)
    if not check_table_exists(engine):
        return []
    
    columns = inspector.get_columns('students')
    return [col['name'] for col in columns]


def migrate_database():
    """تحديث قاعدة البيانات"""
    
    try:
        # إنشاء الـ engine
        engine = create_engine(settings.DATABASE_URL, echo=True)
        
        print("=" * 60)
        print("🔍 فحص قاعدة البيانات...")
        print("=" * 60)
        
        # التحقق من الجدول
        if not check_table_exists(engine):
            print("❌ جدول 'students' غير موجود")
            print("✅ إنشاء جدول جديد...")
            
            # إنشاء جميع الجداول من النماذج
            Base.metadata.create_all(bind=engine)
            print("✅ تم إنشاء جميع الجداول بنجاح!")
        else:
            print("✅ جدول 'students' موجود")
            
            # الحصول على الحقول الموجودة
            existing_columns = get_existing_columns(engine)
            print(f"\n📋 الحقول الموجودة: {existing_columns}")
            
            # الحقول المطلوبة
            required_columns = {
                'zoho_id': 'VARCHAR PRIMARY KEY',
                'username': 'VARCHAR UNIQUE',
                'academic_email': 'VARCHAR UNIQUE',
                'display_name': 'VARCHAR',
                'phone': 'VARCHAR',
                'status': 'VARCHAR',
                'moodle_userid': 'INTEGER',
                'fingerprint': 'VARCHAR',
                'last_sync': 'INTEGER',
                'created_at': 'TIMESTAMP',
                'updated_at': 'TIMESTAMP',
            }
            
            print(f"\n📋 الحقول المطلوبة: {list(required_columns.keys())}")
            
            # الحقول الناقصة
            missing_columns = set(required_columns.keys()) - set(existing_columns)
            
            if missing_columns:
                print(f"\n⚠️  الحقول الناقصة: {missing_columns}")
                print("\n⚠️  تحذير: يجب تحديث قاعدة البيانات يدويًا!")
                print("\nاستخدم أحد الأوامر التالية:")
                print("\n--- باستخدام psql ---")
                print("psql -U admin -d moodle_zoho -f DATABASE_MIGRATION.sql")
                print("\n--- أو يدويًا ---")
                for col in missing_columns:
                    col_type = required_columns[col]
                    print(f"ALTER TABLE students ADD COLUMN IF NOT EXISTS {col} {col_type};")
            else:
                print("✅ جميع الحقول موجودة بالفعل!")
        
        print("\n" + "=" * 60)
        print("✅ انتهى الفحص")
        print("=" * 60)
        
        # إغلاق الـ connection
        engine.dispose()
        
    except Exception as e:
        print(f"\n❌ خطأ: {e}")
        print("\nتأكد من:")
        print("1. PostgreSQL يعمل")
        print("2. DATABASE_URL صحيح في .env")
        print("3. قاعدة البيانات موجودة")
        sys.exit(1)


if __name__ == "__main__":
    migrate_database()
