# Flow-Domainmodell - VisitorFlowIntelligence

Status: Implementierungsgrundlage fuer SB-004
Version: 0.1.0
Plugin: VisitorFlowIntelligence

## 1. Ziel

Das Domainmodell definiert die fachlichen Kernobjekte fuer Besucherstrom-Analysen:

- Step
- Transition
- Path
- Dropoff
- FlowQuery
- FlowResult

Es dient als verbindliche Grundlage fuer SB-005 (Flow API Endpoint) und nachfolgende Report-Implementierungen.

## 2. Fachliche Begriffe

1. Step
- Ein einzelner Knoten in einem Besucherstrom.
- In MVP entspricht ein Step einer normalisierten Action-URL.

2. Transition
- Eine gerichtete Kante von sourceStep zu targetStep.
- Zaehlt, wie oft Nutzer von A nach B gewechselt sind.

3. Path
- Eine geordnete Sequenz von Steps.
- Relevante Metriken: visits, share, depth, dropoffAtStep.

4. Dropoff
- Abbruch eines Besuchsstroms an einem Step ohne naechsten Schritt.

5. FlowQuery
- Fachliche Anfrage auf Flow-Daten fuer idSite, Zeitraum, Segment und Limiter.

6. FlowResult
- Antwortobjekt mit Metadaten und Ergebnislisten (Paths, Transitions, Dropoffs).

## 3. Domänenobjekte

## 3.1 Step

Pflichtattribute:
- id (string)
- label (string)

Optionalattribute:
- normalizedUrl (string)
- actionName (string)

Regeln:
- id ist stabil innerhalb derselben Query-Ausfuehrung.
- label darf nicht leer sein.

## 3.2 Transition

Pflichtattribute:
- sourceStepId (string)
- targetStepId (string)
- visits (int)

Abgeleitete Attribute:
- transitionRate (float 0..1)

Regeln:
- sourceStepId und targetStepId duerfen nicht identisch sein.
- visits >= 0

## 3.3 Path

Pflichtattribute:
- steps (array of string)
- visits (int)

Abgeleitete Attribute:
- share (float 0..1)
- depth (int)
- dropoffAtStep (int|null)

Regeln:
- steps enthaelt mindestens einen Step.
- depth = count(steps)

## 3.4 Dropoff

Pflichtattribute:
- stepId (string)
- dropoffCount (int)

Abgeleitete Attribute:
- dropoffRate (float 0..1)

Regeln:
- dropoffCount >= 0

## 3.5 FlowQuery

Pflichtattribute:
- idSite (int > 0)
- period (day|week|month|year|range)
- date (string)

Optionalattribute:
- segment (string)
- maxDepth (int, default 5, range 2..12)
- limit (int, default 20, range 1..200)

Regeln:
- period/date muessen Matomo-kompatibel sein.

## 3.6 FlowResult

Pflichtattribute:
- idSite (int)
- period (string)
- date (string)
- generatedAt (datetime UTC)
- totalVisits (int)
- paths (array Path)
- transitions (array Transition)
- dropoffs (array Dropoff)

Optionalattribute:
- segment (string)

## 4. Aggregationslogik (MVP)

1. Sequenzbildung
- Pro Visit werden Actions nach serverTime/pageviewPosition sortiert.
- Es wird eine Step-Sequenz erzeugt.

2. Transition-Aggregation
- Fuer jedes Paar (step_i, step_i+1) wird ein Counter erhoeht.

3. Path-Aggregation
- Sequenzen gleicher Step-Folge werden zusammengefasst.

4. Dropoff-Aggregation
- Letzter Step jeder Sequenz erhoeht dropoffCount.

5. Ratenberechnung
- share = path.visits / totalVisits
- transitionRate = transition.visits / visitsOfSourceStep
- dropoffRate = dropoffCount / visitsOfStep

## 5. Invarianten

- Alle Raten liegen in [0,1].
- Alle Counter sind ganzzahlig und nicht negativ.
- Resultate sind reproduzierbar bei identischer Query und gleichem Datenstand.

## 6. Fehlerverhalten

Validierungsfehler Query:
- idSite <= 0
- ungueltiger period-Wert
- ungueltiger maxDepth/limit

Verhalten:
- DomainException mit klarer Fehlermeldung.

## 7. Mapping zu SB-005

SB-005 verwendet dieses Modell wie folgt:

- Request -> FlowQuery
- Aggregation -> Path/Transition/Dropoff
- Response -> FlowResult

Damit ist die API-Spezifikation aus DATA_CONTRACTS direkt implementierbar.

## 8. Akzeptanzkriterien SB-004

SB-004 gilt als abgeschlossen, wenn:

1. Fachliche Entitaeten Step/Transition/Path/Dropoff dokumentiert sind.
2. Query- und Resultobjekte definiert sind.
3. Aggregationsregeln und Invarianten festgelegt sind.
4. Modell in Plugin-Klassen angelegt ist.
