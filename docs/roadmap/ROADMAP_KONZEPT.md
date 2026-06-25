# Matomo Erweiterungsprogramm 2026

## 1. Zielbild

Dieses Programm erweitert Matomo nach Best Practice um drei Kernfaehigkeiten:

1. Besucherstroeme (User Flows) transparent machen.
2. Standortdaten qualitaetsgesichert und datenschutzkonform verbessern.
3. Geraeteinformationen konsistent, tiefer und auswertbar machen.

Ergebnisziel nach Umsetzung:

- Bessere Entscheidungsgrundlagen fuer Kampagnen, Inhalte und Conversion-Optimierung.
- Messbar weniger `unbekannt` bei Standort-/Geraetedimensionen.
- Eigene, wartbare Plugins statt Abhaengigkeit von kostenpflichtigen Add-ons.

## 2. Scope

### In Scope

- Plugin A: `VisitorFlowIntelligence`
- Plugin B: `GeoPrecision`
- Plugin C: `DeviceIntelligence`
- Basis-Dashboard mit Kern-KPIs, Segmenten und Reports
- Technische und fachliche Dokumentation
- Datenschutz- und Loeschkonzept fuer neue Daten

### Out of Scope (Phase spaeter)

- Vollstaendige Session-Recording-Engine
- Vollstaendige Heatmap-Engine
- Kompletter Ersatz aller Premium-Features

## 3. Leitprinzipien (Best Practice)

- Privacy by Design: Datensparsamkeit, Einwilligung, Loeschbarkeit, Transparenz.
- Build iterativ: zuerst MVP pro Plugin, danach Qualitaets-/UX-Ausbau.
- Archivierung statt teurer Live-Abfragen fuer grosse Reports.
- Quality Gates: Code Review, Tests, Performance-Check, Security-Check.
- Reproduzierbare Releases mit Versionierung und Migrationsskripten.

## 4. Zielarchitektur

- Tracking Layer:
  - JavaScript Tracking API + optionale Client Hints (`uadata`) + Custom Dimensions.
- Storage Layer:
  - Matomo Log-Daten als Basis.
  - Plugin-spezifische Tabellen nur dort, wo noetig.
- Processing Layer:
  - Archiver/Record Builder fuer vorberechnete Reports.
- Reporting Layer:
  - Eigene Reports, Widgets, Segmente, API-Endpunkte.
- Governance Layer:
  - Consent-Checks, Aufbewahrungsfristen, Loeschjobs.

## 5. Roadmap (12 Wochen)

## Phase 0: Setup und Discovery (Woche 1)

Ziele:

- Entwicklungsrahmen stabilisieren.
- KPI-Baseline erfassen.

Deliverables:

- Technisches Zielbild final.
- Datenschutz-Checkliste.
- KPI-Baseline dokumentiert:
  - Unknown-Rate Geraetemodell
  - Unknown-Rate Region/Stadt
  - Anzahl nutzbarer Pfadanalysen

## Phase 1: Visitor Flow MVP (Wochen 2-4)

Ziele:

- Kernfunktion fuer Besucherstroeme bereitstellen.

Deliverables:

- Plugin `VisitorFlowIntelligence` mit:
  - Top Entry -> Next Step -> Exit
  - Drop-off pro Schritt
  - Segmentfilter (Kanal, Geraet, Standort)
- 1 Dashboard-Widget + 1 Detailreport
- API fuer Flow-Daten

Erfolgskriterium:

- Fachbereich kann Top 10 Pfade und groesste Drop-off-Stellen sicher auswerten.

## Phase 2: Device Intelligence MVP (Wochen 5-7)

Ziele:

- Geraetedaten verbessern und Unknown-Rate reduzieren.

Deliverables:

- Plugin `DeviceIntelligence` mit:
  - Erweiterte Device-Dimensionen
  - Qualitaetsreport (Unknown-Rate je Dimension)
  - Client-Hints-Ingestion (falls vorhanden)
- Reports fuer Modell-/Markenqualitaet

Erfolgskriterium:

- Unknown-Rate im Geraetemodell sinkt messbar gegenueber Baseline.

## Phase 3: Geo Precision MVP (Wochen 8-10)

Ziele:

- Standortdatenqualitaet transparent und nutzbar machen.

Deliverables:

- Plugin `GeoPrecision` mit:
  - Confidence-Score pro Standorttreffer
  - Geohash-basierte Verdichtung
  - Trennung zwischen IP-basiert und consent-basierter Praezision
- Geo-Qualitaetsdashboard

Erfolgskriterium:

- Standortberichte enthalten Confidence-Ebene und klare Datenherkunft.

## Phase 4: Hardening und Rollout (Wochen 11-12)

Ziele:

- Betriebssicherheit, Dokumentation, Uebergabe.

Deliverables:

- Last-/Performance-Checks
- Datenschutz- und Loeschprozesse verifiziert
- Runbook und Betriebsdoku
- Release v1.0

Erfolgskriterium:

- Produktiver Betrieb ohne kritische Findings.

## 6. KPI-Framework

Technische KPIs:

- API P95 Antwortzeit je Plugin-Report
- Archivierungsdauer je Site/Segment
- Fehlerquote in Tracking-Ingestion

Fachliche KPIs:

- Unknown-Rate Device Modell (% der Besuche)
- Unknown-Rate Region/Stadt
- Abdeckung der Top-Pfade (% Traffic, den Flow-Reports abdecken)
- Anzahl umgesetzter Optimierungen auf Basis neuer Reports

## 7. Risiken und Gegenmassnahmen

- Datenschutzrisiko bei Praezisionsstandort:
  - Gegenmassnahme: Opt-in Pflicht, Geohash-Reduktion, kurze Retention.
- Performance bei komplexen Flows:
  - Gegenmassnahme: Voraggregation und begrenzte Tiefe im MVP.
- Scope Creep:
  - Gegenmassnahme: klare Phase-DoD, Change-Requests gesammelt fuer spaeter.

## 8. Definition of Done (global)

Ein Arbeitspaket gilt als Done, wenn:

- Code umgesetzt und reviewed ist.
- Tests vorhanden und gruen sind.
- Performance kein Regression-Risiko zeigt.
- Datenschutzanforderungen fuer den Teilumfang geprueft sind.
- Doku (Nutzung + Betrieb) aktualisiert ist.

## 9. Naechste 7 Tage (konkret)

1. Plugin-Namensraum, Verzeichnisstruktur und Basisklassen anlegen.
2. Data Contracts fuer Flow, Geo, Device finalisieren.
3. KPI-Baseline aus bestehender Instanz als Startwert dokumentieren.
4. Backlog fuer Phase 1 in umsetzbare Tasks schneiden.
5. Erstes Flow-Report-Skelett + API-Endpunkt implementieren.
