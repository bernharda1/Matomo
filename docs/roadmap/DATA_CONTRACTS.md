# Data Contracts - Matomo Erweiterung

Version: 0.1.0
Status: Draft for implementation
Owner: Analytics Engineering

## 1. Ziel

Dieses Dokument definiert verbindliche Datenvertraege fuer:

- VisitorFlowIntelligence
- GeoPrecision
- DeviceIntelligence

Jeder Vertrag beschreibt:

- Feldname
- Datentyp
- Pflicht/Optional
- Quelle
- Validierung
- Datenschutzklasse

## 2. Globale Konventionen

## 2.1 Benennung

- Tabellen: snake_case, Praefix nach Plugin
- API Felder: camelCase
- Segment-Schluessel: lower_snake_case
- Enums: lowercase strings

## 2.2 Zeit und IDs

- Zeitstempel in UTC
- Datumsfelder im Format YYYY-MM-DD
- idSite als int > 0
- idVisit als int > 0
- idVisitor als hex string oder binaere Matomo-Repr. (intern)

## 2.3 Null/Unknown Regeln

- Unknown-Werte werden nicht als null gespeichert, sondern als definierter Enum-Wert unknown
- null ist nur fuer technisch nicht vorhandene optionale Felder erlaubt

## 2.4 Datenschutzklassifizierung

- Class A: Nicht personenbezogen aggregiert
- Class B: Pseudonymisiert (visit-/visitor-bezogen)
- Class C: Potenziell sensitiv (praezise Standortdaten)

## 3. Plugin: VisitorFlowIntelligence

## 3.1 Input Contract (FlowEvent)

Quelle:
- Matomo log_link_visit_action
- Matomo log_action

Felder:

1. idSite
- Typ: int
- Pflicht: ja
- Validierung: > 0
- Datenschutzklasse: B

2. idVisit
- Typ: int
- Pflicht: ja
- Validierung: > 0
- Datenschutzklasse: B

3. idVisitor
- Typ: string
- Pflicht: optional
- Validierung: hex length 16 (falls gesetzt)
- Datenschutzklasse: B

4. serverTime
- Typ: datetime (UTC)
- Pflicht: ja
- Validierung: ISO-kompatibel, nicht in ferner Zukunft
- Datenschutzklasse: B

5. actionUrl
- Typ: string
- Pflicht: ja
- Validierung: max 2048, gueltige URL oder normalisierte Aktions-URL
- Datenschutzklasse: B

6. actionName
- Typ: string
- Pflicht: optional
- Validierung: max 1024
- Datenschutzklasse: B

7. pageviewPosition
- Typ: int
- Pflicht: ja
- Validierung: >= 1
- Datenschutzklasse: B

8. refActionUrl
- Typ: string
- Pflicht: optional
- Validierung: max 2048
- Datenschutzklasse: B

## 3.2 Aggregate Contract (FlowTransitionAggregate)

1. idSite (int, required)
2. period (enum: day, week, month, year, range)
3. date (string YYYY-MM-DD oder range)
4. segmentHash (string, required)
5. sourceAction (string, required)
6. targetAction (string, required)
7. transitions (int, >= 0)
8. transitionRate (float 0..1)
9. dropoffCount (int, >= 0)
10. dropoffRate (float 0..1)

Datenschutzklasse:
- A bei Aggregatdaten

## 3.3 API Contract (Flow API)

Endpoint (konzeptuell):
- module=API&method=VisitorFlowIntelligence.getTopPaths

Request Parameter:

- idSite: int, required
- period: enum, required
- date: string, required
- segment: string, optional
- maxDepth: int, optional, default 5, range 2..12
- limit: int, optional, default 20, range 1..200

MVP Hinweis (SB-005):
- Segment ist als Feld vorgesehen, wird im aktuellen MVP jedoch noch nicht angewendet.
- Bei gesetztem segment wirft der Endpoint derzeit eine DomainException.

Response Schema:

- meta:
  - idSite (int)
  - period (string)
  - date (string)
  - segment (string)
  - generatedAt (datetime UTC)
- paths: array
  - path (array[string])
  - visits (int)
  - share (float 0..1)
  - dropoffAtStep (int)

## 4. Plugin: GeoPrecision

## 4.1 Input Contract (GeoInput)

Quelle:
- Matomo UserCountry-Daten
- Optional: consent-basierte Browser-Geolocation
- Optional: Tracking API Overrides

Felder:

1. idSite (int, required, >0)
2. idVisit (int, required, >0)
3. serverTime (datetime UTC, required)
4. countryCode (string, optional, ISO-3166-1 alpha-2 oder unknown)
5. regionCode (string, optional, ISO-3166-2 Teil oder unknown)
6. cityName (string, optional, max 255 oder unknown)
7. latitude (float, optional)
8. longitude (float, optional)
9. sourceType (enum, required): ip, consent_precise, override
10. consentState (enum, required): granted, denied, not_applicable, unknown

Validierungen:

- latitude nur im Bereich -90..90
- longitude nur im Bereich -180..180
- Wenn sourceType=consent_precise, dann consentState muss granted sein
- Wenn consentState != granted, dann precisionLevel darf nicht exact sein

## 4.2 Derived Contract (GeoQualityRecord)

1. precisionLevel (enum): country, region, city, approx, exact, unknown
2. confidenceLevel (enum): high, medium, low
3. confidenceScore (int 0..100)
4. geohash (string, optional)
5. geohashLevel (int, optional, range 3..8)

Regeln:

- exact nur bei consentState=granted und sourceType=consent_precise
- ip-basierte Daten maximal bis approx/city je Datenlage

MVP Hinweis (SB-009):
- Implementiert ist `GeoPrecision.getConfidenceSummary` mit Confidence-Scoring basierend auf Precision-Level und Source-Type.
- Confidence Levels: high, medium, low
- Precision Levels: exact, approx, city, region, country, unknown
- Confidence Score: 0-100 basierend auf Dimension-Abdeckung und Quelle.

SB-010 Consent Gate:
- ConsentGatekeeper maskt Precision-Level zu 'country' wenn kein explizites Consent für "precise_location" vorhanden.
- Nur Country-Code wird ohne Consent verarbeitet; City/Region/Coordinates werden maskiert.
- API-Parameter `hasConsentForPreciseGeo` steuert Gating; wirft DomainException wenn Consent erforderlich aber nicht gesetzt.

SB-011 Data Retention:
- Raw event data: 30-day retention (personal data, early deletion)
- Aggregate data: 365-day retention (anonymized, supports annual comparisons)
- Scheduled daily purge jobs via ScheduledTaskScheduler hook
- Dry-run capable CLI commands for testing (geoprecision:test-retention, etc.)
- Comprehensive logging of all purge operations

SB-012 Operational Documentation:
- Installation Guide: Step-by-step plugin deployment, configuration, API verification
- Runbook: Daily operations, quick reference, standard procedures, troubleshooting response
- Release Checklist: Pre/during/post-release validation, rollback criteria and procedures
- Health Check: Automated script + manual verification procedures for all components
- Troubleshooting: Common issues, diagnostics, solutions, performance tuning, support resources

## 4.3 API Contract (Geo Quality API)

Endpoint (konzeptuell):
- VisitorFlowIntelligence getrennt
- module=API&method=GeoPrecision.getQualitySummary

Request:

- idSite (int, required)
- period (enum, required)
- date (string, required)
- segment (string, optional)

Response:

- totals:
  - visits (int)
  - unknownRegionRate (float 0..1)
  - unknownCityRate (float 0..1)
- confidenceDistribution:
  - high (int)
  - medium (int)
  - low (int)
- sourceDistribution:
  - ip (int)
  - consent_precise (int)
  - override (int)

MVP Hinweis (SB-007):
- Implementiert ist aktuell `DeviceIntelligence.getQualitySummary` mit Unknown-Rate pro Device-Dimension.
- Segment ist als Feld vorgesehen, wird im aktuellen MVP jedoch noch nicht angewendet.

## 5. Plugin: DeviceIntelligence

## 5.1 Input Contract (DeviceInput)

Quelle:
- Matomo DevicesDetection
- Optional: Client Hints uadata

Felder:

1. idSite (int, required)
2. idVisit (int, required)
3. serverTime (datetime UTC, required)
4. deviceType (enum): desktop, smartphone, tablet, phablet, tv, bot, unknown
5. brand (string, optional, max 128, default unknown)
6. model (string, optional, max 128, default unknown)
7. osName (string, optional, default unknown)
8. osVersion (string, optional)
9. browserName (string, optional, default unknown)
10. browserVersion (string, optional)
11. resolution (string, optional, pattern ^[0-9]{2,5}x[0-9]{2,5}$ or unknown)
12. clientHintsPresent (bool, required)
13. uaDataRaw (json, optional, nur wenn clientHintsPresent=true)

Validierungen:

- Unknown-Felder normalisiert auf unknown
- uaDataRaw max payload 8KB
- Strings werden getrimmt

MVP Hinweis (SB-008):
- `uadata` wird als JSON verarbeitet und in Device-Dimensionen ueberfuehrt.
- Aktuell erfolgt das Mapping im Report-/API-Pfad; persistente Speicherung folgt in einem spaeteren Ticket.

## 5.2 Derived Contract (DeviceQualityRecord)

1. unknownBrand (bool)
2. unknownModel (bool)
3. unknownOs (bool)
4. unknownBrowser (bool)
5. qualityScore (int 0..100)

Scoring (MVP Vorschlag):

- Start 100
- unknownBrand: -20
- unknownModel: -35
- unknownOs: -20
- unknownBrowser: -15
- keine Client Hints bei mobile: -10
- Minimum 0

## 5.3 API Contract (Device Quality API)

Endpoint (konzeptuell):
- module=API&method=DeviceIntelligence.getQualitySummary

Request:

- idSite (int, required)
- period (enum, required)
- date (string, required)
- segment (string, optional)

Response:

- totals:
  - visits (int)
  - unknownBrandRate (float 0..1)
  - unknownModelRate (float 0..1)
  - unknownOsRate (float 0..1)
- qualityBuckets:
  - high (int)
  - medium (int)
  - low (int)
- byDeviceType: array
  - deviceType (string)
  - unknownModelRate (float)

## 6. Event und Hook Mapping (Matomo)

Hinweis:
- Die konkrete Hook-Auswahl kann je Implementierungsdetail angepasst werden.

Empfohlene Einsatzpunkte:

1. Tracking/Enrichment:
- Tracker.end
- Live.addVisitorDetails
- Segment.addSegments

2. Reporting/Exposure:
- Report.addReports
- Widget.addWidgetConfigs
- API.getReportMetadata.end

3. Archivierung:
- Archiver.addRecordBuilders
- CoreAdminHome.archiveReports.start
- CoreAdminHome.archiveReports.complete

4. Datenschutz/Retention:
- PrivacyManager.deleteDataSubjects
- PrivacyManager.deleteLogsOlderThan

## 7. Retention und Loeschkontrakte

## 7.1 Aufbewahrungsregeln (MVP)

- Event-/Rohnahe Plugin-Daten: 90 Tage
- Aggregatdaten: 400 Tage
- Consent-praezise Geo-Daten: 30 Tage (wenn rechtlich zulaessig)

## 7.2 Loeschjob Contract

Request:
- dryRun (bool, default true)
- olderThanDays (int, required)
- scope (enum): geo_raw, device_raw, flow_raw, all

Response:
- deletedRows (int)
- affectedTables (array[string])
- runtimeMs (int)
- dryRun (bool)

## 8. Datenqualitaetsregeln

1. Vollstaendigkeit:
- Pflichtfelder fehlen nicht.

2. Konsistenz:
- sourceType und consentState sind logisch konsistent.

3. Gueltigkeit:
- Werte in erlaubten Bereichen/Enums.

4. Eindeutigkeit:
- Aggregat-Schluessel sind pro Periode/Segment eindeutig.

5. Aktualitaet:
- Archivierte Daten sind zeitnah zum Tracking verfuegbar.

## 9. Akzeptanzkriterien fuer SB-002

SB-002 gilt als done, wenn:

1. Dieses Dokument im Repo liegt.
2. Alle 3 Plugins Input/Aggregat/API-Vertraege enthalten.
3. Consent- und Retention-Regeln dokumentiert sind.
4. Hook-Mapping fuer Umsetzung enthalten ist.
5. Team kann ohne weitere Grundsatzfragen mit SB-004/SB-005 starten.

## 10. Offene Punkte

- Finaler Wert fuer geohashLevel je Report (3..8).
- Ob uaDataRaw persistiert oder nur abgeleitete Felder gespeichert werden.
- Exakter qualityScore-Algorithmus nach Baseline-Analyse.
