# Detailliertes Konzept - Matomo Erweiterungsprogramm 2026

## 1. Executive Summary

Dieses Konzept beschreibt die eigenstaendige Entwicklung von drei Matomo-Erweiterungen:

1. VisitorFlowIntelligence
2. GeoPrecision
3. DeviceIntelligence

Ziel ist der Aufbau eines belastbaren, datenschutzkonformen Analyse-Stacks fuer:

- Besucherstroeme (Pfad- und Drop-off-Analysen)
- Standortqualitaet und Standortgenauigkeit
- Geraete- und Client-Qualitaet

Das Programm ist als inkrementelle Lieferung angelegt: erst MVP je Plugin, danach Hardening und Ausbau.

## 2. Problemstellung und Zielsetzung

### 2.1 Aktuelle Situation

- Matomo liefert bereits Basisdaten fuer Orte, Geraete und Besucherprofile.
- Es bestehen jedoch Qualitaetsluecken (z. B. "unbekannt" in Region/Geraetedaten).
- Besucherfluss-Analysen sind ohne dediziertes Flow-Modell nur eingeschraenkt nutzbar.
- Premium-Funktionen decken Teile des Bedarfs ab, binden aber budgetaer und strategisch.

### 2.2 Soll-Zustand

- Eigene Plugins liefern priorisierte Funktionen ohne Lock-in.
- Reports sind auf operative Entscheidungen ausgerichtet.
- Datenqualitaet ist messbar verbessert und ueber KPIs steuerbar.
- Datenschutz ist technisch und prozessual verankert.

## 3. Scope-Definition

### 3.1 In Scope

- Eigenentwicklung von 3 Plugins inkl. API, Reports, Widgets.
- Datenqualitaetsmetriken und Monitoring.
- Consent- und Retention-Logik fuer neue Datenelemente.
- Dokumentation, Tests, Betriebsuebergabe.

### 3.2 Out of Scope

- Vollstaendige Replik von Session Recording/Heatmaps.
- Vollstaendiger Ersatz saemtlicher Premium-Plugins.
- Historische Backfill-Perfektion fuer alte Zeitraeume.

## 4. Fachliches Zielmodell

## 4.1 Plugin A: VisitorFlowIntelligence

Kernfaehigkeiten:

- Top-Pfade je Segment und Zeitraum.
- Step-by-step Transition-Raten.
- Drop-off je Schritt und Einstiegsseite.
- Vergleich "aktueller Zeitraum vs. Referenzzeitraum".

Fachliche Fragen, die beantwortet werden:

- Welche Pfade fuehren mit hoher Wahrscheinlichkeit zur Conversion?
- Wo brechen Nutzer mit welcher Quelle/Geraeteklasse ab?
- Welche Landingpages erzeugen qualitativ bessere Flows?

## 4.2 Plugin B: GeoPrecision

Kernfaehigkeiten:

- Confidence-Score fuer Standortdaten (high/medium/low).
- Herkunftstrennung der Standortdaten:
  - IP-basiert
  - consent-basiert praezise
  - manuell/override
- Verdichtete Darstellung ueber Geohash-Cluster.

Fachliche Fragen, die beantwortet werden:

- In welchen Regionen sind die Daten zuverlaessig?
- Wo ist die Genauigkeit zu niedrig fuer regionale Entscheidungen?
- Wie wirkt sich Consent auf Standortabdeckung aus?

## 4.3 Plugin C: DeviceIntelligence

Kernfaehigkeiten:

- Erweiterte Device-Dimensionen.
- Unknown-Rate-Monitoring pro Dimension.
- Korrelation von Device-Klassen mit Pfad-/Drop-off-Mustern.

Fachliche Fragen, die beantwortet werden:

- Welche Device-Segmente performen unterdurchschnittlich?
- Wo fehlen entscheidende Device-Signale?
- Verbessert Client-Hints-Ingestion die Segmentqualitaet?

## 5. Zielarchitektur

## 5.1 Logische Architektur

1. Ingestion Layer
- Matomo JS Tracking API
- Matomo Tracking HTTP API
- Optionale Client Hints (uadata)

2. Enrichment Layer
- Plugin-Logik fuer Confidence, Device-Klassifikation, Pfadaggregation

3. Storage Layer
- Core Matomo Log-Daten
- Plugin-spezifische Tabellen nur fuer Mehrwertdaten

4. Processing Layer
- Archivierung/Record Builder fuer performante Reports

5. Presentation Layer
- Reports, Widgets, Segmente, API-Endpunkte

## 5.2 Technische Prinzipien

- Keine schweren Live-Queries fuer grosse Reports.
- Aggregation standardisiert ueber Archiver.
- Klare Trennung zwischen Rohdaten, Aggregaten und Visualisierung.
- Versionierte Migrationsskripte fuer DB-Aenderungen.

## 6. Datenmodell (konzeptionell)

## 6.1 VisitorFlowIntelligence

Entitaeten:

- flow_path
  - idsite
  - period/date
  - segment_hash
  - entry_action
  - step_n_action
  - transitions
  - dropoff_count

- flow_transition
  - source_action
  - target_action
  - transition_count
  - transition_rate

## 6.2 GeoPrecision

Entitaeten:

- geo_quality_event
  - idvisit/idsite
  - source_type
  - confidence_level
  - geohash_precision
  - country/region/city

- geo_quality_aggregate
  - period/date
  - unknown_rate_region
  - unknown_rate_city
  - confidence_distribution

## 6.3 DeviceIntelligence

Entitaeten:

- device_quality_event
  - idvisit/idsite
  - device_type
  - brand
  - model
  - os/browser
  - hint_presence

- device_quality_aggregate
  - period/date
  - unknown_rate_brand
  - unknown_rate_model
  - unknown_rate_os

## 7. Datenschutz- und Compliance-Konzept

## 7.1 Privacy by Design

- Datensparsamkeit: nur fuer Use Cases notwendige Felder.
- Zweckbindung: keine verdeckte Profilbildung.
- Transparenz: Dokumentation der Datenherkunft und Verarbeitungszwecke.

## 7.2 Consent

- Praezise Standortdaten nur bei explizitem Consent.
- Ohne Consent: nur IP-basierte, bereits vorhandene Matomo-Geodaten nutzen.

## 7.3 Retention und Loeschung

- Plugin-spezifische Rohdaten mit kurzer Aufbewahrung.
- Aggregatdaten laenger haltbar, ohne Personenbezug.
- Geplante Loeschjobs mit dry-run und Logging.

## 7.4 Rechte der Betroffenen

- Exportierbarkeit neuer Datenanteile sicherstellen.
- Loeschbarkeit neuer Datenanteile sicherstellen.

## 8. Reporting- und KPI-Modell

## 8.1 KPI-Kategorien

1. Data Quality
- Unknown-Rate Region/Stadt
- Unknown-Rate Device Model/Brand
- Anteil Datensaetze mit hoher Confidence

2. Performance
- API P95 pro Report
- Archivierungsdauer je Plugin

3. Business Impact
- Identifizierte Top-Drop-off-Schritte
- Anzahl umgesetzter Optimierungsmassnahmen
- Veraenderung relevanter Conversion-Signale

## 8.2 Zielwerte (initial)

- Unknown-Rate Device Model: -20 Prozentpunkte gg. Baseline in 8-12 Wochen.
- Unknown-Rate Region: -15 Prozentpunkte gg. Baseline in 8-12 Wochen.
- Flow-Report P95: unter 1500 ms fuer Standardfilter.

## 9. Umsetzungsplanung (Roadmap in Sprints)

## Sprint 0 (Woche 1)

- Plugin-Skeletons, Namensraeume, Build/Run-Doku.
- KPI-Baseline erheben.
- Datenschutz-Checkliste finalisieren.

Output:
- lauffaehige Grundstruktur
- Baseline-Dokument

## Sprint 1-2 (Wochen 2-4): VisitorFlowIntelligence MVP

- Flow-Domainmodell + Aggregationslogik.
- API Endpoint(s) fuer Top-Pfade.
- Erster Report + Widget.

Output:
- nutzbarer Flow-MVP

## Sprint 3-4 (Wochen 5-7): DeviceIntelligence MVP

- Unknown-Rate Reports.
- Optionale uadata-Ingestion.
- Qualitaetsdashboard Device.

Output:
- messbare Device-Qualitaetssteuerung

## Sprint 5-6 (Wochen 8-10): GeoPrecision MVP

- Confidence-Scoring.
- Geohash-Aggregation.
- Consent-Gating fuer Praezisionsdaten.

Output:
- standortbezogene Entscheidungsqualitaet mit Confidence

## Sprint 7 (Woche 11): Hardening

- Performance-Tuning.
- Security/Privacy-Tests.
- Fehlerhandling und Monitoring.

## Sprint 8 (Woche 12): Release

- Betriebsdoku, Runbook, Rollback.
- Abnahme und Produktionsfreigabe.

## 10. Qualitaetsstrategie

## 10.1 Testpyramide

- Unit-Tests fuer Kernlogik (Scoring, Aggregation).
- Integrations-Tests fuer API und Archivierung.
- UI-Smoke-Tests fuer Kernreports.

## 10.2 Nicht-funktionale Tests

- Lasttest fuer groessere Zeitraeume/Segmente.
- Datenschutz-Checks (Consent-Pfade, Loeschpfade).
- Resilienztests bei fehlenden Feldern.

## 10.3 Definition of Done je Ticket

- Implementiert
- Review abgeschlossen
- Tests gruen
- Doku aktualisiert
- KPI/Monitoring beruecksichtigt

## 11. Betriebs- und Monitoring-Konzept

- Logging je Plugin mit klaren Fehlercodes.
- Dashboard fuer Health-KPIs:
  - API Fehlerquote
  - Archivierungsdauer
  - Datenvolumen je Plugin-Tabelle
- Alerting bei Schwellwertueberschreitung.

## 12. Risikoanalyse

- Risiko: Scope Creep
  - Massnahme: MVP-Disziplin, Change-Requests in Backlog.

- Risiko: Performance bei grossen Segmenten
  - Massnahme: Voraggregation, Paging, Caching.

- Risiko: Datenschutzverstoss bei Standort
  - Massnahme: Consent-Gate, Feldminimierung, Retention-Limits.

- Risiko: Fehlende Datenqualitaet trotz Entwicklung
  - Massnahme: Quality-KPIs und kontinuierliches Tuning.

## 13. Abhaengigkeiten

- Zugriff auf Matomo Admin/Plugin Deployment.
- Zugang zu Tracking-Code-Einbindung im Frontend.
- Abstimmung mit Datenschutz/Legal fuer Consent-Text und Zwecke.

## 14. Offene Entscheidungen

- Exakte Zielwerte fuer Unknown-Rate je Dimension.
- Detailgrad der Geohash-Praezision pro Report.
- Priorisierte Segmente fuer erste Flow-Auswertung.

## 15. Abnahmekriterien Gesamtprogramm

Das Programm gilt als erfolgreich, wenn:

1. Alle drei MVP-Plugins produktiv laufen.
2. Unknown-Raten gegenueber Baseline messbar verbessert sind.
3. Fachliche Teams aktiv mit den neuen Reports arbeiten.
4. Datenschutz- und Loeschprozesse nachweislich funktionieren.
5. Betrieb und Release-Prozess dokumentiert und reproduzierbar sind.

## 16. Direkt anschliessende Next Steps

1. Plugin-Skeletons unter plugins anlegen (SB-001).
2. Baseline-Dokument aus aktueller Instanz erstellen (SB-003).
3. Data Contracts als separates Technikdokument ausarbeiten (SB-002).
4. Sprint-Backlog fuer VisitorFlowIntelligence final schneiden.
