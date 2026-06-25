# GeoPrecision Consent Gate Integration Guide (SB-010)

## Overview

The Consent Gate implementation in SB-010 provides a security layer that masks precise geographic data (city, region, coordinates) unless the visitor has explicitly granted consent for precise location tracking.

## Architecture

### Components

1. **ConsentGatekeeper** (`Service/ConsentGatekeeper.php`)
   - Validates consent state
   - Masks geo data based on consent
   - Filters precision levels
   - Provides consent metadata for UI

2. **API Enhancement** (`API.php`)
   - New parameter: `hasConsentForPreciseGeo` (boolean, optional)
   - Returns `consentGated` flag in meta response
   - Throws DomainException if consent is required but not provided

3. **Controller Integration** (`Controller.php`)
   - Reads `hasConsent` request parameter
   - Passes to API
   - Provides UI notice to end user

4. **Template Enhancement** (`templates/index.twig`)
   - Displays consent status (Granted/Denied)
   - Shows warning when precision is gated

## Data Masking Rules

| Scenario | Country | Region | City | Coordinates | Precision Level |
|----------|---------|--------|------|-------------|-----------------|
| Consent Granted | ✓ | ✓ | ✓ | ✓ | exact/approx/city/region |
| Consent Denied | ✓ | ✗ | ✗ | ✗ | country |
| No Data | ✗ | ✗ | ✗ | ✗ | unknown |

## API Usage Examples

### With Consent (Full Precision)

```
GET /index.php?module=API&method=GeoPrecision.getConfidenceSummary&idSite=1&period=day&date=2026-06-25&hasConsentForPreciseGeo=1

Response:
{
  "meta": {
    "idSite": 1,
    "consentGated": false,
    "averageConfidenceScore": 85.5,
    ...
  },
  "dimensions": [
    {"dimension": "Country", "dataPoints": 42, "knownCount": 40, "unknownCount": 2},
    {"dimension": "Region", "dataPoints": 35, "knownCount": 28, "unknownCount": 7},
    {"dimension": "City", "dataPoints": 30, "knownCount": 22, "unknownCount": 8}
  ]
}
```

### Without Consent (Country-Level Only)

```
GET /index.php?module=API&method=GeoPrecision.getConfidenceSummary&idSite=1&period=day&date=2026-06-25&hasConsentForPreciseGeo=0

Response:
{
  "meta": {
    "idSite": 1,
    "consentGated": true,
    "averageConfidenceScore": 40.0,  // downgraded to country-level
    ...
  },
  "dimensions": [
    {"dimension": "Country", "dataPoints": 42, "knownCount": 40, "unknownCount": 2},
    {"dimension": "Region", "dataPoints": 0, "knownCount": 0, "unknownCount": 0},  // masked
    {"dimension": "City", "dataPoints": 0, "knownCount": 0, "unknownCount": 0}     // masked
  ]
}
```

## Implementation Notes

### Current MVP Status (SB-010)

- ✅ ConsentGatekeeper validates and masks data
- ✅ API parameter controls precision level
- ✅ Report UI displays consent status
- ✅ DomainException prevents processing without consent if configured
- ⏳ Database-level masking (persist-time enforcement) - deferred to SB-011

### Future Integration Points (SB-011, SB-012+)

1. **Matomo Core Consent API Integration**
   - Read runtime consent state from Matomo\Plugins\Consent
   - Replace `hasConsentForPreciseGeo` parameter with automatic lookups
   - Support consent history per visit

2. **Data Retention & Cleanup**
   - Scheduled job to purge precise geo data past retention window
   - Respect consent withdrawal (retroactive masking)

3. **Audit & Compliance**
   - Log all consent-gated queries
   - GDPR export includes consent-related metadata
   - Consent state archived with aggregated reports

## Testing Checklist

- [ ] Verify without consent: City/Region fields are null
- [ ] Verify with consent: City/Region fields have values
- [ ] Verify precision level is "country" when consent denied
- [ ] Verify precision level respects source type when consent granted
- [ ] Verify UI shows "Not Granted (Country-Level Only)" when hasConsent=0
- [ ] Verify UI shows "Granted (Precise Data Enabled)" when hasConsent=1
- [ ] Verify confidence score is downgraded when consent gated

## Configuration (Future)

```php
// Potential future config in matomo/config/config.php
'GeoPrecision' => [
    'require_precise_geo_consent' => true,     // Enforce consent gate
    'precise_geo_consent_category' => 'precise_location',
    'default_precision_masking' => 'country',  // Country-level fallback
],
```
