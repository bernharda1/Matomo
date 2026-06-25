# Health Check Script

Automated validation of plugin installation and functionality.

## Quick Start

```bash
cd /path/to/matomo
bash docs/roadmap/health_check.sh
```

## health_check.sh

```bash
#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Initialize counters
PASSED=0
FAILED=0

# Test functions
test_plugin_active() {
    local plugin=$1
    if php console plugin:list | grep -q "$plugin"; then
        if php console plugin:list | grep "$plugin" | grep -q "Active"; then
            echo -e "${GREEN}✓${NC} $plugin is active"
            ((PASSED++))
        else
            echo -e "${RED}✗${NC} $plugin is installed but not active"
            ((FAILED++))
        fi
    else
        echo -e "${RED}✗${NC} $plugin is not installed"
        ((FAILED++))
    fi
}

test_api_endpoint() {
    local method=$1
    local expected_code=$2
    
    response=$(curl -s -o /dev/null -w "%{http_code}" \
        "http://localhost/index.php?module=API&method=${method}&idSite=1&period=day&date=yesterday&format=json" \
        2>/dev/null)
    
    if [ "$response" == "$expected_code" ]; then
        echo -e "${GREEN}✓${NC} API $method returns $response"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} API $method returns $response (expected $expected_code)"
        ((FAILED++))
    fi
}

test_menu_item() {
    local plugin=$1
    local menu_text=$2
    
    # This would require UI testing; placeholder for now
    echo -e "${YELLOW}⚠${NC} Menu item check requires manual verification: $menu_text"
}

test_database_connectivity() {
    # Check if Matomo can connect to DB
    if php console core:version &>/dev/null; then
        echo -e "${GREEN}✓${NC} Database connectivity OK"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} Database connectivity FAILED"
        ((FAILED++))
    fi
}

test_file_permissions() {
    local plugin_dir=$1
    if [ -r "$plugin_dir" ] && [ -x "$plugin_dir" ]; then
        echo -e "${GREEN}✓${NC} File permissions OK: $plugin_dir"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} File permissions FAILED: $plugin_dir"
        ((FAILED++))
    fi
}

test_disk_space() {
    available=$(df /path/to/matomo | awk 'NR==2 {print $4}')
    if [ "$available" -gt 1048576 ]; then # 1GB
        echo -e "${GREEN}✓${NC} Disk space available: ${available}K"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} Low disk space: ${available}K (< 1GB)"
        ((FAILED++))
    fi
}

test_logs_accessible() {
    if [ -r "tmp/logs/matomo.log" ]; then
        echo -e "${GREEN}✓${NC} Log files accessible"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} Log files not accessible"
        ((FAILED++))
    fi
}

test_no_recent_errors() {
    error_count=$(grep -i error tmp/logs/matomo.log | tail -100 | wc -l)
    if [ "$error_count" -lt 5 ]; then
        echo -e "${GREEN}✓${NC} No recent critical errors (found $error_count warnings)"
        ((PASSED++))
    else
        echo -e "${YELLOW}⚠${NC} Multiple errors in recent logs ($error_count found)"
        ((FAILED++))
    fi
}

# Main execution
echo "======================================"
echo "Matomo Plugin Suite Health Check"
echo "======================================"
echo ""

echo "1. Plugin Installation Status"
test_plugin_active "VisitorFlowIntelligence"
test_plugin_active "GeoPrecision"
test_plugin_active "DeviceIntelligence"
echo ""

echo "2. API Endpoint Availability"
test_api_endpoint "VisitorFlowIntelligence.getTopPaths" "200"
test_api_endpoint "GeoPrecision.getConfidenceSummary" "200"
test_api_endpoint "DeviceIntelligence.getQualitySummary" "200"
echo ""

echo "3. System & Environment"
test_database_connectivity
test_file_permissions "plugins/VisitorFlowIntelligence"
test_file_permissions "plugins/GeoPrecision"
test_file_permissions "plugins/DeviceIntelligence"
test_disk_space
test_logs_accessible
test_no_recent_errors
echo ""

echo "======================================"
echo "Results: ${GREEN}$PASSED passed${NC}, ${RED}$FAILED failed${NC}"
echo "======================================"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}All checks passed! ✓${NC}"
    exit 0
else
    echo -e "${RED}Some checks failed. Review above.${NC}"
    exit 1
fi
```

## Manual Health Check Procedure

If automated script not available, run these commands manually:

### 1. Plugin Status

```bash
cd /path/to/matomo
php console plugin:list | grep -E "VisitorFlowIntelligence|GeoPrecision|DeviceIntelligence"
```

Expected output:
```
VisitorFlowIntelligence  v0.1.0 Active
GeoPrecision             v0.1.0 Active
DeviceIntelligence       v0.1.0 Active
```

### 2. API Functionality

```bash
# Test each API endpoint
curl -s "http://localhost/index.php?module=API&method=VisitorFlowIntelligence.getTopPaths&idSite=1&period=day&date=yesterday&format=json" | jq '.'
curl -s "http://localhost/index.php?module=API&method=GeoPrecision.getConfidenceSummary&idSite=1&period=day&date=yesterday&format=json" | jq '.'
curl -s "http://localhost/index.php?module=API&method=DeviceIntelligence.getQualitySummary&idSite=1&period=day&date=yesterday&format=json" | jq '.'
```

Expected: Each returns JSON with `meta` object and data arrays.

### 3. Database

```bash
mysql -u matomo_user -p matomo << EOF
SELECT COUNT(*) AS visit_count FROM log_visit WHERE DATE(server_time) = CURDATE();
SELECT COUNT(*) AS action_count FROM log_action LIMIT 1;
EOF
```

Expected: Non-zero counts indicate data is present.

### 4. Retention Jobs

```bash
# Dry-run to verify functionality
php console visitorflow:test-retention
php console deviceintelligence:test-retention
php console geoprecision:test-retention
```

Expected: All report "DRY-RUN MODE" with record counts.

### 5. Logs

```bash
# Check for errors in last 24 hours
grep -i error matomo/tmp/logs/matomo.log | tail -20

# Check for plugin-specific messages
grep -E "VisitorFlowIntelligence|GeoPrecision|DeviceIntelligence" matomo/tmp/logs/matomo.log | tail -20
```

Expected: Minimal or no errors related to plugins.

## Alerting Rules

Set up monitoring alerts for:

| Metric | Threshold | Action |
|--------|-----------|--------|
| API Response Time | > 2000ms | Page team |
| Error Rate | > 5 errors/hour | Page team |
| Disk Space | < 1GB free | Page DevOps |
| DB Connectivity | Failed for 5 min | Page DevOps |
| Retention Job Failed | Any execution error | Page team |

## Dashboard Widgets (Matomo UI)

For ongoing monitoring, consider adding custom dashboard widgets:

- Plugin Health Status
- API Response Time Trend
- Database Table Sizes
- Retention Job Last Run
- Error Rate (7-day)
