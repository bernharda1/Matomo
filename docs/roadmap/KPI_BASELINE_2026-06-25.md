# KPI Baseline - Matomo Erweiterung

Erstellt am: 2026-06-25
Bezugssystem: Bernhard Alber fuer Graz (idSite=1)
Zeitraum in den Screens: day / yesterday (2026-06-24)
Status: Baseline fuer SB-003

## 1. Ziel

Diese Baseline dokumentiert den Startzustand vor der Umsetzung der Plugins:

- VisitorFlowIntelligence
- GeoPrecision
- DeviceIntelligence

Fokus fuer SB-003:

- Unknown-Rate Device
- Unknown-Rate Geo

## 2. Datenquellen

1. Besucher > Orte
2. Besucher > Geraete
3. Besucher > Besucher-Log
4. Fruehere Dashboard- und Snapshot-Auszuege derselben Instanz

## 3. Baseline-KPIs (Startwerte)

## 3.1 Geo KPIs

### KPI-GEO-001: Eindeutige Besucher (Geo-Sicht)
- Wert: 31
- Quelle: Orte-Ansicht (Kopfzahl)
- Interpretation: Referenzgroesse fuer Geo-Qualitaetsmetriken

### KPI-GEO-002: Country Coverage
- Wert: 2 Laender (Oesterreich, Deutschland)
- Verteilung (sichtbar):
  - Oesterreich: 30
  - Deutschland: 2
- Hinweis: Summen in Tabellen koennen durch Aggregations-/Darstellungslogik von der Kopfzahl abweichen.

### KPI-GEO-003: Unknown-Rate Region (beobachtet)
- Beobachteter Unknown-Wert in Regionstabelle: 10
- Sichtbare Teilmenge (Top 5 von 8 Regionen): 29
- Beobachtete Unknown-Rate in sichtbarer Teilmenge: 10/29 = 34.5%
- Konservative Untergrenze gegen Kopfzahl: 10/31 = 32.3%
- Baseline-Wert fuer Steuerung: 34.5% (beobachtet), mit Datenqualitaetsvermerk "partial table view"

### KPI-GEO-004: Standortpraezision (qualitativ)
- Aktueller Zustand: Mischung aus konkreten Stadt/Region-Treffern und unbekannten Regionen
- Beobachtung aus Besucher-Log: GPS/Lat-Long wird in Profilansichten teils angezeigt
- Risiko: keine einheitliche Confidence-Ebene im Reporting vorhanden

## 3.2 Device KPIs

### KPI-DEV-001: Unknown-Rate Brand
- Markenverteilung (sichtbar):
  - unbekannt: 11
  - Apple: 9
  - Samsung: 8
  - Google: 1
  - POCO: 1
  - Vivo: 1
- Summe: 31
- Unknown-Rate Brand: 11/31 = 35.5%

### KPI-DEV-002: Unknown-Rate Modell (strikt unknown)
- Beobachtung: in der sichtbaren Modelltabelle kein expliziter Wert "unbekannt"
- Baseline (strikt unknown label): 0/31 = 0.0%
- Hinweis: Dieser Wert alleine ist nicht aussagekraeftig.

### KPI-DEV-003: Modell-Spezifitaetsluecke (neu fuer Steuerung)
- Modelle mit geringer Spezifitaet (z. B. "Allgemeines Desktop", "Allgemeines Smartphone"): 11
- Bezugsmenge: 31
- Spezifitaetsluecke Modell: 11/31 = 35.5%
- Zweck: realistischere Messung der Device-Qualitaet neben strict unknown.

## 3.3 Flow/Nutzungsverhalten (orientierend)

### KPI-FLOW-001: Kurzbesuche-Anteil (qualitativ)
- Beobachtung: Im Besucher-Log treten haeufig Besuche mit 1 Aktion auf.
- Interpretation: Potenzial fuer Flow-/Drop-off-Analyse hoch.
- Hinweis: Exakter Prozentwert folgt in SB-004/SB-005 mit dedizierter Aggregation.

## 4. Baseline-Risiken und Messgrenzen

1. Teilweise sichtbare Tabellen (Pagination/Top-N) koennen Raten verzerren.
2. Kopfwerte und Tabellensummen koennen in Matomo je Darstellung differieren.
3. Fuer robuste Vorher/Nachher-Vergleiche sollten dieselben API-Endpunkte automatisiert gezogen werden.

## 5. Messplan fuer Fortschrittskontrolle

## 5.1 Primare Ziel-KPIs

- Unknown-Rate Brand: von 35.5% auf <= 20%
- Unknown-Rate Region (beobachtet): von 34.5% auf <= 20%
- Modell-Spezifitaetsluecke: von 35.5% auf <= 15%

## 5.2 Vergleichsstandard

- Gleiches idSite
- Gleiche Periode/Datumslogik
- Gleiche Segment-Filter (Default: Alle Besuche)
- Vorher/Nachher als Delta in Prozentpunkten

## 6. Empfehlungen fuer naechste Erhebung

1. Baseline per Reporting API exportieren (nicht nur UI-Snapshot), damit reproduzierbar.
2. Region/City auf volle Tabellen ohne Top-N-Beschraenkung auswerten.
3. Device-Qualitaet mit neuem KPI "Modell-Spezifitaetsluecke" standardisieren.

## 7. Fazit Baseline

- Device-Datenqualitaet zeigt aktuell eine deutliche Luecke bei Brand (35.5%) und Modell-Spezifitaet (35.5%).
- Geo-Datenqualitaet zeigt eine signifikante Region-Unknown-Rate (beobachtet 34.5% in sichtbarer Teilmenge).
- Diese Werte sind geeignete Start-KPIs fuer die Wirksamkeitsmessung der geplanten Plugins.
