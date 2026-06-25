# SB-011: Data Retention & Cleanup Jobs

## Overview

Retention jobs automatically purge old data according to policy, keeping the database lean and compliant with privacy regulations.

## Architecture

### Retention Policy

| Data Type | Retention Period | Rationale |
|-----------|------------------|-----------|
| Raw Event Data | 30 days | Visit-level tracking events are personal data; short retention minimizes exposure. |
| Aggregate Data | 365 days | Aggregates are anonymized; longer retention supports long-term trend analysis. |

### Implementation

Each plugin provides:
1. **RetentionManager** - Implements purge logic with dry-run support
2. **RetentionTask** - Scheduled task (Daily @ 3 AM UTC) for automatic execution
3. **TestRetentionCommand** - CLI command for manual dry-run testing

### Components

#### RetentionManager

```php
$manager = new RetentionManager();

// Dry-run: Reports what would be deleted
$result = $manager->purgeOldData(dryRun: true);
// Returns: ['rawDeleted' => int, 'aggregateDeleted' => int, 'dryRun' => true]

// Execute: Actually deletes
$result = $manager->purgeOldData(dryRun: false);
```

#### Queries

**Count raw records older than 30 days:**
```sql
SELECT COUNT(*) FROM piwik_plugin_geoprecision_raw 
WHERE server_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

**Delete raw records older than 30 days:**
```sql
DELETE FROM piwik_plugin_geoprecision_raw 
WHERE server_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

**Aggregate retention (365 days):**
```sql
SELECT COUNT(*) FROM piwik_plugin_geoprecision_aggregate 
WHERE period_date < DATE_SUB(CURDATE(), INTERVAL 365 DAY);
```

## Testing

### Dry-Run (No Data Deletion)

```bash
# GeoPrecision
php console geoprecision:test-retention

# DeviceIntelligence
php console deviceintelligence:test-retention

# VisitorFlowIntelligence
php console visitorflow:test-retention
```

Output:
```
[DRY-RUN MODE]
No data will be deleted. Re-run with --execute to actually delete.

Raw data records to delete: 12543
Aggregate records to delete: 287
Total records: 12830
```

### Execute (Deletion)

```bash
# GeoPrecision
php console geoprecision:test-retention --execute

# DeviceIntelligence
php console deviceintelligence:test-retention --execute

# VisitorFlowIntelligence
php console visitorflow:test-retention --execute
```

Output:
```
[EXECUTION MODE]
Data has been deleted!

Raw data records to delete: 12543
Aggregate records to delete: 287
Total records: 12830
```

## Automatic Execution

Tasks are automatically scheduled daily at 3 AM UTC via Matomo's `ScheduledTaskScheduler` hook:

```php
'ScheduledTaskScheduler.scheduleTask' => 'scheduleRetentionTask',
```

Tasks run silently during Matomo's archive process. Execution is logged to:
```
matomo/tmp/logs/matomo.log
```

Log entries:
```
[plugin_geoprecision] INFO: GeoPrecision [EXECUTED]: Raw geo data records to delete: 12543, Aggregate records to delete: 287
```

## Database Schema (Expected)

### Raw Data Tables

Columns required:
- `server_time` (DATETIME) - Timestamp for retention cutoff

Examples:
- `piwik_plugin_geoprecision_raw` (geo quality events)
- `piwik_plugin_deviceintelligence_raw` (device quality events)
- `piwik_plugin_visitorflow_raw` (flow events)

### Aggregate Data Tables

Columns required:
- `period_date` (DATE) - Date for retention cutoff

Examples:
- `piwik_plugin_geoprecision_aggregate` (geo aggregates)
- `piwik_plugin_deviceintelligence_aggregate` (device aggregates)
- `piwik_plugin_visitorflow_aggregate` (flow aggregates)

## Monitoring & Audit Trail

All retention operations are logged with:
- Record counts (raw and aggregate)
- Dry-run vs. executed mode
- Affected date ranges
- Timestamp of execution

Log output location:
```
tail -f matomo/tmp/logs/matomo.log | grep -E "GeoPrecision|DeviceIntelligence|VisitorFlowIntelligence"
```

## Future Enhancements (SB-012+)

1. **Configurable Retention Periods**
   - Allow operators to override default 30/365 day windows
   - Per-site retention policies

2. **Consent Withdrawal Handling**
   - Retroactive masking when consent is withdrawn
   - Audit trail of consent state changes

3. **Performance Tuning**
   - Batch deletes for large tables (LIMIT 10000 per batch)
   - Reduce DB lock time during peak hours

4. **Backup Before Delete**
   - Optional snapshot to archive before purge
   - Point-in-time recovery

5. **Admin UI**
   - Dashboard widget showing retention schedule
   - Last run timestamp and record counts
