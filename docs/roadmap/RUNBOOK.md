# Runbook - Matomo Plugin Suite Operations

## Quick Reference

| Procedure | Command | Time |
|-----------|---------|------|
| Check plugin status | `php console plugin:info {Plugin}` | 1 min |
| Test retention (dry-run) | `php console {plugin}:test-retention` | 2 min |
| Execute retention | `php console {plugin}:test-retention --execute` | 5-30 min |
| View logs | `tail -f matomo/tmp/logs/matomo.log` | Live |
| Disable plugin | `php console plugin:deactivate {Plugin}` | 1 min |
| Rollback | See [Rollback Procedures](#rollback-procedures) | 5-15 min |

## Daily Operations

### Morning Health Check (8 AM)

```bash
#!/bin/bash
cd /path/to/matomo

echo "=== Plugin Status ==="
php console plugin:info VisitorFlowIntelligence
php console plugin:info GeoPrecision
php console plugin:info DeviceIntelligence

echo "=== Recent Errors ==="
grep -i error matomo/tmp/logs/matomo.log | tail -20

echo "=== Database Connectivity ==="
php console config:get | grep -A5 database

echo "=== Disk Space ==="
df -h matomo/tmp matomo/data
```

### Evening Retention Check (6 PM)

```bash
#!/bin/bash
cd /path/to/matomo

echo "=== Dry-Run Retention Test ==="
php console visitorflow:test-retention
php console deviceintelligence:test-retention
php console geoprecision:test-retention

echo "=== Check for Warnings ==="
grep -i "warning\|error" matomo/tmp/logs/matomo.log | tail -50
```

## Standard Procedures

### 1. Enable/Disable Plugins

**Enable:**
```bash
cd /path/to/matomo
php console plugin:activate VisitorFlowIntelligence
php console plugin:activate GeoPrecision
php console plugin:activate DeviceIntelligence
```

**Disable (if needed):**
```bash
php console plugin:deactivate VisitorFlowIntelligence
```

### 2. View Plugin Logs

```bash
# All plugin activity
grep -i "plugin" matomo/tmp/logs/matomo.log | tail -100

# Specific plugin
grep "VisitorFlowIntelligence" matomo/tmp/logs/matomo.log | tail -50

# Follow live
tail -f matomo/tmp/logs/matomo.log | grep -E "GeoPrecision|DeviceIntelligence|VisitorFlowIntelligence"
```

### 3. Test API Endpoints

```bash
#!/bin/bash
SITE_ID=1
TOKEN="your_auth_token"
MATOMO_URL="https://matomo.example.com"

echo "=== VisitorFlowIntelligence ==="
curl "${MATOMO_URL}/index.php?module=API&method=VisitorFlowIntelligence.getTopPaths&idSite=${SITE_ID}&period=day&date=yesterday&token_auth=${TOKEN}&format=json" | jq '.meta'

echo "=== GeoPrecision ==="
curl "${MATOMO_URL}/index.php?module=API&method=GeoPrecision.getConfidenceSummary&idSite=${SITE_ID}&period=day&date=yesterday&token_auth=${TOKEN}&format=json" | jq '.meta'

echo "=== DeviceIntelligence ==="
curl "${MATOMO_URL}/index.php?module=API&method=DeviceIntelligence.getQualitySummary&idSite=${SITE_ID}&period=day&date=yesterday&token_auth=${TOKEN}&format=json" | jq '.meta'
```

### 4. Run Scheduled Retention Tasks

**Manual Dry-Run (Safe Testing):**
```bash
cd /path/to/matomo

php console visitorflow:test-retention
php console deviceintelligence:test-retention
php console geoprecision:test-retention
```

**Expected Output:**
```
[DRY-RUN MODE]
No data will be deleted. Re-run with --execute to actually delete.

Raw data records to delete: 12543
Aggregate records to delete: 287
Total records: 12830
```

**Execute Retention (Live Delete):**
```bash
php console visitorflow:test-retention --execute
```

**⚠️ WARNING:** This actually deletes data. Ensure backups are current before executing.

### 5. Database Health Check

```bash
#!/bin/bash
MATOMO_DB="matomo"
MATOMO_USER="matomo_user"

echo "=== Table Counts ==="
mysql -u ${MATOMO_USER} -p ${MATOMO_DB} << EOF
SELECT 'log_visit' AS table_name, COUNT(*) AS row_count FROM log_visit
UNION ALL
SELECT 'log_action', COUNT(*) FROM log_action
UNION ALL
SELECT 'log_link_visit_action', COUNT(*) FROM log_link_visit_action;
EOF

echo "=== Recent Data ==="
mysql -u ${MATOMO_USER} -p ${MATOMO_DB} << EOF
SELECT MAX(server_time) AS latest_visit FROM log_visit;
SELECT COUNT(*) AS visits_today FROM log_visit WHERE DATE(server_time) = CURDATE();
EOF
```

## Rollback Procedures

### Scenario 1: Plugin Causes Errors After Activation

**Symptoms:**
- "Call to undefined method" in plugin code
- HTTP 500 on report pages
- Missing menu items

**Recovery:**
```bash
cd /path/to/matomo

# Disable problematic plugin
php console plugin:deactivate VisitorFlowIntelligence

# Clear cache
rm -rf matomo/tmp/cache/*

# Verify UI recovers
# Navigate to Visitors menu - plugin menu item should disappear

# Check logs
grep -i error matomo/tmp/logs/matomo.log | tail -20
```

### Scenario 2: Retention Job Deletes Too Much Data

**Symptoms:**
- Reports show zero records unexpectedly
- API returns empty arrays
- Dashboard widgets empty

**Recovery:**
```bash
# STOP: Do not run any more deletion jobs!
# Restore from backup

# Check backup status
ls -lh /path/to/backups/mysql/

# Restore database
mysql -u root -p < /path/to/backups/mysql/matomo_2026-06-25.sql

# Verify data restored
mysql -u matomo_user -p matomo << EOF
SELECT COUNT(*) FROM log_visit WHERE server_time > DATE_SUB(NOW(), INTERVAL 30 DAY);
EOF

# Re-enable plugins
php console plugin:activate VisitorFlowIntelligence
php console plugin:activate GeoPrecision
php console plugin:activate DeviceIntelligence
```

### Scenario 3: Plugin Files Missing or Corrupted

**Symptoms:**
- "Plugin directory not found" errors
- Syntax errors in plugin code

**Recovery:**
```bash
# Backup current (corrupted) version
tar czf /backup/plugins_corrupted_$(date +%s).tar.gz /path/to/matomo/plugins/VisitorFlowIntelligence

# Re-copy clean version from repository/source
cp -r /path/to/source/plugins/VisitorFlowIntelligence /path/to/matomo/plugins/

# Verify installation
php console plugin:info VisitorFlowIntelligence

# Clear cache
rm -rf matomo/tmp/cache/*
```

## Monitoring & Alerts

### Log Analysis Script

```bash
#!/bin/bash
# Check for plugin errors in last 24 hours

echo "=== Plugin Errors (Last 24h) ==="
find matomo/tmp/logs -name "*.log" -mtime -1 -exec grep -l "error" {} \;

echo "=== Error Details ==="
grep "error" matomo/tmp/logs/matomo.log | \
  grep -E "VisitorFlowIntelligence|GeoPrecision|DeviceIntelligence" | \
  tail -20

echo "=== Retention Job Status ==="
grep "retention" matomo/tmp/logs/matomo.log | \
  grep -E "EXECUTED|DRY-RUN" | \
  tail -10
```

### Performance Metrics

```bash
#!/bin/bash
# Measure API response times

MATOMO_URL="https://matomo.example.com"
TOKEN="your_auth_token"

echo "=== VisitorFlowIntelligence API Response Time ==="
time curl -s "${MATOMO_URL}/index.php?module=API&method=VisitorFlowIntelligence.getTopPaths&idSite=1&period=day&date=yesterday&token_auth=${TOKEN}" > /dev/null

echo "=== GeoPrecision API Response Time ==="
time curl -s "${MATOMO_URL}/index.php?module=API&method=GeoPrecision.getConfidenceSummary&idSite=1&period=day&date=yesterday&token_auth=${TOKEN}" > /dev/null

echo "=== DeviceIntelligence API Response Time ==="
time curl -s "${MATOMO_URL}/index.php?module=API&method=DeviceIntelligence.getQualitySummary&idSite=1&period=day&date=yesterday&token_auth=${TOKEN}" > /dev/null
```

## Emergency Contacts & Resources

- **Plugin Documentation**: `/home/dev/projects/matomo/docs/roadmap/`
- **Matomo Core Docs**: https://matomo.org/docs/
- **Database Backups**: `/path/to/backups/mysql/`
- **Log Files**: `/path/to/matomo/tmp/logs/`

## Post-Incident Review Checklist

After any outage or error:

- [ ] Document incident time, duration, impact
- [ ] Review logs for root cause
- [ ] Check if this was preventable
- [ ] Update monitoring/alerting if needed
- [ ] Update this runbook with lessons learned
- [ ] Notify team of changes
