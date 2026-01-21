#!/usr/bin/env python3
"""
Database Setup و Migration Script

يقوم بـ:
1. فحص قاعدة البيانات
2. إنشاء الجدول إذا كان غير موجود
3. إضافة الحقول الناقصة
4. إنشاء الـ indexes
"""

import sys
import os
from pathlib import Path

# إضافة المسار
sys.path.insert(0, str(Path(__file__).parent))

from sqlalchemy import (
    create_engine, 
    inspect, 
    text,
    VARCHAR,
    Integer,
    DateTime
)
from datetime import datetime

# استيراد من المشروع
try:
    from app.core.config import settings
    from app.infra.db.models.student import Student
    from app.infra.db.base import Base
except ImportError as e:
    print(f"❌ خطأ في الاستيراد: {e}")
    print("تأكد من أنك في مجلد backend")
    sys.exit(1)


def init_db():
    """إعداد قاعدة البيانات"""
    
    print("\n" + "="*70)
    print("🔧 إعداد قاعدة البيانات - Database Setup")
    print("="*70 + "\n")
    
    try:
        # إنشاء الـ engine
        engine = create_engine(settings.DATABASE_URL, echo=False)
        
        print("✅ متصل بقاعدة البيانات")
        print(f"📍 URL: {settings.DATABASE_URL.split('@')[1] if '@' in settings.DATABASE_URL else 'Unknown'}")
        
        # فحص الجداول الموجودة
        inspector = inspect(engine)
        existing_tables = inspector.get_table_names()
        
        print(f"\n📋 الجداول الموجودة: {existing_tables if existing_tables else 'لا توجد'}")
        
        # إنشاء الجداول
        print("\n🔨 إنشاء الجداول من النماذج...")
        Base.metadata.create_all(bind=engine)
        print("✅ تم إنشاء الجداول بنجاح")
        
        # فحص جدول students
        inspector = inspect(engine)
        if 'students' in inspector.get_table_names():
            columns = inspector.get_columns('students')
            column_names = [col['name'] for col in columns]
            
            print(f"\n📊 جدول 'students':")
            print(f"   عدد الحقول: {len(column_names)}")
            print(f"   الحقول: {', '.join(column_names)}")
            
            # الحقول المتوقعة
            expected_columns = {
                'zoho_id', 'username', 'academic_email', 'display_name',
                'phone', 'status', 'moodle_userid', 'fingerprint', 
                'last_sync', 'created_at', 'updated_at'
            }
            
            existing_set = set(column_names)
            missing = expected_columns - existing_set
            extra = existing_set - expected_columns
            
            if missing:
                print(f"\n⚠️  الحقول الناقصة: {missing}")
            if extra:
                print(f"\n⚠️  حقول إضافية: {extra}")
            if not missing and not extra:
                print("\n✅ جميع الحقول صحيحة!")
        
        # فحص الـ indexes
        print("\n📑 الـ Indexes:")
        indexes = inspector.get_indexes('students')
        for idx in indexes:
            print(f"   - {idx['name']}: {idx['column_names']}")
        
        print("\n" + "="*70)
        print("✅ انتهى إعداد قاعدة البيانات")
        print("="*70 + "\n")
        
        engine.dispose()
        return True
        
    except Exception as e:
        print(f"\n❌ خطأ: {e}")
        print("\nأسباب محتملة:")
        print("1. PostgreSQL لم يبدأ تشغيله")
        print("2. DATABASE_URL غير صحيح في .env")
        print("3. قاعدة البيانات غير موجودة")
        print("\nالحل:")
        print("- تأكد من قيمة DATABASE_URL في .env")
        print("- تأكد من تشغيل PostgreSQL")
        print("- تأكد من وجود قاعدة البيانات أو أنشئها بـ:")
        print("  createdb moodle_zoho")
        return False


def migrate_db_manually():
    """تحديث يدوي للحقول الناقصة"""
    
    print("\n⚠️  تحديث قاعدة البيانات يدويًا...\n")
    
    try:
        engine = create_engine(settings.DATABASE_URL, echo=False)
        
        with engine.connect() as connection:
            # قائمة الـ ALTER TABLE أوامر
            alter_commands = [
                "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS username VARCHAR UNIQUE;",
                "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS display_name VARCHAR;",
                "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS moodle_userid INTEGER;",
                "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS fingerprint VARCHAR;",
                "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS last_sync INTEGER;",
                "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;",
                "ALTER TABLE IF EXISTS students ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;",
            ]
            
            # تنفيذ الأوامر
            for cmd in alter_commands:
                try:
                    connection.execute(text(cmd))
                    print(f"✅ {cmd.split('ADD COLUMN')[1].split(';')[0].strip()}")
                except Exception as e:
                    print(f"⚠️  {cmd.split('ADD COLUMN')[1].split(';')[0].strip()}: {str(e)[:50]}")
            
            # إنشاء الـ indexes
            index_commands = [
                "CREATE INDEX IF NOT EXISTS idx_students_username ON students(username);",
                "CREATE INDEX IF NOT EXISTS idx_students_moodle_userid ON students(moodle_userid);",
            ]
            
            for cmd in index_commands:
                try:
                    connection.execute(text(cmd))
                    print(f"✅ {cmd.split('INDEX')[1].split('ON')[0].strip()}")
                except Exception as e:
                    print(f"⚠️  {cmd.split('INDEX')[1].split('ON')[0].strip()}: {str(e)[:50]}")
            
            connection.commit()
        
        engine.dispose()
        print("\n✅ انتهى التحديث")
        return True
        
    except Exception as e:
        print(f"❌ خطأ: {e}")
        return False


if __name__ == "__main__":
    # إعداد قاعدة البيانات
    success = init_db()
    
    # إذا فشل، حاول التحديث اليدوي
    if not success:
        print("\nمحاولة التحديث اليدوي...")
        migrate_db_manually()
