#!/bin/bash
# ════════════════════════════════════════════════════════════════
# Complete Student Dashboard Deletion Script
# Run on SERVER as root
# ════════════════════════════════════════════════════════════════

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
echo -e "${YELLOW}  Student Dashboard - Complete Deletion${NC}"
echo -e "${YELLOW}════════════════════════════════════════════════════════════${NC}"
echo ""

# Database credentials
DB_USER="moodle_user"
DB_PASS="BaBa112233@@"
DB_NAME="moodle_db"

# Moodle path
MOODLE_PATH="/var/www/html/moodle/local/moodle_zoho_sync"

# Confirmation
echo -e "${RED}WARNING: This will DELETE all Student Dashboard files!${NC}"
echo ""
read -p "Are you sure? Type 'DELETE' to confirm: " CONFIRM

if [ "$CONFIRM" != "DELETE" ]; then
    echo -e "${YELLOW}Aborted.${NC}"
    exit 0
fi

echo ""
echo -e "${YELLOW}Step 1: Deleting Frontend Files...${NC}"

# Main dashboard page
rm -fv "$MOODLE_PATH/ui/dashboard/student.php"

# JavaScript
rm -fv "$MOODLE_PATH/ui/dashboard/js/student_dashboard.js"

# CSS
rm -fv "$MOODLE_PATH/ui/dashboard/css/dashboard.css"

# AJAX proxy files
rm -fv "$MOODLE_PATH/ui/ajax/load_profile.php"
rm -fv "$MOODLE_PATH/ui/ajax/load_academics.php"
rm -fv "$MOODLE_PATH/ui/ajax/load_finance.php"
rm -fv "$MOODLE_PATH/ui/ajax/load_classes.php"
rm -fv "$MOODLE_PATH/ui/ajax/load_grades.php"
rm -fv "$MOODLE_PATH/ui/ajax/load_requests.php"

# AJAX action files
rm -fv "$MOODLE_PATH/ui/ajax/acknowledge_grade.php"
rm -fv "$MOODLE_PATH/ui/ajax/submit_request.php"

echo -e "${GREEN}✓ Frontend files deleted${NC}"

echo ""
echo -e "${YELLOW}Step 2: Dropping Database Table...${NC}"

mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -e "
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS mdl_local_mzi_grade_ack;
SET FOREIGN_KEY_CHECKS = 1;
SELECT 'Table dropped' AS status;
" 2>&1

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database table dropped${NC}"
else
    echo -e "${RED}✗ Failed to drop table${NC}"
fi

echo ""
echo -e "${YELLOW}Step 3: Clearing Moodle Cache...${NC}"

sudo -u www-data php /var/www/html/moodle/admin/cli/purge_caches.php

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Cache cleared${NC}"
else
    echo -e "${RED}✗ Failed to clear cache${NC}"
fi

echo ""
echo -e "${YELLOW}Step 4: Verifying Deletion...${NC}"

# Check if files still exist
REMAINING=0

for file in \
    "$MOODLE_PATH/ui/dashboard/student.php" \
    "$MOODLE_PATH/ui/dashboard/js/student_dashboard.js" \
    "$MOODLE_PATH/ui/dashboard/css/dashboard.css" \
    "$MOODLE_PATH/ui/ajax/load_profile.php" \
    "$MOODLE_PATH/ui/ajax/load_academics.php" \
    "$MOODLE_PATH/ui/ajax/load_finance.php" \
    "$MOODLE_PATH/ui/ajax/load_classes.php" \
    "$MOODLE_PATH/ui/ajax/load_grades.php" \
    "$MOODLE_PATH/ui/ajax/load_requests.php" \
    "$MOODLE_PATH/ui/ajax/acknowledge_grade.php" \
    "$MOODLE_PATH/ui/ajax/submit_request.php"
do
    if [ -f "$file" ]; then
        echo -e "${RED}✗ Still exists: $file${NC}"
        REMAINING=$((REMAINING + 1))
    fi
done

# Check database table
TABLE_EXISTS=$(mysql -u $DB_USER -p"$DB_PASS" $DB_NAME -sN -e "
    SELECT COUNT(*) FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = '$DB_NAME' 
    AND TABLE_NAME = 'mdl_local_mzi_grade_ack';
")

if [ "$TABLE_EXISTS" -gt 0 ]; then
    echo -e "${RED}✗ Table still exists: mdl_local_mzi_grade_ack${NC}"
    REMAINING=$((REMAINING + 1))
fi

echo ""
if [ $REMAINING -eq 0 ]; then
    echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}  ✓ All Student Dashboard files deleted successfully!${NC}"
    echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
else
    echo -e "${RED}════════════════════════════════════════════════════════════${NC}"
    echo -e "${RED}  ✗ $REMAINING items still remain${NC}"
    echo -e "${RED}════════════════════════════════════════════════════════════${NC}"
fi

echo ""
echo "Next steps:"
echo "1. Rebuild Student Dashboard from scratch"
echo "2. Create new architecture design"
echo "3. Implement webhook-driven model"
echo ""
