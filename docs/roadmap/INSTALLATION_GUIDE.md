# Installation Guide - Matomo Plugin Suite

## Overview

This guide covers installation and configuration of three custom Matomo plugins:
1. **VisitorFlowIntelligence** - Visitor path and drop-off analysis
2. **GeoPrecision** - Geographic data quality and confidence scoring
3. **DeviceIntelligence** - Device data quality and client hints processing

## Prerequisites

- Matomo 5.0.0 or higher
- MySQL 5.7 or higher
- PHP 7.4 or higher
- Write access to `plugins/` directory
- Write access to database

## Installation Steps

### Step 1: Copy Plugin Files

```bash
# From workspace or git repository
cp -r plugins/VisitorFlowIntelligence /path/to/matomo/plugins/
cp -r plugins/GeoPrecision /path/to/matomo/plugins/
cp -r plugins/DeviceIntelligence /path/to/matomo/plugins/

# Verify directory structure
ls -la /path/to/matomo/plugins/VisitorFlowIntelligence/
ls -la /path/to/matomo/plugins/GeoPrecision/
ls -la /path/to/matomo/plugins/DeviceIntelligence/
```

### Step 2: Enable Plugins in Matomo UI

Navigate to **Administration > Plugins > Manage** and:

1. Click **Enable** for `VisitorFlowIntelligence`
2. Click **Enable** for `GeoPrecision`
3. Click **Enable** for `DeviceIntelligence`

Alternatively, via command line:

```bash
cd /path/to/matomo

php console plugin:activate VisitorFlowIntelligence
php console plugin:activate GeoPrecision
php console plugin:activate DeviceIntelligence
```

### Step 3: Verify Plugin Registration

```bash
cd /path/to/matomo

# Check plugin info
php console plugin:info VisitorFlowIntelligence
php console plugin:info GeoPrecision
php console plugin:info DeviceIntelligence
```

Expected output:
```
Plugin: VisitorFlowIntelligence
Version: 0.1.0
Requires: matomo >= 5.0.0
Status: Active
```

### Step 4: Create Database Tables (Future)

When plugin data tables are implemented, run migrations:

```bash
cd /path/to/matomo

# Execute pending migrations
php console plugin:migrate VisitorFlowIntelligence
php console plugin:migrate GeoPrecision
php console plugin:migrate DeviceIntelligence
```

**Note:** Current MVP does not create custom tables; uses existing `log_visit` and `log_link_visit_action` tables.

### Step 5: Verify Menu Registration

Log in to Matomo UI and navigate to **Visitors** menu. You should see three new menu items:

- **Visitor Flow** (position 45) - VisitorFlowIntelligence
- **Device Intelligence** (position 46) - DeviceIntelligence
- **Geo Confidence** (position 47) - GeoPrecision

### Step 6: Test API Endpoints

```bash
# Test VisitorFlowIntelligence API
curl "https://matomo.example.com/index.php?module=API&method=VisitorFlowIntelligence.getTopPaths&idSite=1&period=day&date=yesterday&token_auth=YOUR_TOKEN"

# Test GeoPrecision API
curl "https://matomo.example.com/index.php?module=API&method=GeoPrecision.getConfidenceSummary&idSite=1&period=day&date=yesterday&token_auth=YOUR_TOKEN"

# Test DeviceIntelligence API
curl "https://matomo.example.com/index.php?module=API&method=DeviceIntelligence.getQualitySummary&idSite=1&period=day&date=yesterday&token_auth=YOUR_TOKEN"
```

Expected response (200 OK with JSON):
```json
{
  "meta": {
    "idSite": 1,
    "period": "day",
    "date": "2026-06-24",
    "generatedAt": "2026-06-25T10:30:00+00:00",
    "totalVisits": 42
  },
  "paths": [/* data */],
  "transitions": [/* data */],
  "dropoffs": [/* data */]
}
```

## Configuration

### Retention Policy (Optional)

Default retention periods:

```
Raw data:       30 days
Aggregate data: 365 days
```

To override, add to `config/config.php`:

```php
'VisitorFlowIntelligence' => [
    'raw_retention_days' => 30,
    'aggregate_retention_days' => 365,
],
'GeoPrecision' => [
    'raw_retention_days' => 30,
    'aggregate_retention_days' => 365,
],
'DeviceIntelligence' => [
    'raw_retention_days' => 30,
    'aggregate_retention_days' => 365,
],
```

### Consent Configuration (GeoPrecision)

Consent for precise geo data is required. Set in environment or config:

```bash
export GEO_PRECISION_REQUIRE_CONSENT=1
```

## Verification Checklist

- [ ] Plugin files copied to `plugins/` directory
- [ ] Plugins activated in Matomo UI
- [ ] Plugin info command returns status: Active
- [ ] Menu items visible under Visitors
- [ ] API endpoints respond with 200 OK
- [ ] Test data queries return non-empty results
- [ ] Scheduled retention tasks registered
- [ ] Log output shows plugin initialization (matomo.log)

## Troubleshooting

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for common installation issues.

## Next Steps

1. Run [HEALTH_CHECK.md](HEALTH_CHECK.md) to validate installation
2. Review [RUNBOOK.md](RUNBOOK.md) for operational procedures
3. Check [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) for production readiness
