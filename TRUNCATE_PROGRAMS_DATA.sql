-- ════════════════════════════════════════════════════════════════
-- Clear programs.php data only (registrations + installments + payments)
-- Students table is NOT touched
-- Run on the Moodle DB (phpMyAdmin or MySQL CLI)
-- ════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE mdl_local_mzi_payments;
TRUNCATE TABLE mdl_local_mzi_installments;
TRUNCATE TABLE mdl_local_mzi_registrations;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'programs.php tables cleared — ready for fresh sync' AS status;
