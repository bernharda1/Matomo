# SB-021: Custom Segment Builder (Initial Implementation)

**Status:** In Progress (Planning Phase)  
**Branch:** `SB-021-custom-segment-builder`  
**Phase:** Phase 3 Sprint 4 (Weeks 23-24)

---

## Overview

SB-021 enables users to visually build custom visitor segments with no-code interface:

- ✅ SegmentBuilder service (core logic)
- ✅ SegmentRepository (persistence)
- ✅ SegmentAPI (REST endpoints)
- 🔄 Vue.js UI components (next iteration)
- 🔄 Preset segment library (next iteration)

**Key Goals:** User-friendly segment composer, shareable segments, preset library

---

## Step 1: Segment Builder Service (SB-021.1)

### SegmentBuilder (`Service/SegmentBuilder.php`)

**Features:**

| Feature | Implementation | Status |
|---------|-----------------|--------|
| Create segments | Rule-based building | ✅ |
| Update segments | Modify rules & metadata | ✅ |
| Delete segments | Cleanup with dependencies | ✅ |
| Segment queries | Auto-generate DSL | ✅ |
| AND/OR logic | Complex rule composition | ✅ |
| Sharing | Per-user permissions | ✅ |
| Usage analytics | Track segment usage | ✅ |
| Validation | Input & rule validation | ✅ |

**Supported Fields:**

```
- deviceType (mobile, tablet, desktop)
- country (country codes)
- browserName (Chrome, Firefox, Safari, etc)
- osName (Windows, macOS, Linux, etc)
- referrerType (direct, search, social, etc)
- searchKeyword (search engine keywords)
- customVariable (user-defined variables)
- visitorId (specific visitor ID)
- visitorType (new, returning)
- visitDuration (seconds)
- actionCount (number of actions)
- goalConversions (number of conversions)
```

**Operators:**

```
- == (equals)
- != (not equals)
- contains (substring match)
- not_contains (exclude substring)
- > (greater than)
- < (less than)
- >= (greater or equal)
- <= (less or equal)
- in (list membership)
- not_in (exclude list)
```

**API Methods:**

```php
// Create segment
$segment = $builder->createSegment(
    name: 'Mobile Users - DE',
    description: 'Mobile visitors from Germany',
    rules: [
        ['field' => 'deviceType', 'operator' => '==', 'value' => 'mobile'],
        ['field' => 'country', 'operator' => '==', 'value' => 'de'],
    ],
    operator: 'AND',  // All rules must match
    isPublic: false
);
// Returns: [segment_id, name, query, rule_count, created_at]

// Update segment
$builder->updateSegment(
    segmentId: 123,
    name: 'Updated Name',
    rules: [...]
);

// Delete segment
$builder->deleteSegment(123);

// Get all segments
$segments = $builder->getSegments(onlyPublic: false);

// Get single segment
$segment = $builder->getSegment(123);

// Get presets
$presets = $builder->getPresets();

// Share segment
$builder->shareSegment(
    segmentId: 123,
    targetUserId: 456,
    permission: 'read'  // read, write, admin
);

// Get usage
$usage = $builder->getSegmentUsage(123);
// Returns: [uses, last_used, shared_with]
```

---

## Step 2: Segment Repository (SB-021.1)

### SegmentRepository (`Infrastructure/SegmentRepository.php`)

**Database Tables:**

```sql
-- Segments table
CREATE TABLE piwik_visitorflow_segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    user_id INT,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(1000),
    query TEXT,
    rules JSON,
    operator VARCHAR(10),  -- AND/OR
    is_public TINYINT DEFAULT 0,
    created_at INT,
    updated_at INT,
    INDEX (site_id, user_id),
    INDEX (site_id, is_public)
);

-- Shares table
CREATE TABLE piwik_visitorflow_segment_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    segment_id INT NOT NULL,
    user_id INT NOT NULL,
    permission VARCHAR(20),  -- read, write, admin
    created_at INT,
    updated_at INT,
    UNIQUE (segment_id, user_id),
    INDEX (user_id)
);

-- Usage tracking
CREATE TABLE piwik_visitorflow_segment_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    segment_id INT NOT NULL,
    used_at INT,
    user_id INT,
    INDEX (segment_id),
    INDEX (used_at)
);
```

**Methods:**

- `save()` - Create new segment
- `update()` - Modify existing segment
- `delete()` - Remove segment & related data
- `getById()` - Fetch single segment
- `getAll()` - List user segments
- `getPresets()` - Built-in preset segments
- `share()` - Grant access to users
- `countUsages()` - Usage statistics
- `getLastUsed()` - Last access time
- `countShares()` - Share statistics

---

## Step 3: Segment API (SB-021.2)

### SegmentAPI (`API/SegmentAPI.php`)

**Endpoints:**

| Endpoint | Method | Parameters | Response |
|----------|--------|-----------|----------|
| `/createSegment` | POST | idSite, name, rules, operator? | Segment object |
| `/updateSegment` | PUT | idSite, segmentId, name?, rules? | Update status |
| `/deleteSegment` | DELETE | idSite, segmentId | Delete status |
| `/getSegments` | GET | idSite, onlyPublic? | Segments array |
| `/getSegment` | GET | idSite, segmentId | Segment object |
| `/getPresetSegments` | GET | idSite | Presets array |
| `/shareSegment` | POST | idSite, segmentId, userId, permission | Share status |
| `/getSegmentUsage` | GET | idSite, segmentId | Usage stats |

---

## Segment Query Examples

### Example 1: Mobile Visitors - Germany

```
Rules:
- deviceType == mobile
- country == de
Operator: AND

Query: deviceType==mobile;AND;countryCode==de
```

### Example 2: Returning Visitors OR High Engagement

```
Rules:
- visitorType == returning
- actionCount > 10
Operator: OR

Query: visitorType==returning;OR;actionCount>10
```

### Example 3: Search Traffic - Exclude Brand

```
Rules:
- referrerType == search
- searchKeyword not_contains "brandname"

Query: referrerType==search;AND;searchKeyword!*brandname
```

---

## Next Implementation Phases

### Phase 4A: Vue.js Segment Builder UI
- Drag-and-drop rule composer
- Visual rule editor
- Real-time query preview
- Save & test functionality

### Phase 4B: Preset Library
- 20+ built-in presets
- Segment templates
- Quick-select interface

### Phase 4C: Segment Analytics
- Segment performance metrics
- Most-used segments
- Segment sharing insights

---

## Configuration

### Environment Variables

```bash
# Segment settings
VISITORFLOW_SEGMENT_MAX_RULES=20          # Max rules per segment
VISITORFLOW_SEGMENT_MAX_NAME=255          # Max name length
VISITORFLOW_SEGMENT_MAX_DESC=1000         # Max description
VISITORFLOW_SEGMENT_CACHE_TTL=3600        # Cache duration (1 hour)
```

---

## Security Considerations

### Access Control
- User can only access own segments or shared ones
- Permissions: read, write, admin
- Admin can manage all segments

### Data Validation
- Input validation on all fields
- XSS prevention in descriptions
- SQL injection prevention in queries

### Rate Limiting
- 100 segment creations per hour
- 50 shares per minute
- Query rate limiting via RateLimiter

---

## Performance Targets

| Metric | Target | Status |
|--------|--------|--------|
| List segments | < 500ms | 🎯 |
| Create segment | < 200ms | 🎯 |
| Update segment | < 200ms | 🎯 |
| Query generation | < 10ms | 🎯 |
| Share segment | < 100ms | 🎯 |

---

## Files Created

| File | Lines | Purpose |
|------|-------|---------|
| Service/SegmentBuilder.php | 290 | Segment business logic |
| Infrastructure/SegmentRepository.php | 300 | Data persistence |
| API/SegmentAPI.php | 120 | REST endpoints |

**Total: +710 lines (foundation)**

---

## Status & Next Steps

**Completed (This Session):**
- ✅ SegmentBuilder service implementation
- ✅ SegmentRepository with database schema
- ✅ SegmentAPI REST endpoints

**Next (Continuation):**
- 📋 Vue.js UI components
- 📋 Preset segment library
- 📋 Segment analytics
- 📋 Integration tests

---

**SB-021 Foundation Ready!** 🚀

Parallel development with SB-020 real-time dashboards maintains project momentum.
