-- Allow NULL for moodle_user_id
ALTER TABLE mdl_local_mzi_students 
MODIFY COLUMN moodle_user_id BIGINT(10) NULL;
