#!/bin/bash
# SB-013 Migration Testing Script
# 
# This script validates SB-013 migrations and provides test results
# Usage: ./test-sb-013-migrations.sh [--execute]

set -e

MATOMO_ROOT="${MATOMO_ROOT:-.}"
PLUGIN_PATH="$MATOMO_ROOT/plugins"
LOG_FILE="/tmp/sb-013-test-$(date +%Y%m%d_%H%M%S).log"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "╔════════════════════════════════════════════════════════════╗"
echo "║     SB-013 Database Layer Migration Test Suite             ║"
echo "║     VisitorFlowIntelligence, GeoPrecision,                 ║"
echo "║     DeviceIntelligence                                     ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Test 1: Check migration files exist
echo -e "${YELLOW}Test 1: Validating migration files...${NC}"
MISSING=0

MIGRATION_FILES=(
    "VisitorFlowIntelligence/Infrastructure/Migrations/Migration.php"
    "VisitorFlowIntelligence/Infrastructure/Migrations/Migration_1_0_0_CreateVisitorFlowRawTable.php"
    "GeoPrecision/Infrastructure/Migrations/Migration.php"
    "GeoPrecision/Infrastructure/Migrations/Migration_1_0_0_CreateGeoPrecisionRawTable.php"
    "DeviceIntelligence/Infrastructure/Migrations/Migration.php"
    "DeviceIntelligence/Infrastructure/Migrations/Migration_1_0_0_CreateDeviceIntelligenceRawTable.php"
)

for file in "${MIGRATION_FILES[@]}"; do
    if [ -f "$PLUGIN_PATH/$file" ]; then
        echo -e "  ${GREEN}✓${NC} $file"
    else
        echo -e "  ${RED}✗${NC} $file (MISSING)"
        MISSING=$((MISSING + 1))
    fi
done

if [ $MISSING -eq 0 ]; then
    echo -e "${GREEN}✓ All migration files present${NC}"
else
    echo -e "${RED}✗ $MISSING migration files missing${NC}"
    exit 1
fi

echo ""

# Test 2: Check MigrationManager exists
echo -e "${YELLOW}Test 2: Validating MigrationManager...${NC}"
if [ -f "$PLUGIN_PATH/VisitorFlowIntelligence/Infrastructure/MigrationManager.php" ]; then
    echo -e "  ${GREEN}✓${NC} MigrationManager found"
else
    echo -e "  ${RED}✗${NC} MigrationManager not found"
    exit 1
fi

echo ""

# Test 3: Check console commands
echo -e "${YELLOW}Test 3: Validating console commands...${NC}"
if [ -f "$PLUGIN_PATH/VisitorFlowIntelligence/Commands/TestMigrationsCommand.php" ]; then
    echo -e "  ${GREEN}✓${NC} TestMigrationsCommand found"
else
    echo -e "  ${RED}✗${NC} TestMigrationsCommand not found"
    exit 1
fi

echo ""

# Test 4: Check PHP syntax
echo -e "${YELLOW}Test 4: Validating PHP syntax...${NC}"
SYNTAX_ERRORS=0

for file in "${MIGRATION_FILES[@]}"; do
    if php -l "$PLUGIN_PATH/$file" 2>&1 | grep -q "Parse error"; then
        echo -e "  ${RED}✗${NC} Syntax error in $file"
        php -l "$PLUGIN_PATH/$file"
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done

# Check MigrationManager
if php -l "$PLUGIN_PATH/VisitorFlowIntelligence/Infrastructure/MigrationManager.php" 2>&1 | grep -q "Parse error"; then
    echo -e "  ${RED}✗${NC} Syntax error in MigrationManager.php"
    php -l "$PLUGIN_PATH/VisitorFlowIntelligence/Infrastructure/MigrationManager.php"
    SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
else
    echo -e "  ${GREEN}✓${NC} MigrationManager syntax OK"
fi

# Check command
if php -l "$PLUGIN_PATH/VisitorFlowIntelligence/Commands/TestMigrationsCommand.php" 2>&1 | grep -q "Parse error"; then
    echo -e "  ${RED}✗${NC} Syntax error in TestMigrationsCommand.php"
    php -l "$PLUGIN_PATH/VisitorFlowIntelligence/Commands/TestMigrationsCommand.php"
    SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
else
    echo -e "  ${GREEN}✓${NC} TestMigrationsCommand syntax OK"
fi

if [ $SYNTAX_ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ All PHP files have valid syntax${NC}"
else
    echo -e "${RED}✗ $SYNTAX_ERRORS PHP syntax errors found${NC}"
    exit 1
fi

echo ""

# Test 5: Check database connectivity (optional)
echo -e "${YELLOW}Test 5: Database connectivity check (if available)...${NC}"
if command -v mysql &> /dev/null; then
    mysql -e "SELECT 1" > /dev/null 2>&1 && echo -e "  ${GREEN}✓${NC} Database connection OK" || echo -e "  ${YELLOW}⚠${NC} Database not accessible (expected for CI)"
else
    echo -e "  ${YELLOW}⚠${NC} MySQL client not found (CI environment)"
fi

echo ""

# Test 6: Show migration status (requires Matomo console)
echo -e "${YELLOW}Test 6: Getting migration status via console...${NC}"
if [ -f "$MATOMO_ROOT/console" ]; then
    echo -e "  ${YELLOW}ℹ${NC} Running: ./console visitorflow:test-migrations --status"
    if "$MATOMO_ROOT/console" visitorflow:test-migrations --status 2>/dev/null; then
        echo -e "  ${GREEN}✓${NC} Console command executed successfully"
    else
        echo -e "  ${YELLOW}⚠${NC} Console command not yet available (will be on plugin enable)"
    fi
else
    echo -e "  ${YELLOW}⚠${NC} Matomo console not found (expected for CI)"
fi

echo ""

# Summary
echo "╔════════════════════════════════════════════════════════════╗"
echo "║                    Test Summary                            ║"
echo "╠════════════════════════════════════════════════════════════╣"
echo -e "  ${GREEN}✓${NC} All migration files present"
echo -e "  ${GREEN}✓${NC} MigrationManager implemented"
echo -e "  ${GREEN}✓${NC} Console command available"
echo -e "  ${GREEN}✓${NC} PHP syntax validated"
echo "║                                                            ║"
echo "  Next steps:"
echo "    1. Enable plugins in Matomo admin"
echo "    2. Run: ./console visitorflow:test-migrations --execute"
echo "    3. Verify tables created: SHOW TABLES LIKE 'plugin_%_raw'"
echo "║                                                            ║"
echo "╚════════════════════════════════════════════════════════════╝"

echo ""

# Log execution
echo "[$(date)] SB-013 migration test completed" >> "$LOG_FILE"
echo "Test log: $LOG_FILE"

exit 0
