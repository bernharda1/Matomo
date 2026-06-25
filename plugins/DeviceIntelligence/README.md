# DeviceIntelligence

Purpose:
- Improve device observability and reduce unknown device attributes.

MVP scope:
- Unknown-rate quality metrics.
- Optional client hints ingestion model.
- Device quality reporting.

Implemented MVP API:
- Method: `DeviceIntelligence.getQualitySummary`
- Params: `idSite`, `period`, `date`, `segment` (currently not enabled in MVP)
- Response: `meta`, `dimensions`

Implemented MVP report:
- Menu entry under Visitors.
- Table with unknown count and unknown rate per dimension.
- Robust schema handling via dynamic column detection on `log_visit`.

Implemented Client Hints processing (SB-008):
- Raw `uadata` JSON can be submitted in the report UI.
- JSON is validated and mapped to device dimensions.
- Mapped fields: `deviceType`, `brand`, `model`, `osName`, `osVersion`, `browserName`, `browserVersion`, `clientHintsPresent`.

## Data Retention & Cleanup (SB-011)

Implemented automatic retention jobs:
- Raw device data: Deleted after 30 days (personal data)
- Aggregate data: Deleted after 365 days (anonymized)
- Scheduled task runs daily at 3 AM UTC
- Dry-run mode available for testing

Manual testing:
```bash
php console deviceintelligence:test-retention          # Dry-run (default)
php console deviceintelligence:test-retention --execute # Execute delete
```

Next implementation steps:
1. Persist mapped Client Hints fields per visit in dedicated storage.
2. Add trend history and quality buckets over time.
3. Add dashboard widget with unknown-rate time series.
