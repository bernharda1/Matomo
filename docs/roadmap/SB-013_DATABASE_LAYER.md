# SB-013: Database Layer Optimization

**Status:** In Progress (SB-013.1 to SB-013.6)  
**Branch:** `SB-013-database-layer`  
**Phase:** Phase 2 (Hardening & Optimization)

---

## Overview

SB-013 implements persistent storage layer for all three plugins with proper indexing and performance tuning. Instead of querying raw `log_visit` and `log_link_visit_action` tables directly, each plugin now has its own dedicated raw data table optimized for aggregation.

**Key Benefits:**
- ✅ Faster queries (indexed on `idsite`, `server_time`, dimension columns)
- ✅ Efficient retention management (monthly partitioning for 30-day purge)
- ✅ Scalability (dedicated tables vs shared log tables)
- ✅ Migration versioning (track schema changes, enable rollback)

---

## Ticket Breakdown

### SB-013.1: Create plugin_visitorflow_raw Table

**File:** `VisitorFlowIntelligence/Infrastructure/Migrations/Migration_1_0_0_CreateVisitorFlowRawTable.php`

**Schema:**

| Column | Type | Comment |
|--------|------|---------|
| `id` | BIGINT UNSIGNED | Auto-increment primary key |
| `idsite` | INT UNSIGNED | Site ID (foreign key) |
| `idvisit` | BIGINT UNSIGNED | Visit ID (foreign key) |
| `idvisitor` | BINARY(8) | Visitor ID |
| `path_hash` | VARCHAR(32) | MD5 hash for deduplication |
| `depth` | TINYINT UNSIGNED | Steps in path (1-N) |
| `steps_json` | MEDIUMTEXT | JSON array of steps |
| `transition_count` | SMALLINT UNSIGNED | Number of transitions |
| `visit_duration` | INT UNSIGNED | Total duration (seconds) |
| `server_time` | DATETIME | When recorded |

**Indexes:**

| Index | Columns | Purpose |
|-------|---------|---------|
| `idx_idsite_server_time` | (idsite, server_time) | Range queries by date |
| `idx_idvisit` | (idvisit) | Join with log_visit |
| `idx_path_hash` | (path_hash) | Deduplication check |
| `idx_server_time` | (server_time) | Retention purges (30-day) |

**Size Estimate:**
- Per row: ~500 bytes (with JSON steps)
- 1 million visits/month: ~500 MB raw
- 12-month retention: ~6 GB (with overhead)

---

### SB-013.2: Create plugin_geoprecision_raw Table

**File:** `GeoPrecision/Infrastructure/Migrations/Migration_1_0_0_CreateGeoPrecisionRawTable.php`

**Schema:**

| Column | Type | Comment |
|--------|------|---------|
| `idsite` | INT UNSIGNED | Site ID |
| `idvisit` | BIGINT UNSIGNED | Visit ID |
| `country_code` | CHAR(2) | ISO 3166-1 alpha-2 |
| `region_name` | VARCHAR(100) | Region/State |
| `city_name` | VARCHAR(100) | City |
| `latitude` | DECIMAL(9, 6) | Geographic latitude |
| `longitude` | DECIMAL(9, 6) | Geographic longitude |
| `confidence_score` | TINYINT UNSIGNED | 0-100 confidence |
| `precision_level` | ENUM | unknown/country/region/city/approx/exact |
| `confidence_level` | ENUM | low/medium/high |
| `has_consent_precise` | BOOLEAN | Consent for precise geo |
| `server_time` | DATETIME | When recorded |

**Indexes:**

| Index | Columns | Purpose |
|-------|---------|---------|
| `idx_idsite_server_time` | (idsite, server_time) | Range queries |
| `idx_country` | (country_code, server_time) | Geographic grouping |
| `idx_idvisit` | (idvisit) | Join with log_visit |
| `idx_confidence_score` | (confidence_score) | Confidence distribution |

**Size Estimate:**
- Per row: ~300 bytes
- 1 million visits/month: ~300 MB
- 12-month retention: ~3.6 GB

---

### SB-013.3: Create plugin_deviceintelligence_raw Table

**File:** `DeviceIntelligence/Infrastructure/Migrations/Migration_1_0_0_CreateDeviceIntelligenceRawTable.php`

**Schema:**

| Column | Type | Comment |
|--------|------|---------|
| `idsite` | INT UNSIGNED | Site ID |
| `idvisit` | BIGINT UNSIGNED | Visit ID |
| `device_type` | VARCHAR(50) | desktop/mobile/tablet |
| `brand` | VARCHAR(100) | Device brand |
| `model` | VARCHAR(100) | Device model |
| `os_name` | VARCHAR(100) | OS name |
| `browser_name` | VARCHAR(100) | Browser name |
| `browser_version` | VARCHAR(50) | Browser version |
| `client_hints_raw` | JSON | User-Agent Client Hints |
| `client_hints_present` | BOOLEAN | CH available flag |
| `resolution` | VARCHAR(20) | Screen resolution |
| `server_time` | DATETIME | When recorded |

**Indexes:**

| Index | Columns | Purpose |
|-------|---------|---------|
| `idx_idsite_server_time` | (idsite, server_time) | Range queries |
| `idx_device_type` | (device_type, server_time) | Device grouping |
| `idx_brand` | (brand, server_time) | Brand distribution |
| `idx_idvisit` | (idvisit) | Join with log_visit |

**Size Estimate:**
- Per row: ~400 bytes (with Client Hints JSON)
- 1 million visits/month: ~400 MB
- 12-month retention: ~4.8 GB

---

### SB-013.4: Add Indexes & Constraints

**Status:** Deferred to SB-013.4 (Migration_1_0_1_AddIndexesAndPartitions.php)

**Planned:**
- Composite indexes for common queries
- Foreign key constraints to log_visit
- Partitioning by month for retention
- Compressed storage for archive partitions

---

### SB-013.5: Migration Infrastructure

**Files:**
- `Infrastructure/Migrations/Migration.php` (base class)
- `Infrastructure/MigrationManager.php` (version tracking & execution)
- `plugin_migration_log` table (migration history)

**Features:**
- ✅ Version tracking (which migrations executed)
- ✅ Automatic schema discovery
- ✅ Rollback capability (down() method)
- ✅ Logging & error handling

**Usage:**

```php
$manager = new MigrationManager('VisitorFlowIntelligence', $migrationsPath);

// Get status
$status = $manager->getStatus();
// Returns: total, completed, pending, versions

// Execute pending migrations
$executed = $manager->migrate();
// Returns: ['1.0.0', '1.0.1', ...]
```

**Console Command:**

```bash
# Show status
./console visitorflow:test-migrations --status

# Execute migrations
./console visitorflow:test-migrations --execute

# Dry-run (show what would happen)
./console visitorflow:test-migrations --dry-run
```

---

### SB-013.6: Performance Baseline Test

**Objective:** Establish performance baseline for queries on new tables

**Test Cases:**

#### Test 1: Single-Day Query
```sql
SELECT * FROM plugin_visitorflow_raw 
WHERE idsite = 1 
AND server_time BETWEEN '2026-06-25 00:00:00' AND '2026-06-25 23:59:59'
LIMIT 1000;
```

**Expected:**
- Rows: ~10K-50K (1M visits/month ÷ 30 days)
- Time: < 100ms
- Index used: `idx_idsite_server_time`

#### Test 2: Month-Long Range Query
```sql
SELECT COUNT(*), AVG(confidence_score) 
FROM plugin_geoprecision_raw 
WHERE idsite = 1 
AND server_time >= '2026-05-25' 
GROUP BY country_code;
```

**Expected:**
- Rows returned: 10-50 countries
- Time: < 500ms
- Index used: `idx_idsite_server_time` + `idx_country`

#### Test 3: Aggregation Query (Materialized)
```sql
SELECT 
  path_hash,
  COUNT(*) as visits,
  AVG(depth) as avg_depth
FROM plugin_visitorflow_raw
WHERE idsite = 1 AND server_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY path_hash
ORDER BY visits DESC
LIMIT 100;
```

**Expected:**
- Time: < 2 seconds
- Rows: ~100 top paths
- Index used: `idx_idsite_server_time`

#### Test 4: Large Dataset Query (1M+ rows)
```sql
SELECT DISTINCT device_type, COUNT(*) as count
FROM plugin_deviceintelligence_raw
WHERE idsite = 1 
AND server_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY device_type;
```

**Expected:**
- Rows: ~5-10 device types
- Time: < 5 seconds
- Index used: `idx_device_type`

**Baseline Recording:**

```
╔════════════════════════════════════════════════════════════╗
║              Performance Baseline Report                   ║
║                    2026-06-25                              ║
╠════════════════════════════════════════════════════════════╣
║ Metric                    │ Target    │ Actual    │ Status  ║
├───────────────────────────┼───────────┼───────────┼─────────┤
║ Single-day query (10K)    │ < 100ms   │ TBD       │ ⏳      ║
║ Month-long range (50K)    │ < 500ms   │ TBD       │ ⏳      ║
║ Aggregation (path hash)   │ < 2s      │ TBD       │ ⏳      ║
║ Large dataset (1M+ rows)  │ < 5s      │ TBD       │ ⏳      ║
║ P95 response time         │ < 500ms   │ TBD       │ ⏳      ║
╚════════════════════════════════════════════════════════════╝
```

---

## Database Footprint

**Total Storage (12-month retention):**
- plugin_visitorflow_raw: ~6 GB
- plugin_geoprecision_raw: ~3.6 GB
- plugin_deviceintelligence_raw: ~4.8 GB
- **Total: ~14.4 GB** (for 1M visits/month)

**Monthly Growth:** ~1.2 GB/month  
**Daily Growth:** ~40 MB/day  
**Purge Cycle:** Monthly (automatic via retention jobs)

---

## Migration Execution Flow

```
1. Plugin Registration
   └─ Hook: PluginInstalled or PluginActivated
      
2. MigrationManager instantiated
   └─ Scans Infrastructure/Migrations/ for Migration_*.php files
   
3. Get pending migrations
   └─ Query plugin_migration_log for completed versions
   └─ Determine which migrations haven't run
   
4. Execute in order (sorted by version)
   ├─ Call up() method
   ├─ Record in plugin_migration_log
   └─ Log success/failure
   
5. Verify table structure
   └─ Check for errors, foreign keys, indexes
   
6. Done
   └─ Migrations table is now ready for use
```

---

## Testing Strategy

### Unit Tests
- [ ] Migration class can load and validate schema
- [ ] MigrationManager properly tracks versions
- [ ] Table creation SQL is valid

### Integration Tests
- [ ] Migrations execute successfully in test DB
- [ ] Tables are created with correct schema
- [ ] Indexes are applied correctly
- [ ] Foreign key constraints work

### Performance Tests
- [ ] Load test data (1M rows per table)
- [ ] Run baseline query suite (Test 1-4 above)
- [ ] Measure P95 latency
- [ ] Verify indexes are used

---

## Rollback Plan

If migration fails:

1. **Automatic Rollback (if enabled)**
   ```php
   try {
       $manager->migrate();
   } catch (\Exception $e) {
       $manager->rollback(); // Calls down() methods
   }
   ```

2. **Manual Rollback**
   ```bash
   # Drop tables (destructive!)
   DROP TABLE plugin_visitorflow_raw;
   DROP TABLE plugin_geoprecision_raw;
   DROP TABLE plugin_deviceintelligence_raw;
   
   # Clear migration log
   DELETE FROM plugin_migration_log WHERE plugin IN ('VisitorFlowIntelligence', 'GeoPrecision', 'DeviceIntelligence');
   ```

3. **Restore from Backup**
   - If tables corrupted, restore DB backup from before migration
   - Re-run migrations after backup restore

---

## Next Tickets

- **SB-013.4:** Add partitioning & advanced indexes
- **SB-014:** Archiver integration (uses these raw tables)
- **SB-015:** Caching layer (caches aggregates from raw tables)

---

## Documentation Links

- [PHASE_2_3_ROADMAP.md](../PHASE_2_3_ROADMAP.md) — Full Phase 2 plan
- [DATA_CONTRACTS.md](../DATA_CONTRACTS.md) — API specs
- [RUNBOOK.md](../RUNBOOK.md) — Operations procedures

---

**Ready for Testing:** Yes  
**Code Review Required:** Yes  
**Merge to Master:** Pending Phase 2 approval

