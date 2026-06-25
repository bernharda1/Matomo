# VisitorFlowIntelligence

Purpose:
- Analyze visitor paths and identify drop-off bottlenecks.

MVP scope:
- Top paths by segment and date range.
- Transition and drop-off metrics.
- Report and widget registration.

Implemented MVP API:
- Method: `VisitorFlowIntelligence.getTopPaths`
- Params: `idSite`, `period`, `date`, `segment` (currently not enabled in MVP), `maxDepth`, `limit`
- Response: `meta`, `paths`, `transitions`, `dropoffs`

Current implementation notes:
- Data source: `log_visit`, `log_link_visit_action`, `log_action`
- Path normalization: URL path based (query/hash stripped)
- Segment filter: explicitly rejected in SB-005 MVP with DomainException

## Data Retention & Cleanup (SB-011)

Implemented automatic retention jobs:
- Raw flow data: Deleted after 30 days (personal data)
- Aggregate data: Deleted after 365 days (anonymized)
- Scheduled task runs daily at 3 AM UTC
- Dry-run mode available for testing

Manual testing:
```bash
php console visitorflow:test-retention          # Dry-run (default)
php console visitorflow:test-retention --execute # Execute delete
```

Next implementation steps:
1. Add Archiver for pre-aggregated period data.
2. Enable segment support for flow API queries.
3. Improve report UX with interactive Sankey visualization.
