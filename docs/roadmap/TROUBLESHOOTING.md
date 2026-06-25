# Troubleshooting Guide

## Common Issues & Solutions

### 1. Plugin Not Appearing in Menu

**Symptoms:**
- Plugins are installed and active
- Menu items under Visitors not showing new plugins

**Diagnosis:**
```bash
php console plugin:info VisitorFlowIntelligence
# Check if Status = Active
grep "Menu.Reporting.addItems" plugins/VisitorFlowIntelligence/VisitorFlowIntelligence.php
```

**Solutions:**

1. **Clear cache:**
   ```bash
   rm -rf matomo/tmp/cache/*
   ```

2. **Verify menu registration:**
   ```bash
   grep -A5 "function.*addReportMenuItem" plugins/VisitorFlowIntelligence/VisitorFlowIntelligence.php
   ```

3. **Check permissions:**
   ```bash
   ls -la plugins/VisitorFlowIntelligence/
   # Should be readable by www-data
   ```

4. **Restart Matomo cache:**
   ```bash
   php console cache:clear
   ```

### 2. API Endpoints Returning 404 or Empty

**Symptoms:**
- Curl returns 404 or JSON with no data
- API responses lack `meta` object

**Diagnosis:**
```bash
# Check if plugins are active
php console plugin:list | grep Active

# Test endpoint manually
curl -v "http://matomo.local/index.php?module=API&method=VisitorFlowIntelligence.getTopPaths&idSite=1&period=day&date=yesterday"
```

**Solutions:**

1. **Verify API method exists:**
   ```bash
   grep "public function getTopPaths" plugins/VisitorFlowIntelligence/API.php
   ```

2. **Check for PHP syntax errors:**
   ```bash
   php -l plugins/VisitorFlowIntelligence/API.php
   ```

3. **Look for runtime errors:**
   ```bash
   tail -100 matomo/tmp/logs/matomo.log | grep -i error
   ```

4. **Verify access token is valid:**
   ```bash
   # Test with valid token_auth parameter
   curl "...&token_auth=YOUR_VALID_TOKEN"
   ```

5. **Check site exists:**
   ```bash
   # idSite=1 must exist in Matomo
   php console site:list
   ```

### 3. "Undefined Type" Errors in Code Editor

**Symptoms:**
- IDE shows red squiggles for Piwik classes
- Autocomplete not working for Matomo APIs
- Code linting shows "Undefined type 'Piwik\\*'"

**Diagnosis:**
This is expected if Matomo Core is not in workspace or indexed.

**Solutions:**

1. **Install Matomo locally for development:**
   ```bash
   composer install matomo/core
   ```

2. **Use Matomo Docker image for reference:**
   ```bash
   docker run -it matomo:latest bash
   # Inspect /var/www/html/core/
   ```

3. **Ignore false positives:**
   - These warnings are safe; code runs fine in Matomo
   - Focus on actual PHP syntax errors (php -l)

4. **Add type stubs (optional):**
   - Create local type definitions for frequently used Piwik classes
   - Or submit to Piwik stub repository

### 4. Retention Job Not Running

**Symptoms:**
- No log entries for retention jobs
- Data older than 30 days still present
- Manual commands work but scheduled job doesn't

**Diagnosis:**
```bash
# Check if scheduled tasks are registered
grep "ScheduledTaskScheduler" matomo/tmp/logs/matomo.log

# Check cron job status (if using system cron)
crontab -l | grep matomo

# Verify Matomo archive process runs
grep "ArchiveProcessor\|archive" matomo/tmp/logs/matomo.log | tail -20
```

**Solutions:**

1. **Trigger archive process manually:**
   ```bash
   php console core:archive --force-all-websites
   ```

2. **Verify task scheduler hook:**
   ```bash
   grep "scheduleRetentionTask" plugins/GeoPrecision/GeoPrecision.php
   ```

3. **Check if tables exist:**
   ```bash
   # Retention jobs skip missing tables silently
   mysql -u matomo_user -p matomo << EOF
   SHOW TABLES LIKE '%plugin_geoprecision%';
   SHOW TABLES LIKE '%plugin_deviceintelligence%';
   SHOW TABLES LIKE '%plugin_visitorflow%';
   EOF
   ```

4. **Manually test dry-run:**
   ```bash
   php console geoprecision:test-retention
   # Should show records to delete
   ```

### 5. High CPU/Memory After Plugin Activation

**Symptoms:**
- Server load increases significantly
- Memory usage jumps to high %
- Web server becomes unresponsive

**Diagnosis:**
```bash
# Check Matomo logs for slow queries
grep "slow_query\|SLOW SQL" matomo/tmp/logs/matomo.log

# Monitor current queries
mysql -u matomo_user -p matomo -e "SHOW PROCESSLIST;"

# Check plugin code for N+1 queries
grep -n "foreach.*Db::" plugins/VisitorFlowIntelligence/Infrastructure/*
```

**Solutions:**

1. **Disable problematic plugin temporarily:**
   ```bash
   php console plugin:deactivate VisitorFlowIntelligence
   # Monitor if CPU drops back to normal
   ```

2. **Optimize queries (if familiar with SQL):**
   - Check for missing indexes
   - Look for full table scans
   - Add JOIN conditions

3. **Increase server resources:**
   - More RAM for MySQL buffer pool
   - More CPU cores for parallel processing
   - SSD for faster disk I/O

4. **Implement query caching:**
   - Add Redis/Memcached for result caching
   - Cache API responses in Matomo

### 6. "Access Denied" or Permission Errors

**Symptoms:**
- HTTP 403 when accessing reports
- "User does not have view access" error
- Can't run CLI commands

**Diagnosis:**
```bash
# Check user permissions in Matomo
# Navigate to Administration > Users

# Check file ownership
ls -la plugins/VisitorFlowIntelligence/
# Should be owned by www-data (or your web user)
```

**Solutions:**

1. **Fix file ownership:**
   ```bash
   chown -R www-data:www-data plugins/VisitorFlowIntelligence
   chown -R www-data:www-data plugins/GeoPrecision
   chown -R www-data:www-data plugins/DeviceIntelligence
   ```

2. **Verify user has view access to site:**
   - Admin > Users > Edit User
   - Ensure user has "View" permission for idSite=1

3. **Check token_auth is valid:**
   ```bash
   # Test API with token
   curl "...&token_auth=YOUR_TOKEN"
   ```

### 7. Database Corruption or Data Loss

**Symptoms:**
- Reports return zero records unexpectedly
- Error message: "Integrity constraint violated"
- Queries fail with "table doesn't exist"

**Diagnosis:**
```bash
# Check table integrity
mysql -u matomo_user -p matomo << EOF
CHECK TABLE log_visit;
CHECK TABLE log_action;
EOF

# Check for missing columns
DESC log_visit | grep -E "country|region|city"
```

**Solutions:**

1. **Repair corrupted table:**
   ```bash
   mysql -u root -p << EOF
   REPAIR TABLE matomo.log_visit;
   OPTIMIZE TABLE matomo.log_visit;
   EOF
   ```

2. **Restore from backup:**
   ```bash
   # Stop Matomo
   service php-fpm stop
   
   # Restore database
   mysql -u root -p matomo < /backup/matomo_YYYY-MM-DD.sql
   
   # Restart
   service php-fpm start
   ```

3. **Verify backup is valid:**
   ```bash
   # Test restore to separate DB first
   mysql -u root -p -e "CREATE DATABASE matomo_test;"
   mysql -u root -p matomo_test < /backup/matomo_latest.sql
   ```

## Performance Tuning

### Slow API Responses

**Symptoms:** API endpoints take > 2 seconds

**Solutions:**
```bash
# 1. Enable MySQL query log
mysql -u root -p << EOF
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;
EOF

# 2. Check slow query log
tail -50 /var/log/mysql/slow.log | grep -i visitorflow

# 3. Check if tables have indexes
SHOW INDEXES FROM piwik_log_visit;
SHOW INDEXES FROM piwik_log_action;

# 4. Monitor query time
time php console api:get --method=VisitorFlowIntelligence.getTopPaths
```

### High Memory Usage

**Solutions:**
```bash
# 1. Limit query results
# Modify API methods to use LIMIT clauses

# 2. Implement pagination
# Add offset/limit parameters to API

# 3. Archive pre-aggregated data
# Implement Archiver for daily/weekly rollups

# 4. Cache results
# Use Matomo's built-in cache/Redis
```

## Getting Help

### Logs to Collect

Before contacting support, gather:

```bash
# 1. Recent error logs (last 50 lines)
tail -50 matomo/tmp/logs/matomo.log > error_logs.txt

# 2. Plugin info
php console plugin:info VisitorFlowIntelligence >> error_logs.txt
php console plugin:info GeoPrecision >> error_logs.txt
php console plugin:info DeviceIntelligence >> error_logs.txt

# 3. System info
php -v >> error_logs.txt
mysql --version >> error_logs.txt
uname -a >> error_logs.txt

# 4. Database stats
mysql -u matomo_user -p matomo -e "SHOW TABLE STATUS;" >> error_logs.txt
```

### Support Channels

- **Documentation**: `/home/dev/projects/matomo/docs/roadmap/`
- **GitHub Issues**: [Repository URL]
- **Email Support**: [Support Email]
- **Slack/Discord**: [Community Channel]

### Reporting Bugs

Include:
1. Exact error message (screenshot if UI error)
2. Steps to reproduce
3. Expected vs actual behavior
4. Logs (see above)
5. Matomo version
6. PHP version
7. Database type/version
