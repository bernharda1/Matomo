# Master Roadmap - Matomo Plugin Suite v1.0 → v2.0

## Executive Summary

**Vision:** Build an enterprise-grade analytics plugin suite that replaces premium Matomo modules through in-house development, enabling full control, cost savings, and strategic alignment.

**Scope:** 
- Phase 1 (MVP): Weeks 1-8 ✅ **COMPLETE**
- Phase 2 (Hardening): Weeks 9-14 (12 dev-weeks)
- Phase 3 (Advanced): Weeks 15-20 (12 dev-weeks)
- **Total: 20 weeks to production-grade v1.0**

---

## Phase Overview

### Phase 1: MVP Foundation (Weeks 1-8) ✅ DONE

**Objective:** Build minimum viable product with core functionality, data contracts, and operational documentation.

**Plugins:**
1. **VisitorFlowIntelligence** — Top paths, transitions, drop-offs
2. **GeoPrecision** — Confidence scoring, consent gating
3. **DeviceIntelligence** — Quality metrics, client hints

**Deliverables:**
- 3 production-ready plugins (API + UI + Menu)
- Retention jobs (30d raw, 365d aggregate)
- Comprehensive operational docs (Install, Runbook, Release, Health Check)
- Roadmap, data contracts, KPI baselines, domain models

**Completion:** June 25, 2026 (On Time)

---

### Phase 2: Hardening & Optimization (Weeks 9-14)

**Objective:** Move from MVP to production-grade reliability, performance, and testability.

**Key Work Streams:**

| Ticket | Scope | Outcome |
|--------|-------|---------|
| **SB-013** | Database Layer Optimization | Raw data tables, proper indexing |
| **SB-014** | Archiver Integration | Pre-aggregated period data, fast reports |
| **SB-015** | Caching Layer | 50% API response time reduction |
| **SB-016** | Segment Support | Enable filtering by all dimensions |
| **SB-017** | Automated Testing | 70%+ code coverage, CI/CD |
| **SB-018** | Security Hardening | OWASP compliance, zero critical findings |

**Success Criteria:**
- All plugins available on custom persistence layer (not just log_visit)
- API P95 response time < 500ms
- 100% of reports served from archive cache
- Automated test suite passing in CI/CD
- Security audit clean
- Production deployment guide ready

---

### Phase 3: Advanced Features (Weeks 15-20)

**Objective:** Deliver enterprise analytics capabilities that compete with premium tools.

**Key Work Streams:**

| Ticket | Scope | Outcome |
|--------|-------|---------|
| **SB-019** | Visualizations | Sankey, heatmaps, time series charts |
| **SB-020** | Dashboard Widgets | Mini-reports for dashboard home |
| **SB-021** | Real-time Archiving | Data available within 1 minute |
| **SB-022** | Cohort Analysis | Retention curves, cohort comparison |
| **SB-023** | Anomaly Detection | ML-based KPI alerts |
| **SB-024** | Admin UI | Web-based configuration (no code changes) |
| **SB-025** | Docs & Certification | Team training, v1.0 release |

**Success Criteria:**
- Interactive visualizations with drill-down
- Real-time data pipeline with < 1min latency
- Cohort retention analysis working
- Automated alerts for anomalies
- All configuration via admin UI
- Team certified to operate & extend
- v1.0 production release announced

---

## Detailed Ticket Map

### Phase 2 Tickets (SB-013 to SB-018)

```
SB-013: Database Layer Optimization
├─ 13.1 Create raw data tables (flow, geo, device)
├─ 13.2 Add indexes (server_time, idsite, dimensions)
├─ 13.3 Migrate existing data (if any)
├─ 13.4 Implement migration scripts
├─ 13.5 Performance baseline (query P95)
└─ 13.6 Load testing (10M+ rows)

SB-014: Archiver Integration
├─ 14.1 Create Archiver classes
├─ 14.2 Implement aggregation logic
├─ 14.3 Register hooks (ArchiveProcessor)
├─ 14.4 Persist aggregates to DB
├─ 14.5 API to fetch from archive
└─ 14.6 Archive performance testing

SB-015: Caching Layer
├─ 15.1 Integrate Matomo cache facade
├─ 15.2 Cache API responses (TTL: 1h/24h)
├─ 15.3 Cache invalidation on data delete
├─ 15.4 Cache statistics & monitoring
├─ 15.5 Performance comparison
└─ 15.6 Cache configuration guide

SB-016: Segment Support
├─ 16.1 Add segment param to getTopPaths
├─ 16.2 Add segment param to getConfidenceSummary
├─ 16.3 Add segment param to getQualitySummary
├─ 16.4 Implement segment filtering in Repos
├─ 16.5 Update API response examples
└─ 16.6 Segment + archive performance test

SB-017: Automated Testing
├─ 17.1 Set up PHPUnit framework
├─ 17.2 Domain class tests (70+ tests)
├─ 17.3 Service class tests (50+ tests)
├─ 17.4 API integration tests (30+ tests)
├─ 17.5 DB integration tests (20+ tests)
└─ 17.6 CI/CD pipeline (GitHub Actions)

SB-018: Security Hardening
├─ 18.1 Input validation audit
├─ 18.2 SQL injection prevention check
├─ 18.3 XSS prevention in templates
├─ 18.4 CSRF token validation
├─ 18.5 Rate limiting (optional)
└─ 18.6 Security audit report
```

### Phase 3 Tickets (SB-019 to SB-025)

```
SB-019: Visualization Enhancements
├─ 19.1 Sankey diagram (flow paths)
├─ 19.2 Confidence heatmap (geo by region)
├─ 19.3 Device tree (browser > OS > model)
├─ 19.4 Time series (confidence, unknown-rate trends)
├─ 19.5 Export to PDF/Excel
└─ 19.6 Dashboard widget registration

SB-020: Segment Widgets
├─ 20.1 "Top Path This Week" widget
├─ 20.2 "Confidence Score Summary" widget
├─ 20.3 "Top 5 Devices" widget
├─ 20.4 Widget builder API
├─ 20.5 Widget caching
└─ 20.6 Widget documentation

SB-021: Real-time Archiving
├─ 21.1 Hourly aggregation logic
├─ 21.2 Real-time cache layer
├─ 21.3 Real-time API endpoints
├─ 21.4 Auto-refresh UI (JavaScript)
├─ 21.5 Load testing (DB impact)
└─ 21.6 Enable/disable per site

SB-022: Cohort Analysis
├─ 22.1 Cohort data model
├─ 22.2 Cohort creation API
├─ 22.3 Retention curve calculation
├─ 22.4 Cohort comparison
├─ 22.5 Cohort UI builder
└─ 22.6 Data export

SB-023: Anomaly Detection (ML)
├─ 23.1 Define anomaly model (σ-based)
├─ 23.2 Baseline calculation (30-day avg)
├─ 23.3 Detect confidence score anomalies
├─ 23.4 Detect unknown-rate anomalies
├─ 23.5 Detect drop-off anomalies
└─ 23.6 Alert system (email/webhook)

SB-024: Admin Configuration UI
├─ 24.1 Admin settings page
├─ 24.2 Configurable retention periods
├─ 24.3 Feature toggles (real-time, anomaly)
├─ 24.4 Cache settings
├─ 24.5 Data export preferences
└─ 24.6 Settings validation

SB-025: Extended Documentation
├─ 25.1 Developer guide
├─ 25.2 Auto-generated API reference
├─ 25.3 Operations certification
├─ 25.4 Compliance documentation (GDPR)
├─ 25.5 Migration guide
└─ 25.6 v1.0 release announcement
```

---

## Release Timeline

```
2026-06-25: Phase 1 Complete (MVP)
├─ Tag: v0.1.0-alpha (internal testing)
├─ Status: Functional, not production-ready
└─ Next: Phase 2 kickoff

2026-07-27: Phase 2 Complete (Hardening)
├─ Tag: v0.9.0-beta (release candidates)
├─ Status: Production-ready, tested, documented
├─ Duration: 6 weeks (14 calendar days = 2 weeks summer break)
└─ Next: Phase 3 kickoff

2026-09-08: Phase 3 Complete (Advanced)
├─ Tag: v1.0.0 (production release)
├─ Status: Enterprise-grade, feature-complete
├─ Duration: 6 weeks
└─ Next: Ongoing maintenance & monitoring

2026-Q4+: Continuous Improvement
├─ Patches (v1.0.1, v1.0.2, etc.)
├─ Minor features (v1.1, v1.2)
└─ Major features (v2.0, v3.0)
```

---

## Resource Allocation

### Development Team (Weeks 1-20)

**Week 1-8 (Phase 1):**
- 1x Backend Dev (Full-time): Core logic, APIs
- 1x Frontend Dev (Part-time, 50%): Templates, basic UI
- 1x DevOps Eng (Part-time, 25%): Infrastructure, docs
- 1x QA (Part-time, 25%): Testing, release checklist
- 1x Product Owner (Part-time, 50%): Requirements, acceptance

**Week 9-14 (Phase 2):**
- 1x Backend Dev (Full-time): DB, Archiver, Caching
- 1x Frontend Dev (Part-time, 25%): Testing framework
- 1x DevOps Eng (Part-time, 50%): CI/CD, security audit
- 1x QA (Full-time): Test suite, security testing
- 1x Product Owner (Part-time, 50%): Acceptance, risk management

**Week 15-20 (Phase 3):**
- 1x Backend Dev (Part-time, 50%): Real-time, anomalies, cohorts
- 1x Frontend Dev (Full-time): Visualizations, widgets, admin UI
- 1x DevOps Eng (Part-time, 25%): Monitoring, deployment
- 1x QA (Full-time): Integration testing, UX testing
- 1x Product Owner (Full-time): Feature scope, UAT, go-live

---

## Budget Estimate

```
Phase 1 (Weeks 1-8):   8 weeks × 3.5 devs × €6,000/week  = €168,000
Phase 2 (Weeks 9-14):  6 weeks × 3.5 devs × €6,000/week  = €126,000
Phase 3 (Weeks 15-20): 6 weeks × 3.5 devs × €6,000/week  = €126,000
─────────────────────────────────────────────────────────
TOTAL (20 weeks):      20 weeks × 3.5 devs × €6,000/week = €420,000

Infrastructure:
├─ Testing DB, caching, CI/CD                            = €10,000
├─ Security audit, penetration testing                   = €20,000
└─ Training, documentation, knowledge transfer           = €10,000
─────────────────────────────────────────────────────────
GRAND TOTAL:                                             €460,000

Budget per plugin: €460,000 / 3 = €153,000 per plugin
Cost per feature: €460,000 / 13 plugins/features ≈ €35,000

ROI (Comparison to Premium Modules):
├─ Matomo Premium: €50,000/year × 3 modules             = €150,000/year
├─ Break-even: €460,000 / €150,000/year                 ≈ 3 years
└─ Positive ROI: After year 4 onwards (€150k/year savings)
```

---

## Risk Register

| Risk | Phase | Likelihood | Impact | Mitigation |
|------|-------|-----------|--------|-----------|
| DB performance degradation | 2 | Medium | High | Load testing, index strategy |
| Segment complexity overrun | 2 | Medium | Medium | Early spike, spike on proof-of-concept |
| Test coverage insufficient | 2 | Low | High | Mandatory 70% coverage gate |
| Cache invalidation bugs | 2 | Medium | Medium | Cache testing specialist |
| Visualization library issues | 3 | Low | Medium | Evaluate 3+ libraries early |
| ML model not detecting anomalies | 3 | Medium | Medium | Manual baseline + thresholds |
| Team burnout (20 weeks) | 1-3 | High | High | Agile retrospectives, adjust scope |
| Scope creep | 1-3 | High | Medium | Strict scope gate at SB-013 |

---

## Success Criteria Summary

### Phase 1 ✅
- [x] 3 plugins production-ready
- [x] Retention jobs functioning
- [x] Operational docs complete
- [x] All 12 tickets done on time

### Phase 2 (Go/No-Go at week 14)
- [ ] P95 response time < 500ms
- [ ] Archive process < 30s
- [ ] 70% test coverage
- [ ] Security audit clean
- [ ] Zero data loss incidents
- **Decision:** Proceed to Phase 3 OR Extend Phase 2

### Phase 3 (Go/No-Go at week 20)
- [ ] All visualizations working
- [ ] Real-time data < 1min latency
- [ ] Anomaly detection tuned
- [ ] Admin UI fully functional
- [ ] Team trained & certified
- **Decision:** Release v1.0 OR Patch cycle

---

## Communication Plan

### Weekly Sync (Mondays 10 AM)
- Standup: Progress, blockers, risks
- Burndown chart review
- Sprint planning (next week)

### Bi-weekly Executive Briefing (2nd Wed)
- Phase status & budget tracking
- Key decisions & escalations
- Demo of completed features

### End-of-Phase Steering Committee Review
- Phase completion sign-off
- Go/no-go decision for next phase
- Budget adjustments & resource reallocation

---

## Appendix: Comparison to Premium Tools

| Feature | Premium Tools | Our Plugin Suite (v1.0) |
|---------|---------------|------------------------|
| Path Analysis | Yes | Yes (SB-005) |
| Confidence Scoring | Yes | Yes (SB-009) |
| Consent Gating | Partial | Yes (SB-010) |
| Device Quality | Yes | Yes (SB-007) |
| Client Hints | No | Yes (SB-008) |
| Custom Reports | Yes | Yes (SB-019) |
| Real-time Data | Yes (Premium) | Yes (SB-021) |
| Cohort Analysis | Premium feature | Yes (SB-022) |
| Anomaly Detection | Premium feature | Yes (SB-023) |
| **Annual Cost** | €50,000/module | €0 (sunk cost) |
| **Customization** | Limited | Full control |
| **Data Residency** | Vendor | On-premise |

---

## Document Index

- **Phase 1 Documentation:**
  - ROADMAP_KONZEPT.md — High-level program planning
  - KONZEPT_DETAILLIERT.md — Detailed architecture & design
  - DATA_CONTRACTS.md — API specifications
  - KPI_BASELINE_2026-06-25.md — Baseline metrics
  - SOLOBOARD.md — Ticket tracking

- **Phase 1 Operational Docs:**
  - INSTALLATION_GUIDE.md — Deployment procedure
  - RUNBOOK.md — Daily operations
  - RELEASE_CHECKLIST.md — Go-live validation
  - HEALTH_CHECK.md — Monitoring & verification
  - TROUBLESHOOTING.md — Issue resolution

- **Phase 2-3 Planning:**
  - PHASE_2_3_ROADMAP.md — Detailed ticket breakdown
  - This file (MASTER_ROADMAP.md)

---

**Document Status:** Final  
**Last Updated:** 2026-06-25  
**Next Review:** 2026-07-27 (End of Phase 2)  
**Approval Status:** Pending Stakeholder Sign-off

---

## Sign-Off

| Role | Name | Date | Status |
|------|------|------|--------|
| Technical Lead | | | ☐ Approved |
| Product Owner | | | ☐ Approved |
| Finance | | | ☐ Approved |
| Executive Sponsor | | | ☐ Approved |

**Phase 1 Sign-off:** Completed ✓ (2026-06-25)  
**Phase 2 Kickoff:** Ready for 2026-06-26  
**Phase 3 Readiness:** Pending Phase 2 completion  

---

*For questions or updates, contact: Matomo Plugin Development Team*
