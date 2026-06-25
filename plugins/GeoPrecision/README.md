# GeoPrecision

Purpose:
- Improve and expose geo data quality using confidence scoring.

MVP scope:
- Geo source classification.
- Confidence levels (high, medium, low).
- Aggregated geohash reporting.

Implemented MVP API and Report (SB-009):
- Method: `GeoPrecision.getConfidenceSummary`
- Params: `idSite`, `period`, `date`, `segment` (currently not enabled in MVP)
- Response: `meta`, `confidenceDistribution`, `dimensions`

Implemented Scoring Logic:
- Precision levels: exact, approx, city, region, country, unknown
- Confidence level determination based on source type (override, consent_precise, ip)
- Confidence score calculation: 0-100 based on precision, confidence level, and source
- Dimension coverage: Country, Region, City data points tracked

Next implementation steps:
1. Add consent gate checks for precise geo data.
2. Persist geohash/confidence records in dedicated storage table.
3. Add dashboard widget with confidence score time series.

## Consent Gate for Precise Geo Data (SB-010)

Implemented ConsentGatekeeper service:
- Blocks city/region/coordinate data when `hasConsentForPreciseGeo=false`
- Masks precision level to 'country' if consent not granted
- Country-level data always available (based on IP)
- Report UI shows consent status and gating notices
- API returns `consentGated: true` in metadata when consent is absent
- API parameter: `hasConsentForPreciseGeo` (boolean, optional, default false)

Usage:
- Without consent: Only Country-level data is returned, Region/City/Coords are null
- With consent: Full precision data available per GeoConfidenceScorer logic

Next steps:
1. Integrate with Matomo Core Consent API to fetch runtime consent state
2. Persist masked geo data only when consent is recorded in live visit
3. Add audit trail for consent-gated queries

## Data Retention & Cleanup (SB-011)

Implemented automatic retention jobs:
- Raw geo data: Deleted after 30 days (personal data)
- Aggregate data: Deleted after 365 days (anonymized)
- Scheduled task runs daily at 3 AM UTC
- Dry-run mode available for testing

Manual testing:
```bash
php console geoprecision:test-retention          # Dry-run (default)
php console geoprecision:test-retention --execute # Execute delete
```

Logging:
```
tail -f matomo/tmp/logs/matomo.log | grep GeoPrecision
```
