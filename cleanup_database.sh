#!/bin/bash
# ════════════════════════════════════════════════════════════════
# Complete Database Cleanup Script After Checkpoint Restore
# ════════════════════════════════════════════════════════════════

echo "════════════════════════════════════════════════════════════"
echo "  Moodle-Zoho Integration - Database Cleanup"
echo "════════════════════════════════════════════════════════════"
echo ""

# Configuration
DB_USER="moodle_user"
DB_NAME="moodle_db"
DB_HOST="localhost"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Backup
echo -e "${YELLOW}Step 1: Creating backup...${NC}"
read -p "Enter backup filename (default: backup_$(date +%Y%m%d_%H%M%S).sql): " BACKUP_FILE
BACKUP_FILE=${BACKUP_FILE:-backup_$(date +%Y%m%d_%H%M%S).sql}

mysqldump -u $DB_USER -p $DB_NAME > $BACKUP_FILE

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Backup created: $BACKUP_FILE${NC}"
else
    echo -e "${RED}✗ Backup failed! Aborting.${NC}"
    exit 1
fi

# Step 2: Check tables
echo ""
echo -e "${YELLOW}Step 2: Checking which tables exist...${NC}"
mysql -u $DB_USER -p $DB_NAME -e "
    SELECT TABLE_NAME, TABLE_ROWS 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = '$DB_NAME' 
    AND TABLE_NAME LIKE 'mdl_local_mzi_%'
    ORDER BY TABLE_NAME;
"

# Step 3: Confirm action
echo ""
echo -e "${RED}WARNING: This will DROP the following tables:${NC}"
echo "  - mdl_local_mzi_students"
echo "  - mdl_local_mzi_registrations"
echo "  - mdl_local_mzi_payments"
echo "  - mdl_local_mzi_enrollments"
echo "  - mdl_local_mzi_requests"
echo "  - mdl_local_mzi_webhook_logs"
echo "  - mdl_local_mzi_sync_status"
echo "  - mdl_local_mzi_student_cache"
echo "  - mdl_local_mzi_admin_notifications"
echo "  - mdl_local_mzi_backend_health"
echo "  - mdl_local_mzi_grade_queue"
echo "  - mdl_local_mzi_grade_ack"
echo "  - mdl_local_mzi_btec_templates"
echo ""
read -p "Are you sure you want to DROP these tables? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo -e "${YELLOW}Aborted by user.${NC}"
    exit 0
fi

# Step 4: Drop tables
echo ""
echo -e "${YELLOW}Step 4: Dropping tables...${NC}"

mysql -u $DB_USER -p $DB_NAME << EOF
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS mdl_local_mzi_students;
DROP TABLE IF EXISTS mdl_local_mzi_registrations;
DROP TABLE IF EXISTS mdl_local_mzi_payments;
DROP TABLE IF EXISTS mdl_local_mzi_enrollments;
DROP TABLE IF EXISTS mdl_local_mzi_requests;
DROP TABLE IF EXISTS mdl_local_mzi_webhook_logs;
DROP TABLE IF EXISTS mdl_local_mzi_sync_status;
DROP TABLE IF EXISTS mdl_local_mzi_student_cache;
DROP TABLE IF EXISTS mdl_local_mzi_admin_notifications;
DROP TABLE IF EXISTS mdl_local_mzi_backend_health;
DROP TABLE IF EXISTS mdl_local_mzi_grade_queue;
DROP TABLE IF EXISTS mdl_local_mzi_grade_ack;
DROP TABLE IF EXISTS mdl_local_mzi_btec_templates;

SET FOREIGN_KEY_CHECKS = 1;
EOF

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tables dropped successfully${NC}"
else
    echo -e "${RED}✗ Failed to drop tables${NC}"
    exit 1
fi

# Step 5: Reset plugin version
echo ""
echo -e "${YELLOW}Step 5: Resetting plugin version to 2026020901...${NC}"

mysql -u $DB_USER -p $DB_NAME -e "
    UPDATE mdl_config_plugins 
    SET value = '2026020901' 
    WHERE plugin = 'local_moodle_zoho_sync' 
    AND name = 'version';
"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Version reset successfully${NC}"
else
    echo -e "${RED}✗ Failed to reset version${NC}"
fi

# Step 6: Verify
echo ""
echo -e "${YELLOW}Step 6: Verification...${NC}"
mysql -u $DB_USER -p $DB_NAME -e "
    SELECT name, value 
    FROM mdl_config_plugins 
    WHERE plugin = 'local_moodle_zoho_sync';
"

echo ""
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Cleanup completed successfully!${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo ""
echo "Next steps:"
echo "1. Navigate to: Site Administration → Notifications"
echo "2. Moodle will detect version changes"
echo "3. Click 'Upgrade Moodle database now'"
echo ""
echo "Backup file: $BACKUP_FILE"
echo ""
