# Phase 2 & Phase 3 Roadmap - Matomo Plugin Suite Expansion

## Overview

After successful completion of Phase 1 (MVP: SB-000 to SB-012), Phase 2-3 focuses on:
- Production hardening and performance optimization
- Advanced features and visualizations
- Enterprise-grade reliability

**Timeline:** Weeks 9-20 (12 weeks total)

---

## Phase 2: Hardening & Optimization (Weeks 9-14)

### SB-013: Database Layer Optimization

**Objective:** Implement persistent storage with proper indexing and performance tuning.

**Tickets:**
- SB-013.1 Create plugin_visitorflow_raw table (idvisit, path_hash, depth, server_time)
- SB-013.2 Create plugin_geoprecision_raw table (idvisit, country, region, city, confidence_score, server_time)
- SB-013.3 Create plugin_deviceintelligence_raw table (idvisit, device_type, brand, model, server_time)
- SB-013.4 Add indexes (server_time, idsite, country/device_type)
- SB-013.5 Implement migration scripts with versioning (v1.0, v1.1 patches)
- SB-013.6 Test performance on 10M+ row tables

**Acceptance:** Tables created, indexes applied, query P95 < 500ms

---

### SB-014: Archiver Integration

**Objective:** Implement Matomo Archiver for pre-aggregated period data (daily/weekly/monthly).

**Tickets:**
- SB-014.1 Create Archiver classes for each plugin
- SB-014.2 Implement period aggregation logic (sum, avg, distinct)
- SB-014.3 Register ArchiveProcessor hooks
- SB-014.4 Add archive persistence to custom aggregate tables
- SB-014.5 Enable period-based report generation (existing + custom periods)
- SB-014.6 Load tests: archive 1 year of daily data

**Acceptance:** Archive process completes within SLA, reports served from archive cache

---

### SB-015: Caching Layer

**Objective:** Add Redis/Memcached caching for API responses and aggregates.

**Tickets:**
- SB-015.1 Integrate with Matomo cache facade (Context, Transient)
- SB-015.2 Cache API responses (TTL: 1 hour for day, 24h for month)
- SB-015.3 Implement cache invalidation on data deletion
- SB-015.4 Add cache statistics to health check
- SB-015.5 Performance comparison (cached vs non-cached)
- SB-015.6 Document cache configuration and sizing

**Acceptance:** API response time reduced by 50% for cached queries, cache hit ratio > 80%

---

### SB-016: Segment Support

**Objective:** Enable segment filtering across all plugin APIs (was deferred in MVP).

**Tickets:**
- SB-016.1 Add segment parameter to VisitorFlowIntelligence API
- SB-016.2 Add segment parameter to GeoPrecision API
- SB-016.3 Add segment parameter to DeviceIntelligence API
- SB-016.4 Implement segment logic in Repository classes
- SB-016.5 Update tests with segment examples
- SB-016.6 Performance impact assessment (with/without segments)

**Acceptance:** All APIs accept & correctly filter by segment, P95 still < 2s

---

### SB-017: Automated Testing

**Objective:** Comprehensive unit and integration tests for all plugins.

**Tickets:**
- SB-017.1 Set up PHPUnit test framework for plugins
- SB-017.2 Unit tests for Domain classes (Step, Transition, Path, etc.)
- SB-017.3 Unit tests for Service classes (FlowPathAggregator, GeoConfidenceScorer, etc.)
- SB-017.4 Integration tests for API methods
- SB-017.5 Database tests for Repository classes (with test DB)
- SB-017.6 Add CI/CD pipeline (GitHub Actions or equivalent)

**Acceptance:** 70%+ code coverage, all tests pass in CI/CD

---

### SB-018: Security Hardening

**Objective:** Implement security best practices and vulnerability fixes.

**Tickets:**
- SB-018.1 Input validation and sanitization (all API parameters)
- SB-018.2 SQL injection prevention audit (verify parameterization)
- SB-018.3 XSS prevention in Twig templates (escaping, filters)
- SB-018.4 CSRF token validation for form submissions
- SB-018.5 Rate limiting for API endpoints (optional)
- SB-018.6 Security audit report + remediation plan

**Acceptance:** OWASP Top 10 compliance, zero critical findings

---

## Phase 3: Advanced Features (Weeks 15-20)

### SB-019: Visualization Enhancements

**Objective:** Add interactive charts and advanced report visualizations.

**Tickets:**
- SB-019.1 Sankey diagram for VisitorFlowIntelligence paths (using D3.js or similar)
- SB-019.2 Confidence score heat map for GeoPrecision (by country/region)
- SB-019.3 Device tree (hierarchical browser > OS > model)
- SB-019.4 Time series charts (confidence trend, unknown-rate trend)
- SB-019.5 Export reports to PDF/Excel
- SB-019.6 Dashboard widget registration for custom homepage

**Acceptance:** All visualizations render, interactive filters work, exports complete

---

### SB-020: Segment Widgets

**Objective:** Create reusable dashboard widgets for each plugin (mini reports).

**Tickets:**
- SB-020.1 VisitorFlowIntelligence widget: "Top Path This Week"
- SB-020.2 GeoPrecision widget: "Confidence Score Summary"
- SB-020.3 DeviceIntelligence widget: "Top 5 Devices"
- SB-020.4 Widget builder API (configurable date range, limit, etc.)
- SB-020.5 Widget caching strategy
- SB-020.6 Widget documentation for end users

**Acceptance:** All widgets render on dashboard, update when date range changes

---

### SB-021: Real-time Archiving

**Objective:** Implement real-time data aggregation without waiting for scheduled archive process.

**Tickets:**
- SB-021.1 Implement real-time aggregation for hourly data
- SB-021.2 Add real-time cache layer (separate from archive cache)
- SB-021.3 Real-time API endpoints (getRealTimeData)
- SB-021.4 Real-time report in UI with auto-refresh
- SB-021.5 Performance testing (load impact on DB)
- SB-021.6 Configuration: enable/disable real-time per site

**Acceptance:** Real-time data available within 1 minute of event, < 10% DB load increase

---

### SB-022: Cohort Analysis

**Objective:** Enable tracking and analysis of visitor cohorts (grouped by attributes).

**Tickets:**
- SB-022.1 Define cohort model (cohort_id, member_count, creation_date, attributes)
- SB-022.2 Cohort creation API (by device, geo, flow pattern)
- SB-022.3 Cohort retention curves (week-over-week, month-over-month)
- SB-022.4 Cohort comparison (Device A vs Device B retention)
- SB-022.5 UI for cohort builder and visualizations
- SB-022.6 Cohort data export

**Acceptance:** Cohorts can be created, retention analysis available, UI functional

---

### SB-023: Anomaly Detection (ML)

**Objective:** Implement basic ML for anomaly detection in KPI trends.

**Tickets:**
- SB-023.1 Define anomaly model (baseline vs current, σ-based detection)
- SB-023.2 Implement baseline calculation (30-day rolling average)
- SB-023.3 Anomaly detection for confidence score drops
- SB-023.4 Anomaly detection for unknown-rate spikes
- SB-023.5 Anomaly detection for flow drop-off changes
- SB-023.6 Anomaly alerting (email/webhook) for operations team

**Acceptance:** Anomalies detected, alerts sent, baseline configurable

---

### SB-024: Admin Configuration UI

**Objective:** Create admin interface for plugin configuration (web UI, not code changes).

**Tickets:**
- SB-024.1 Admin settings page under Administration > Plugins
- SB-024.2 Configurable retention periods (raw, aggregate)
- SB-024.3 Enable/disable features per site (real-time, anomaly detection)
- SB-024.4 Configure cache settings (TTL, backend)
- SB-024.5 Data export preferences (GDPR compliance)
- SB-024.6 Settings validation and error handling

**Acceptance:** All settings can be configured via UI, changes take effect immediately

---

### SB-025: Extended Documentation & Certification

**Objective:** Complete documentation and release as production-ready.

**Tickets:**
- SB-025.1 Developer guide (extending plugins, adding dimensions)
- SB-025.2 API reference (auto-generated from code)
- SB-025.3 Operations certification guide (training for ops team)
- SB-025.4 Compliance documentation (GDPR, CCPA readiness)
- SB-025.5 Migration guide (from competitor tools)
- SB-025.6 Release v1.0 production announcement

**Acceptance:** All documentation complete, team certified, ready for GA release

---

## Timeline & Dependencies

```
Phase 1 (Weeks 1-8): MVP ✓ COMPLETE
├─ SB-000 to SB-012 (All tickets done)

Phase 2 (Weeks 9-14): Hardening & Optimization
├─ SB-013: Database Layer (Week 9-10)
├─ SB-014: Archiver (Week 10-11, depends on SB-013)
├─ SB-015: Caching (Week 11-12)
├─ SB-016: Segment Support (Week 12-13, depends on SB-013)
├─ SB-017: Testing (Week 13-14, parallel with others)
└─ SB-018: Security (Week 14)

Phase 3 (Weeks 15-20): Advanced Features
├─ SB-019: Visualizations (Week 15-16, depends on SB-014)
├─ SB-020: Widgets (Week 16-17, depends on SB-014)
├─ SB-021: Real-time (Week 17-18, depends on SB-014)
├─ SB-022: Cohorts (Week 18-19)
├─ SB-023: Anomaly Detection (Week 19)
├─ SB-024: Admin UI (Week 20, depends on all)
└─ SB-025: Docs & Certification (Week 20, parallel with SB-024)
```

---

## Resource Plan

### Team Composition
- 1x Backend Developer (Core plugin logic, DB, API)
- 1x Frontend Developer (UI, visualizations, widgets)
- 1x DevOps Engineer (Infrastructure, CI/CD, monitoring)
- 1x QA Engineer (Testing, security audit)
- 1x Product Owner (Requirements, acceptance criteria)

### Time Estimates
- Phase 2: ~80 dev-days (10 weeks / 1 person = 50 days + overflow)
- Phase 3: ~120 dev-days (6 weeks / 1-2 people)
- Total: ~200 dev-days for full expansion

---

## KPI & Success Metrics

### Performance Targets (Phase 2-3)
- API P95 response time: < 500ms (vs. current 1-2s)
- Report generation: < 30s for month-long data (vs. current 5-10s)
- Cache hit ratio: > 80%
- DB query time: < 200ms for 95th percentile

### Reliability Targets
- Uptime: 99.95% (or better)
- Error rate: < 0.1%
- Zero data loss incidents
- Incident response time: < 15 minutes

### User Adoption Targets
- 100% of operations team trained (SB-025)
- 50%+ of daily reports run through new plugins (Month 1)
- 80%+ of daily reports run through new plugins (Month 3)

---

## Risk Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|-----------|
| DB performance degradation | Medium | High | SB-013/014 index strategy, load testing |
| Cache invalidation bugs | Medium | Medium | SB-015 comprehensive testing |
| Segment logic complexity | Medium | Medium | SB-016 peer review, integration tests |
| ML false positive alerts | High | Low | SB-023 tuning, manual override option |
| Team turnover | Low | High | SB-025 documentation, knowledge transfer |

---

## Decision Points & Go/No-Go Criteria

### End of Phase 2
- [ ] All database tables in production, performance baseline established
- [ ] Archive process running reliably, P95 < 30s
- [ ] 70%+ test coverage, CI/CD pipeline green
- [ ] Security audit passed (zero critical findings)

**Decision:** Proceed to Phase 3 for advanced features OR Hold for stabilization

### End of Phase 3
- [ ] All visualizations working, no client-side crashes
- [ ] Real-time data available with acceptable latency
- [ ] Anomaly detection tuned and validated
- [ ] Admin UI fully functional, all settings tested

**Decision:** Release v1.0 for production OR Patch cycle for Phase 3.1

---

## Budget & ROI

### Estimated Investment
- Development: 200 dev-days @ €100/hour = €160,000
- Infrastructure: €10,000 (testing DB, caching cluster)
- QA & Security: €20,000
- **Total: ~€190,000**

### Expected ROI
- Saved licensing from competitor tools: €50,000/year
- Operational efficiency gains: €30,000/year (faster analysis, fewer manual reports)
- **Break-even: ~2.3 years, positive ROI thereafter**

---

## Next Steps

1. **Approval:** Confirm Phase 2-3 scope with stakeholders
2. **Planning:** Detailed task breakdown for SB-013 to SB-025
3. **Sprint Planning:** Assign tickets to development sprints
4. **Kickoff:** Team alignment on first sprint (SB-013)
5. **Baseline:** Measure current performance metrics for comparison

---

## Appendix: Feature Backlog (Nice-to-Have)

- **Multi-language support** (currently en only)
- **Mobile-optimized UI** (responsive design)
- **API rate limiting & quotas** (enterprise feature)
- **White-label branding** (SaaS multi-tenant)
- **Slack/Teams integration** (alerts, dashboards)
- **Data warehouse sync** (BigQuery, Snowflake export)
- **GIS mapping** (geographic heatmaps)
- **Predictive analytics** (forecast future trends)

---

**Prepared by:** Matomo Plugin Development Team  
**Date:** 2026-06-25  
**Status:** Ready for Stakeholder Review
