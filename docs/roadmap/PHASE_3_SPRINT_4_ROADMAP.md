# Phase 3 Sprint 4 Roadmap (SB-022 → SB-026)

**Date**: 2026-06-25  
**Previous**: SB-020 + SB-021 Merged ✅  
**Master Branch**: 73ad968  
**Status**: Ready for Sprint 4 Planning

---

## Overview

Following the successful completion of SB-020 (Real-time Dashboards) and SB-021 (Custom Segment Builder), this document outlines the recommended next 5 tickets to extend the platform with advanced analytics, real-time alerts, performance optimization, and mobile support.

---

## Recommended Tickets (Priority Order)

### 🥇 **SB-022: Advanced Segment Analytics Dashboard** (HIGH)

**Dependencies**: SB-020, SB-021  
**Effort**: 40 hours  
**Target Sprint**: Week 23-24

**Overview**:
Build comprehensive analytics dashboard for segments using real-time data from SB-020 and metadata from SB-021.

**Deliverables**:
- `SegmentAnalyticsDashboard.vue`: Full-featured dashboard component
- Analytics queries: Segment usage trends, performance metrics
- Performance drill-down: Segment → traffic source → device → browser
- Comparison mode: Compare 2+ segments side-by-side
- Export functionality: CSV/PDF reports
- Real-time updates: Integrated with WebSocket

**Technical Details**:
```php
// New API endpoints
GET /api/SegmentAPI.getAnalytics?segmentId=1&period=month
GET /api/SegmentAPI.compareSegments?ids=1,2,3
GET /api/SegmentAPI.getTrendAnalysis?segmentId=1&days=30
```

**Acceptance Criteria**:
- ✅ Dashboard loads < 2 seconds
- ✅ Drill-down responsive < 1 second
- ✅ Real-time updates every 10 seconds
- ✅ Export completes < 5 seconds
- ✅ 15+ test cases passing

---

### 🥈 **SB-023: Real-time Alert Integration** (HIGH)

**Dependencies**: SB-020  
**Effort**: 35 hours  
**Target Sprint**: Week 25

**Overview**:
Enable real-time alerts when visitors or segments meet defined conditions (drop in traffic, anomalies, high bounce rate, etc).

**Deliverables**:
- `AlertManager.php`: Alert rule engine
- `AlertRepository.php`: Persistence layer
- `AlertAPI.php`: REST endpoints (CRUD + trigger)
- `AlertNotifier.php`: Email/Slack/webhook dispatch
- `AlertsPanel.vue`: UI for creating/managing alerts
- Alert templates (20+ common alerts)

**Technical Details**:
```php
// Alert rule schema
[
    'id' => 1,
    'name' => 'Traffic Drop Alert',
    'condition' => 'traffic < baseline * 0.8',
    'threshold' => 0.8,
    'segment_id' => 5,
    'notifications' => ['email', 'slack', 'webhook'],
    'enabled' => true,
    'created_at' => '2026-06-25'
]
```

**Acceptance Criteria**:
- ✅ Alert triggers within 30 seconds of condition met
- ✅ Notifications sent within 5 seconds
- ✅ 10+ alert templates provided
- ✅ Email + Slack + webhook support
- ✅ 12+ test cases passing

---

### 🥉 **SB-024: Redis Caching Layer** (MEDIUM)

**Dependencies**: SB-020, SB-021  
**Effort**: 30 hours  
**Target Sprint**: Week 26

**Overview**:
Implement Redis caching for real-time data and segment computations to reduce database load and improve performance.

**Deliverables**:
- `CacheManager.php`: Redis wrapper with TTL logic
- Cache strategies: Segment results (5 min), realtime data (30 sec), preset cache (1 day)
- Cache invalidation: Automatic on segment update
- `CacheStoreFactory.php`: Factory for different cache types
- Monitoring: Cache hit/miss rates

**Technical Details**:
```php
// Cache key structure
CACHE_KEY = "visitorflow:{type}:{id}:{segment}:{period}"

// TTL by type
'segment_results' => 300,      // 5 minutes
'realtime_data' => 30,         // 30 seconds
'presets' => 86400,            // 1 day
'analytics' => 3600            // 1 hour
```

**Acceptance Criteria**:
- ✅ Cache hit rate > 75% in normal operations
- ✅ Query time reduced by 60%+ with cache
- ✅ Graceful fallback when Redis unavailable
- ✅ Cache invalidation < 100ms
- ✅ 10+ test cases passing

---

### 🎯 **SB-025: Mobile App API & SDK** (MEDIUM)

**Dependencies**: SB-020, SB-021  
**Effort**: 45 hours  
**Target Sprint**: Week 27-28

**Overview**:
Create REST API and SDK for mobile apps (iOS/Android) to access real-time dashboards and segments.

**Deliverables**:
- `MobileAPI.php`: Optimized API endpoints (mobile-specific)
- Response compression: Gzip responses < 5KB
- `MobileSDK.php`: PHP SDK for mobile clients
- Swift SDK package: iOS integration
- Kotlin SDK package: Android integration
- API authentication: Token-based with refresh
- Rate limiting: Per-device throttling

**Technical Details**:
```php
// Mobile API endpoints
GET /api/Mobile/realtime/{siteId}             // Compact realtime data
GET /api/Mobile/segments/{siteId}             // Available segments
GET /api/Mobile/segment/{segmentId}/metrics   // Segment metrics
POST /api/Mobile/auth/token                   // Get auth token
```

**Response Format** (optimized for mobile):
```json
{
  "rt": {
    "c": 42,           // current visitors
    "f": [...]         // flows
    "d": [...]         // dropoffs
  },
  "u": 1624636800      // last update timestamp
}
```

**Acceptance Criteria**:
- ✅ API response < 5KB per request
- ✅ SDK calls < 100ms latency
- ✅ Authentication token expires 24 hours
- ✅ Rate limit: 1000 req/hour per device
- ✅ iOS + Android SDKs fully functional
- ✅ 15+ test cases passing

---

### 🚀 **SB-026: Performance Optimization v1.1** (LOW)

**Dependencies**: SB-020, SB-021, SB-024  
**Effort**: 25 hours  
**Target Sprint**: Week 29

**Overview**:
Post-release performance optimization pass targeting 50%+ latency reduction and 30%+ memory savings.

**Deliverables**:
- Query optimization: Index analysis + add missing indexes
- Memory profiling: Identify and fix memory leaks
- WebSocket optimization: Binary protocol for updates (vs JSON)
- Lazy loading: Defer non-critical data loading
- Database pooling: Connection pool for high concurrency
- Load testing: Simulated 10k concurrent users
- Performance monitoring: New Relic / DataDog integration

**Target Metrics**:
```
Current → Target
-----------------------
Query time:        1.5s → 0.8s  (47% faster)
Dashboard load:    2.5s → 1.2s  (52% faster)
Memory per client:  500KB → 350KB (30% less)
WebSocket msg:     ~2KB → ~400B  (80% smaller with binary)
Throughput:        100 req/s → 250 req/s (2.5x)
```

**Acceptance Criteria**:
- ✅ 50%+ latency reduction verified
- ✅ Memory usage < 350KB per client
- ✅ Binary protocol reduces message size by 80%
- ✅ Load test passes 10k concurrent users
- ✅ Production monitoring dashboard live

---

## Sprint Calendar

| Sprint | Weeks | Tickets | Focus |
|--------|-------|---------|-------|
| 3 ✅ | 20-22 | SB-020, SB-021 | Real-time + Segments |
| 4 | 23-24 | SB-022 | Advanced Analytics |
| 4 | 25 | SB-023 | Alerts |
| 4 | 26 | SB-024 | Caching |
| 4 | 27-28 | SB-025 | Mobile SDK |
| 4 | 29 | SB-026 | Performance |

---

## Dependencies Graph

```
SB-018 (Security) ✅
    ↓
SB-019 (Geo-Rate) ✅
    ↓
    ├─→ SB-020 (Real-time) ✅
    │       ↓
    │   ┌───┴────┬────────────────┐
    │   │        │                │
    │   ↓        ↓                ↓
    │  SB-022  SB-023          SB-025
    │ (Analytics)(Alerts)    (Mobile)
    │   ↑        ↑                ↑
    │   └────┬───┴────────────┬───┘
    │        ↓                ↓
    │      SB-024 (Cache) → SB-026 (Perf)
    │
    └─→ SB-021 (Segments) ✅
            ↓
        ┌───┴──────────┐
        ↓              ↓
      SB-022         SB-024
   (Analytics)     (Cache)
```

---

## Technical Decisions for Sprint 4

### 1. Cache Strategy
- **Decision**: Redis over in-process cache
- **Rationale**: Shared across processes, persistent across restarts
- **TTL tiers**: 30s (realtime) → 5m (segments) → 1h (analytics)

### 2. Mobile API Optimization
- **Decision**: Custom response format (not full REST model)
- **Rationale**: Mobile networks limited; compress data 80%
- **Size targets**: < 5KB per request

### 3. Alert Dispatch
- **Decision**: Async queue (not immediate)
- **Rationale**: Better scaling; fail gracefully if service down
- **Latency target**: < 5 seconds despite async

### 4. Performance Testing
- **Decision**: Load test with 10k concurrent (not just 1k)
- **Rationale**: Prepare for viral adoption scenarios
- **Tools**: Apache JMeter / Locust

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Redis unavailable | Fallback to DB queries (with timeout) |
| Alert spam | Deduplication + aggregation logic |
| Mobile API breaking | Versioning: /api/v1/Mobile/... |
| Performance regression | Benchmark suite runs before merge |
| High memory in alerts | Streaming large result sets vs loading all |

---

## Success Criteria for Sprint 4

| Metric | Target |
|--------|--------|
| On-time delivery | 5/5 tickets merged |
| Test coverage | 80%+ across all new code |
| Performance improvement | 50%+ latency reduction |
| Zero security findings | 100% audit pass |
| Production stability | <0.1% error rate |
| User adoption | 50%+ of segments used |

---

## Recommended Start Date

**SB-022** can start immediately (Monday, 2026-06-30) as it has no blocking dependencies beyond existing merged features.

**SB-023** should start after SB-022 completes (around 2026-07-07) to leverage alert triggers from analytics.

---

## Review Checklist

Before starting Sprint 4:

- [ ] Verify SB-020/021 stability in production (24+ hours)
- [ ] Collect user feedback on segment builder & real-time dashboard
- [ ] Review error logs for any edge cases
- [ ] Confirm Redis availability for SB-024
- [ ] Update API documentation for all new endpoints
- [ ] Prepare test data sets (10k segments, 100k events)
- [ ] Schedule team review of Sprint 4 design docs

---

**Next Action**: Confirm Sprint 4 start date and assign ticket owners.
