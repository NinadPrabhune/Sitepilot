#!/bin/bash

# Database Drift Detection Script - Laravel Based
# This script detects schema drift using Laravel commands

set -e

echo "🔍 Detecting database drift..."

# Colors for output
RED="\033[0;31m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
NC="\033[0m" # No Color

# Check if we are in a Laravel project
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: Not in a Laravel project directory${NC}"
    exit 1
fi

# Run migration pretend to detect drift
echo "📋 Running migration simulation..."
MIGRATION_OUTPUT=$(php artisan migrate --pretend 2>&1)

# Check for drift indicators
if echo "$MIGRATION_OUTPUT" | grep -q "Nothing to migrate"; then
    echo -e "${GREEN}✅ No database drift detected${NC}"
    exit 0
fi

# Count potential issues
CREATE_TABLES=$(echo "$MIGRATION_OUTPUT" | grep -c "create table" || true)
ADD_COLUMNS=$(echo "$MIGRATION_OUTPUT" | grep -c "add column" || true)
DROP_COLUMNS=$(echo "$MIGRATION_OUTPUT" | grep -c "drop column" || true)
MODIFY_INDEXES=$(echo "$MIGRATION_OUTPUT" | grep -c "add index\|drop index" || true)

TOTAL_CHANGES=$((CREATE_TABLES + ADD_COLUMNS + DROP_COLUMNS + MODIFY_INDEXES))

if [ $TOTAL_CHANGES -eq 0 ]; then
    echo -e "${GREEN}✅ No database drift detected${NC}"
    exit 0
fi

echo -e "${YELLOW}⚠️  Database drift detected!${NC}"
echo "Changes found:"
echo "  - Create tables: $CREATE_TABLES"
echo "  - Add columns: $ADD_COLUMNS"
echo "  - Drop columns: $DROP_COLUMNS"
echo "  - Modify indexes: $MODIFY_INDEXES"

echo ""
echo "📄 Detailed migration output:"
echo "$MIGRATION_OUTPUT"

# Check for critical changes
if [ $CREATE_TABLES -gt 0 ] || [ $DROP_COLUMNS -gt 0 ]; then
    echo -e "${RED}🚨 Critical schema changes detected!${NC}"
    echo "These changes require manual review and may cause data loss."
    exit 1
fi

echo -e "${YELLOW}⚠️  Schema modifications detected${NC}"
echo "Please review and update your migrations."
exit 1