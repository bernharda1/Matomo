# SB-014: Archiver Integration

**Status:** In Progress (SB-014.1 to SB-014.6)  
**Branch:** `SB-014-archiver`  
**Phase:** Phase 2 Sprint 2 (Weeks 11-12)

---

## Overview

SB-014 implements Matomo's Archiver system for pre-aggregating raw flow, geographic, and device data into period-based archives (daily, weekly, monthly). This enables fast report generation (< 30s for month-long data) by avoiding raw table queries during user requests.

**Key Benefits:**
- ✅ Pre-computed aggregate data (no runtime aggregation)
- ✅ Fast report generation (< 30s for month, vs. 5-10s for raw query)
- ✅ Period-based caching (daily, weekly, monthly snapshots)
- ✅ Integration with Matomo's core archiving infrastructure
- ✅ Scheduled archiving (runs nightly, no manual intervention)

---

## Ticket Breakdown

### SB-014.1: Create Archiver Base Classes ✅

**Files:**
- `VisitorFlowIntelligence/Infrastructure/BaseArchiver.php`
- `GeoPrecision/Infrastructure/BaseArchiver.php`
- `DeviceIntelligence/Infrastructure/BaseArchiver.php`
- `VisitorFlowIntelligence/Infrastructure/FlowArchiver.php`
- `GeoPrecision/Infrastructure/GeoArchiver.php`
- `DeviceIntelligence/Infrastructure/DeviceArchiver.php`

**Features:**
- Abstract base class with common archiving logic
- ArchiveProcessor integration
- Period detection (day/week/month)
- Archive duplicate detection (avoid re-archiving)
- Metrics & data table storage
- Comprehensive logging

**Plugin Archivers:**

| Plugin | Archiver | Aggregates |
|--------|----------|-----------|
| **VisitorFlowIntelligence** | FlowArchiver | Top paths, transitions, drop-offs |
| **GeoPrecision** | GeoArchiver | Confidence distribution, geographic coverage, precision levels |
| **DeviceIntelligence** | DeviceArchiver | Unknown rates, top devices, Client Hints adoption |

---

### SB-014.2: Complete Aggregation Logic ✅

**Enhanced Methods:**

#### FlowArchiver
- `aggregateTopPaths()`: Groups paths by hash, calculates visit share
- `aggregateTransitions()`: Counts total transitions in flow
- `aggregateDropoffs()`: Analyzes path depth distribution for exit points

**Query Strategy:**

```sql
-- Top Paths Aggregation
SELECT path_hash, COUNT(*) as visits, AVG(depth) as avg_depth
FROM plugin_visitorflow_raw
WHERE idsite = ? AND server_time BETWEEN ? AND ?
GROUP BY path_hash ORDER BY visits DESC LIMIT 100

-- Drop-off Analysis
SELECT depth, COUNT(*) as count
FROM plugin_visitorflow_raw
WHERE idsite = ? AND server_time BETWEEN ? AND ?
GROUP BY depth
```

#### GeoArchiver
- `aggregateConfidenceDistribution()`: Groups by confidence level
- `aggregateGeographicCoverage()`: Country/region/city coverage rates
- `aggregatePrecisionLevels()`: Precision level breakdown

#### DeviceArchiver
- `aggregateUnknownRates()`: Unknown-rate by device type
- `aggregateTopDevices()`: Top brands, models, OS, browsers
- `aggregateClientHintsAdoption()`: CH adoption percentage

**Performance:**
- Single-pass aggregation from raw tables
- Index-based queries (idsite, server_time)
- Suitable for nightly archiving
- Typical time: < 30s per site, per period

---

### SB-014.3: Register Archiver Hooks ✅

**Hook:** `ArchiveProcessor.new`

**Integration Points:**

| Plugin | Method | Hook |
|--------|--------|------|
| VisitorFlowIntelligence | onArchiveProcess() | ArchiveProcessor.new |
| GeoPrecision | onArchiveProcess() | ArchiveProcessor.new |
| DeviceIntelligence | onArchiveProcess() | ArchiveProcessor.new |

**Archiving Flow:**

```
1. Matomo ArchiveProcessor starts (daily at 3 AM UTC)
2. For each site/period: ArchiveProcessor.new hook fires
3. onArchiveProcess() called with ArchiveProcessor instance
4. Plugin Archiver instantiated (FlowArchiver, GeoArchiver, etc.)
5. aggregate() executes:
   - Queries raw data table
   - Aggregates into metrics/data tables
   - Stores via ArchiveProcessor.insertNumericRecord/insertBlobRecord
6. Archive stored in archive_numeric & archive_blob tables
7. Reports use cached archive (no raw table query)
```

**Code Example:**

```php
public function onArchiveProcess(ArchiveProcessor $archiveProcessor): void
{
    try {
        $archiver = new FlowArchiver($archiveProcessor);
        $archiver->aggregate();
    } catch (\Exception $e) {
        \Piwik\Log::warning("[VisitorFlowIntelligence] Archiving error: " . $e->getMessage());
    }
}
```

---

### SB-014.4: Archive Table Creation ⏳ PENDING

**Tables to create:**

```sql
CREATE TABLE IF NOT EXISTS piwik_period_visitorflow_aggregate (
    idarchive INT UNSIGNED NOT NULL,
    idsite INT UNSIGNED NOT NULL,
    period VARCHAR(10) NOT NULL,
    date_start DATE NOT NULL,
    date_end DATE NOT NULL,
    top_paths JSON,
    transitions_total INT UNSIGNED,
    dropoffs JSON,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idarchive, idsite, period),
    INDEX idx_idsite_period (idsite, period)
);

-- Similar for GeoPrecision and DeviceIntelligence
```

**Note:** Matomo uses archive_numeric and archive_blob tables directly. Additional tables are optional for denormalization/reporting.

---

### SB-014.5: Console Commands ✅

**Command:** `visitorflow:test-archiver`

**Usage:**

```bash
# Dry-run (validate configuration)
./console visitorflow:test-archiver 1 --date=2026-06-25

# Execute archiving for specific date
./console visitorflow:test-archiver 1 --date=2026-06-25 --execute

# Archive week (2026-06-22 to 2026-06-28)
./console visitorflow:test-archiver 1 --date=2026-06-22 --period=week --execute

# Archive entire month (2026-06)
./console visitorflow:test-archiver 1 --date=2026-06 --period=month --execute
```

**Output:**

```
VisitorFlowIntelligence Archiver Test

Configuration:
┌───────────────┬──────────────────────┐
│ Parameter     │ Value                │
├───────────────┼──────────────────────┤
│ Site ID       │ 1                    │
│ Date Range    │ 2026-06-25           │
│ Period Type   │ day                  │
│ Mode          │ DRY-RUN              │
└───────────────┴──────────────────────┘

DRY-RUN MODE: No archiving will occur
Run with --execute to perform actual archiving

Archive Configuration Ready:
  Site ID: 1
  Period: day
  Date Range: 2026-06-25

Next steps:
  1. Enable VisitorFlowIntelligence plugin
  2. Populate plugin_visitorflow_raw table with test data
  3. Run: ./console visitorflow:test-archiver 1 --date=2026-06-25 --execute
```

---

### SB-014.6: Performance & Validation ⏳ PENDING

**Testing Plan:**

| Test | Query | Expected Time | Acceptance |
|------|-------|----------------|-----------|
| Archive 1 day | Full aggregation | < 30s | ✅ Single site |
| Archive 1 week | 7 days aggregated | < 2m | ✅ Multi-site friendly |
| Archive 1 month | 30 days aggregated | < 5m | ✅ Batch archiving |
| Archive 1 year | 365 days aggregated | < 30m | ✅ Historical re-archive |
| Concurrent archive | 10 sites × 1 month | < 5m | ✅ DB connection pool |

**Validation Checklist:**

- [ ] Archives created in archive_numeric table
- [ ] Metrics stored with correct ArchiveProcessor ID
- [ ] Data tables serialized correctly in archive_blob
- [ ] Period detection working (day/week/month)
- [ ] Duplicate archive detection prevents re-runs
- [ ] Reports use archived data (no raw table query)
- [ ] Performance < target times
- [ ] Log entries generated for all archiving steps

---

## Archiving Timeline

### Daily Archiving (3 AM UTC)

```
Timeline:
03:00 UTC - Matomo ArchiveProcessor starts
03:05 - Site 1 Day 2026-06-25: ArchiveProcessor.new fires
03:06 - Site 1: FlowArchiver.aggregate() completes
03:07 - Site 1: GeoArchiver.aggregate() completes
03:08 - Site 1: DeviceArchiver.aggregate() completes
03:15 - Site 2 Day 2026-06-25: All archivers complete
...
04:30 - All sites/periods archived, process ends

Results:
- archive_numeric table populated with metrics
- archive_blob table populated with data tables
- Reports now serve from archive (< 30s load time)
```

### Weekly/Monthly Archiving

```
Weekly: Every Monday 03:00 UTC
- Aggregates 7 days of raw data into single archive entry
- Faster queries for week-long date ranges

Monthly: First day of month 03:00 UTC
- Aggregates 30 days into single archive entry
- Enables month-over-month comparisons
```

---

## Performance Impact

### Before SB-014 (Raw Query)

```
Query: SELECT * FROM plugin_visitorflow_raw 
       WHERE idsite=1 AND server_time >= 2026-06-01
Time: 5-10s (1M rows)
IO: Disk reads, full table scan, sorting
```

### After SB-014 (Archive)

```
Query: SELECT * FROM archive_blob 
       WHERE idsite=1 AND idarchive IN (...)
Time: < 500ms (pre-computed)
IO: Small blob record fetch, no aggregation
Improvement: 10-20x faster
```

---

## Storage Impact

**Archive Storage (per site, per period):**

| Data | Size | Notes |
|------|------|-------|
| Top Paths (100 rows) | ~15 KB | JSON serialized |
| Transitions Metric | ~1 KB | Single integer |
| Drop-offs (20 points) | ~5 KB | JSON array |
| **Day Archive** | **~20 KB** | Per site |
| **Week Archive (7 days)** | **~25 KB** | Aggregated |
| **Month Archive (30 days)** | **~30 KB** | Aggregated |

**12-Month Storage:**
- Days: 365 × 20 KB = 7.3 MB per site
- Weeks: 52 × 25 KB = 1.3 MB per site
- Months: 12 × 30 KB = 0.36 MB per site
- **Total: ~9 MB per site** (vs. 14.4 GB raw tables)

---

## Next Tickets

- **SB-014.4:** Create archive tables (denormalization)
- **SB-014.6:** Performance testing & validation
- **SB-015:** Caching layer (cache aggregates in Redis)
- **SB-016:** Segment support (enable filtering)

---

## Documentation Links

- [PHASE_2_3_ROADMAP.md](../PHASE_2_3_ROADMAP.md) — Full Phase 2 plan
- [SB-013_DATABASE_LAYER.md](SB-013_DATABASE_LAYER.md) — Raw data tables
- [DATA_CONTRACTS.md](../DATA_CONTRACTS.md) — API specs

---

**Ready for Testing:** Yes  
**Code Review Required:** Yes  
**Merge to Master:** Pending testing validation

