"""
Database Schema Update Script

هذا الملف يحتوي على الـ SQL اللازمة لتحديث جدول students ليطابق النموذج الجديد
"""

# ============================================
# إذا كنت تريد تحديث جدول موجود:
# ============================================

UPDATE_STUDENTS_TABLE = """
-- إضافة الحقول الناقصة إذا لم تكن موجودة
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS username VARCHAR UNIQUE;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS display_name VARCHAR;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS moodle_userid INTEGER;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS fingerprint VARCHAR;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS last_sync INTEGER;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- إنشاء index للحقول المهمة إذا لم تكن موجودة
CREATE INDEX IF NOT EXISTS idx_students_username ON students(username);
CREATE INDEX IF NOT EXISTS idx_students_moodle_userid ON students(moodle_userid);
"""

# ============================================
# أو إذا كنت تريد إنشاء جدول جديد من الصفر:
# ============================================

CREATE_STUDENTS_TABLE = """
CREATE TABLE students (
    zoho_id VARCHAR PRIMARY KEY,
    username VARCHAR UNIQUE NOT NULL,
    academic_email VARCHAR UNIQUE NOT NULL,
    
    display_name VARCHAR,
    phone VARCHAR,
    status VARCHAR,
    
    moodle_userid INTEGER,
    fingerprint VARCHAR,
    last_sync INTEGER,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_students_username ON students(username);
CREATE INDEX idx_students_academic_email ON students(academic_email);
CREATE INDEX idx_students_moodle_userid ON students(moodle_userid);
"""

# ============================================
# للتحقق من الجدول الحالي:
# ============================================

CHECK_TABLE = """
-- انظر إلى الجدول الحالي
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'students';

-- انظر إلى الـ indexes
SELECT indexname, indexdef 
FROM pg_indexes 
WHERE tablename = 'students';
"""
