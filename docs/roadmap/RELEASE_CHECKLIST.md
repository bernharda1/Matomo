# Release Checklist v1.0

## Pre-Release (1 Week Before)

### Code Quality

- [ ] All PHP files pass syntax check
  ```bash
  find plugins/ -name "*.php" -exec php -l {} \;
  ```

- [ ] Type hints are complete (no mixed/void mismatches)
  ```bash
  grep -r "function.*{" plugins/ | grep -v ":\|type"
  ```

- [ ] No debug code left behind
  ```bash
  grep -r "var_dump\|print_r\|dd(" plugins/
  grep -r "TODO\|FIXME\|XXX" plugins/ || echo "Clean"
  ```

- [ ] Translation keys are consistent
  ```bash
  grep -r "translate" plugins/*/templates/*.twig | wc -l
  grep -r "'.*'" plugins/*/lang/en.json | wc -l
  ```

### Documentation

- [ ] README.md complete for all 3 plugins
  - [ ] VisitorFlowIntelligence
  - [ ] GeoPrecision
  - [ ] DeviceIntelligence

- [ ] API documentation exists
  - [ ] Endpoint URLs documented
  - [ ] Request/response examples provided
  - [ ] Error codes documented

- [ ] Configuration guide complete
  - [ ] All options listed
  - [ ] Default values noted
  - [ ] Security implications noted

### Testing

- [ ] Unit tests pass (if implemented)
  ```bash
  php console tests:run --testsuite unit
  ```

- [ ] API endpoints respond correctly
  ```bash
  php console tests:run --testsuite api-plugins
  ```

- [ ] UI renders without errors
  - [ ] VisitorFlowIntelligence report loads
  - [ ] GeoPrecision report loads
  - [ ] DeviceIntelligence report loads

- [ ] Dry-run retention jobs complete without errors
  ```bash
  php console visitorflow:test-retention
  php console deviceintelligence:test-retention
  php console geoprecision:test-retention
  ```

### Database

- [ ] Backup strategy confirmed
  - [ ] Daily backups enabled
  - [ ] Test restore successful
  - [ ] Backup location accessible

- [ ] Retention policy reviewed
  - [ ] Raw data retention: 30 days ✓
  - [ ] Aggregate retention: 365 days ✓
  - [ ] No unintended data loss expected

## Release Day (Production Deployment)

### Pre-Deployment

- [ ] Maintenance mode enabled
  ```bash
  php console config:set --section=General --key=maintenance_mode --value=1
  ```

- [ ] Current database backup taken
  ```bash
  mysqldump -u matomo_user -p matomo > /backup/matomo_pre_release.sql
  ```

- [ ] Team notified of deployment window

### Deployment Steps

- [ ] VisitorFlowIntelligence plugin copied
  ```bash
  cp -r plugins/VisitorFlowIntelligence /path/to/matomo/plugins/
  ```

- [ ] GeoPrecision plugin copied
  ```bash
  cp -r plugins/GeoPrecision /path/to/matomo/plugins/
  ```

- [ ] DeviceIntelligence plugin copied
  ```bash
  cp -r plugins/DeviceIntelligence /path/to/matomo/plugins/
  ```

- [ ] Plugin permissions correct
  ```bash
  chown -R www-data:www-data /path/to/matomo/plugins/VisitorFlowIntelligence
  chown -R www-data:www-data /path/to/matomo/plugins/GeoPrecision
  chown -R www-data:www-data /path/to/matomo/plugins/DeviceIntelligence
  ```

- [ ] Plugins activated
  ```bash
  php console plugin:activate VisitorFlowIntelligence
  php console plugin:activate GeoPrecision
  php console plugin:activate DeviceIntelligence
  ```

- [ ] Cache cleared
  ```bash
  rm -rf /path/to/matomo/tmp/cache/*
  ```

- [ ] Maintenance mode disabled
  ```bash
  php console config:set --section=General --key=maintenance_mode --value=0
  ```

### Post-Deployment Verification

- [ ] Plugins active and registered
  ```bash
  php console plugin:info VisitorFlowIntelligence
  php console plugin:info GeoPrecision
  php console plugin:info DeviceIntelligence
  ```

- [ ] Menu items visible in UI
  - [ ] Visitor Flow in Visitors menu
  - [ ] Device Intelligence in Visitors menu
  - [ ] Geo Confidence in Visitors menu

- [ ] API endpoints respond (all 200 OK)
  ```bash
  curl "https://matomo.example.com/index.php?module=API&method=VisitorFlowIntelligence.getTopPaths&idSite=1&period=day&date=yesterday&token_auth=TOKEN"
  curl "https://matomo.example.com/index.php?module=API&method=GeoPrecision.getConfidenceSummary&idSite=1&period=day&date=yesterday&token_auth=TOKEN"
  curl "https://matomo.example.com/index.php?module=API&method=DeviceIntelligence.getQualitySummary&idSite=1&period=day&date=yesterday&token_auth=TOKEN"
  ```

- [ ] No errors in logs
  ```bash
  grep -i "error\|fatal" /path/to/matomo/tmp/logs/matomo.log | wc -l
  # Expected: 0
  ```

- [ ] Reports load without errors
  - [ ] VisitorFlowIntelligence report displays data
  - [ ] GeoPrecision report displays data
  - [ ] DeviceIntelligence report displays data

- [ ] Scheduled tasks registered
  ```bash
  grep "RetentionTask\|ScheduledTaskScheduler" /path/to/matomo/tmp/logs/matomo.log
  ```

## Post-Release (1 Week After)

### Stability Monitoring

- [ ] No error spikes in logs
  ```bash
  grep -i "error" /path/to/matomo/tmp/logs/matomo.log | wc -l
  # Compare to baseline
  ```

- [ ] Performance metrics acceptable
  - [ ] API response time < 2 seconds
  - [ ] DB query time < 1 second
  - [ ] CPU usage normal

- [ ] Database size growth within expected range
  ```bash
  du -sh /path/to/matomo/data
  du -sh /path/to/matomo/tmp
  ```

- [ ] Retention jobs run successfully
  ```bash
  grep "retention.*EXECUTED" /path/to/matomo/tmp/logs/matomo.log | tail -5
  ```

### User Acceptance

- [ ] Stakeholders confirm reports work as expected
- [ ] No user-reported bugs in first week
- [ ] Feedback collected for improvements

## Rollback Decision Criteria

Rollback immediately if:

- [ ] Critical data loss detected
- [ ] API endpoints consistently return 500 errors
- [ ] Retention job deletes unexpected volume of data
- [ ] Database performance degrades by > 50%
- [ ] Plugin blocks other Matomo functionality

### Rollback Procedure

```bash
cd /path/to/matomo

# 1. Disable plugins
php console plugin:deactivate VisitorFlowIntelligence
php console plugin:deactivate GeoPrecision
php console plugin:deactivate DeviceIntelligence

# 2. Remove plugin files
rm -rf plugins/VisitorFlowIntelligence
rm -rf plugins/GeoPrecision
rm -rf plugins/DeviceIntelligence

# 3. Restore database (if data issues occurred)
mysql -u matomo_user -p matomo < /backup/matomo_pre_release.sql

# 4. Clear cache
rm -rf tmp/cache/*

# 5. Verify Matomo works
# Navigate to UI and confirm no errors
```

## Release Notes Template

```markdown
# Release v1.0 - Plugin Suite

## What's New

### VisitorFlowIntelligence
- Top paths and transitions analysis
- Drop-off detection by step
- Configurable depth and limit

### GeoPrecision
- Geo confidence scoring (high/medium/low)
- Consent-gated precision levels
- Confidence distribution reporting

### DeviceIntelligence
- Device data quality metrics
- Client Hints (uadata) processing
- Unknown-rate monitoring

## Security & Privacy

- Consent-gated precise geo data
- 30-day retention for raw event data
- Automatic data purge via scheduled jobs

## Breaking Changes

None.

## Migration Guide

See INSTALLATION_GUIDE.md

## Known Limitations

- Segment support not yet implemented (MVP)
- Custom data tables not yet created (MVP)
- No Archiver pre-aggregation (MVP)

## Support

- Documentation: /docs/roadmap/
- Issues: [GitHub/GitLab]
- Contact: [Team Email]
```

## Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Developer | | | |
| QA Lead | | | |
| DevOps | | | |
| Product Owner | | | |

---

**Release Date:** [YYYY-MM-DD]  
**Version:** 1.0  
**Status:** ☐ Ready for Production / ☐ Hold / ☐ Rollback
