"""
Quick SQL update to add student_id for Omar
Run this directly in phpMyAdmin or MySQL
"""

sql_update = """
-- Update Omar's student_id field
UPDATE mdl_local_mzi_students 
SET student_id = 'A01B3660C',
    address = 'Istanbul, Turkey',
    updated_at = UNIX_TIMESTAMP()
WHERE zoho_student_id = '5398830000033528295'
AND moodle_user_id = 3;

-- Verify the update
SELECT 
    id,
    student_id,
    zoho_student_id,
    moodle_user_id,
    first_name,
    last_name,
    email,
    address,
    status
FROM mdl_local_mzi_students
WHERE moodle_user_id = 3;
"""

print("=" * 80)
print("SQL UPDATE SCRIPT")
print("=" * 80)
print("\nCopy and run this in phpMyAdmin or MySQL client:\n")
print(sql_update)
print("=" * 80)
print("\nThis will:")
print("1. Set student_id = 'A01B3660C' for Omar")
print("2. Set address = 'Istanbul, Turkey'")
print("3. Update the timestamp")
print("4. Show the updated record")
print("=" * 80)
