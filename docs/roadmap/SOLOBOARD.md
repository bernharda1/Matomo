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

## Diese Woche - Taktplan (Solo)

Tag 1:

- SB-001 starten und abschliessen
- SB-003 starten

Tag 2:

- SB-003 abschliessen
- SB-004 umsetzen

Tag 3:

- SB-005 umsetzen

Tag 4:

- SB-006 umsetzen

Tag 5:

- Stabilisieren, testen, Dokumentation aktualisieren

## Retro-Notizen

- Was lief gut:
- Was hat gebremst:
- Was wird naechste Woche verbessert:
