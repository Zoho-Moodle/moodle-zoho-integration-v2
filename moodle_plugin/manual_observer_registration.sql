-- Manual Observer Registration for Moodle-Zoho Integration
-- Run this on your Moodle database to register observers without reinstall

-- Clean up first (remove any old entries)
DELETE FROM mdl_events_handlers WHERE component = 'local_moodle_zoho_sync';
DELETE FROM mdl_events_handlers WHERE component = 'local_mb_zoho_sync';

-- Register 6 observers
INSERT INTO mdl_events_handlers (eventname, component, handlerfunction, priority, internal) VALUES
('\\core\\event\\user_created', 'local_moodle_zoho_sync', '\\local_moodle_zoho_sync\\observer::user_created', 9999, 0),
('\\core\\event\\user_updated', 'local_moodle_zoho_sync', '\\local_moodle_zoho_sync\\observer::user_updated', 9999, 0),
('\\core\\event\\user_enrolment_created', 'local_moodle_zoho_sync', '\\local_moodle_zoho_sync\\observer::enrollment_created', 9999, 0),
('\\core\\event\\user_enrolment_deleted', 'local_moodle_zoho_sync', '\\local_moodle_zoho_sync\\observer::enrollment_deleted', 9999, 0),
('\\mod_assign\\event\\submission_graded', 'local_moodle_zoho_sync', '\\local_moodle_zoho_sync\\observer::submission_graded', 9999, 0),
('\\core\\event\\user_graded', 'local_moodle_zoho_sync', '\\local_moodle_zoho_sync\\observer::grade_updated', 9999, 0);

-- Verify registration
SELECT 
    id,
    eventname,
    handlerfunction,
    priority
FROM mdl_events_handlers 
WHERE component = 'local_moodle_zoho_sync'
ORDER BY eventname;
