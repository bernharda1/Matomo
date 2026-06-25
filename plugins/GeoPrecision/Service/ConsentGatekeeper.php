<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Service;

final class ConsentGatekeeper
{
    public const CONSENT_CATEGORY_PRECISE_GEO = 'precise_location';

    /**
     * Determines if precise geo data should be allowed based on consent state.
     *
     * @param bool $hasConsentForPreciseGeo
     * @return bool
     */
    public function allowsPreciseGeoData(bool $hasConsentForPreciseGeo): bool
    {
        return $hasConsentForPreciseGeo;
    }

    /**
     * Masks geo data to coarse level if consent is not granted.
     *
     * @param bool $hasConsent
     * @param string|null $countryCode
     * @param string|null $regionCode
     * @param string|null $cityName
     * @param float|null $latitude
     * @param float|null $longitude
     *
     * @return array<string, mixed>
     */
    public function maskGeoDataIfNeeded(
        bool $hasConsent,
        ?string $countryCode,
        ?string $regionCode,
        ?string $cityName,
        ?float $latitude,
        ?float $longitude
    ): array {
        if ($hasConsent) {
            return [
                'countryCode' => $countryCode,
                'regionCode' => $regionCode,
                'cityName' => $cityName,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'masked' => false,
                'maskReason' => null,
            ];
        }

        return [
            'countryCode' => $countryCode,
            'regionCode' => null,
            'cityName' => null,
            'latitude' => null,
            'longitude' => null,
            'masked' => true,
            'maskReason' => 'No consent for precise geo data. Country-level data only.',
        ];
    }

    /**
     * Determines the effective precision level based on consent state.
     *
     * @param string $requestedPrecisionLevel
     * @param bool $hasConsent
     *
     * @return string
     */
    public function filterPrecisionLevel(string $requestedPrecisionLevel, bool $hasConsent): string
    {
        if ($hasConsent) {
            return $requestedPrecisionLevel;
        }

        $coarseMapping = [
            'exact' => 'country',
            'approx' => 'country',
            'city' => 'country',
            'region' => 'country',
            'country' => 'country',
            'unknown' => 'unknown',
        ];

        return $coarseMapping[$requestedPrecisionLevel] ?? 'country';
    }

    /**
     * Check if a device model/brand should be treated as sensitive.
     * Returns true if data should be gated (currently false, as device data
     * is not considered as sensitive as precise geo).
     *
     * @param string $dimension
     *
     * @return bool
     */
    public function isDeviceDimensionGated(string $dimension): bool
    {
        return false;
    }

    /**
     * Get consent category name for UI/documentation.
     *
     * @return string
     */
    public static function getConsentCategory(): string
    {
        return self::CONSENT_CATEGORY_PRECISE_GEO;
    }

    /**
     * Get human-readable description of what this consent gate protects.
     *
     * @return string
     */
    public static function getConsentDescription(): string
    {
        return 'Permission to collect and process precise geographic location data (city, latitude, longitude). Without this consent, only country-level data is retained.';
    }
}
