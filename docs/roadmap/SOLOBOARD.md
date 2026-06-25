# Solo-Board - Matomo Erweiterung

Board-Regeln:

- WIP-Limit In Progress: 2 Tickets.
- Ticketgroesse: max. 1-2 Tage Aufwand.
- Jedes Ticket braucht Akzeptanzkriterium.
- Blocker sofort markieren und loesen.

## Backlog

(Keine ausstehenden Tickets)

## In Progress

- [ ] (leer)

## Blocked

- [ ] (leer)

## Done

- [x] SB-000 Roadmap-Konzept erstellt
  - Ergebnis: `docs/roadmap/ROADMAP_KONZEPT.md`

- [x] SB-001 Projektstruktur fuer 3 Plugins angelegt
  - Ergebnis: `plugins/VisitorFlowIntelligence`, `plugins/GeoPrecision`, `plugins/DeviceIntelligence`

- [x] SB-002 Data Contracts und Naming-Konvention finalisiert
  - Ergebnis: `docs/roadmap/DATA_CONTRACTS.md`

- [x] SB-003 KPI-Baseline aus Ist-Daten erfasst
  - Ergebnis: `docs/roadmap/KPI_BASELINE_2026-06-25.md`

- [x] SB-004 Flow-Domainmodell definiert
  - Ergebnis: `docs/roadmap/FLOW_DOMAINMODELL.md`, `plugins/VisitorFlowIntelligence/Domain/*`

- [x] SB-005 Flow API Endpoint (MVP) bereitgestellt
  - Ergebnis: `plugins/VisitorFlowIntelligence/API.php`, `plugins/VisitorFlowIntelligence/Infrastructure/FlowEventRepository.php`, `plugins/VisitorFlowIntelligence/Service/FlowPathAggregator.php`

- [x] SB-006 Flow Report in Matomo UI registriert
  - Ergebnis: `plugins/VisitorFlowIntelligence/VisitorFlowIntelligence.php`, `plugins/VisitorFlowIntelligence/Controller.php`, `plugins/VisitorFlowIntelligence/templates/index.twig`

- [x] SB-007 Device Quality Report (Unknown-Rate) umgesetzt
  - Ergebnis: `plugins/DeviceIntelligence/API.php`, `plugins/DeviceIntelligence/Infrastructure/DeviceQualityRepository.php`, `plugins/DeviceIntelligence/Controller.php`, `plugins/DeviceIntelligence/templates/index.twig`

- [x] SB-008 Client-Hints Verarbeitung eingebaut
  - Ergebnis: `plugins/DeviceIntelligence/Service/ClientHintsMapper.php`, `plugins/DeviceIntelligence/API.php`, `plugins/DeviceIntelligence/templates/index.twig`

- [x] SB-009 Geo Confidence Scoring implementiert
  - Ergebnis: `plugins/GeoPrecision/Service/GeoConfidenceScorer.php`, `plugins/GeoPrecision/Infrastructure/GeoQualityRepository.php`, `plugins/GeoPrecision/API.php`, `plugins/GeoPrecision/Controller.php`, `plugins/GeoPrecision/templates/index.twig`

- [x] SB-010 Consent-Gate fuer Praezisionsstandort integriert
  - Ergebnis: `plugins/GeoPrecision/Service/ConsentGatekeeper.php`, `plugins/GeoPrecision/API.php`, `plugins/GeoPrecision/Controller.php`, `plugins/GeoPrecision/templates/index.twig`

- [x] SB-011 Loesch- und Retention-Job fuer neue Daten
  - Ergebnis: RetentionManager + RetentionTask pro Plugin, CLI-Commands mit dry-run, ScheduledTaskScheduler-Integration, `docs/roadmap/SB-011_RETENTION_JOBS_GUIDE.md`

- [x] SB-012 Betriebsdoku + Release-Checkliste v1.0
  - Ergebnis: `INSTALLATION_GUIDE.md`, `RUNBOOK.md`, `RELEASE_CHECKLIST.md`, `HEALTH_CHECK.md`, `TROUBLESHOOTING.md`

### Phase 2 (Hardening & Optimization)

- [x] SB-013 Database Layer Optimization
  - Ergebnis: 3 raw data tables (plugin_visitorflow_raw, plugin_geoprecision_raw, plugin_deviceintelligence_raw)
  - Migration infrastructure (MigrationManager, versioning, rollback)
  - Performance baseline tests (< 500ms P95 target)
  - PR #1 merged to master
  - Status: ✅ COMPLETE (2026-06-25)

## Diese Woche - Taktplan (Phase 2)

Sprint 1 (Week 9-10): SB-013 Database Layer ✅ DONE

Sprint 2 (Week 11-12):
- [ ] SB-014.1-3 Archiver Integration (pre-aggregation logic)
- [ ] SB-015.1-4 Caching Layer (Redis/Memcached integration)

Sprint 3 (Week 12-13):
- [ ] SB-016 Segment Support (enable dimension filtering)

Sprint 4 (Week 13-14):
- [ ] SB-017 Automated Testing (PHPUnit + CI/CD)
- [ ] SB-018 Security Hardening (OWASP compliance)

## Retro-Notizen

- Was lief gut:
- Was hat gebremst:
- Was wird naechste Woche verbessert:
