<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Service;

final class GeoConfidenceScorer
{
    private const PRECISION_LEVELS = [
        'country' => 1,
        'region' => 2,
        'city' => 3,
        'approx' => 4,
        'exact' => 5,
        'unknown' => 0,
    ];

    private const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];

    public function scoreGeoData(
        ?string $countryCode,
        ?string $regionCode,
        ?string $cityName,
        bool $hasConsent,
        string $sourceType
    ): array {
        $countryKnown = $this->isDataPointKnown($countryCode);
        $regionKnown = $this->isDataPointKnown($regionCode);
        $cityKnown = $this->isDataPointKnown($cityName);

        $precisionLevel = $this->determinePrecisionLevel(
            $countryKnown,
            $regionKnown,
            $cityKnown,
            $hasConsent,
            $sourceType
        );

        $confidenceLevel = $this->determineConfidenceLevel($sourceType, $precisionLevel);
        $confidenceScore = $this->calculateConfidenceScore($sourceType, $precisionLevel, $confidenceLevel);

        return [
            'precisionLevel' => $precisionLevel,
            'confidenceLevel' => $confidenceLevel,
            'confidenceScore' => $confidenceScore,
        ];
    }

    private function isDataPointKnown(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $value = trim($value);

        return $value !== '' && strtolower($value) !== 'unknown';
    }

    private function determinePrecisionLevel(
        bool $countryKnown,
        bool $regionKnown,
        bool $cityKnown,
        bool $hasConsent,
        string $sourceType
    ): string {
        if (!$countryKnown) {
            return 'unknown';
        }

        if ($sourceType === 'consent_precise' && $hasConsent && $cityKnown) {
            return 'exact';
        }

        if ($sourceType === 'override' && $cityKnown) {
            return 'exact';
        }

        if ($sourceType === 'ip') {
            if ($cityKnown) {
                return 'approx';
            }

            if ($regionKnown) {
                return 'region';
            }

            return 'country';
        }

        if ($cityKnown) {
            return 'city';
        }

        if ($regionKnown) {
            return 'region';
        }

        return 'country';
    }

    private function determineConfidenceLevel(string $sourceType, string $precisionLevel): string
    {
        if ($precisionLevel === 'unknown') {
            return 'low';
        }

        if ($sourceType === 'override') {
            return 'high';
        }

        if ($sourceType === 'consent_precise') {
            return 'high';
        }

        if ($sourceType === 'ip') {
            if ($precisionLevel === 'country') {
                return 'high';
            }

            if ($precisionLevel === 'region') {
                return 'medium';
            }

            if ($precisionLevel === 'approx') {
                return 'medium';
            }
        }

        return 'medium';
    }

    private function calculateConfidenceScore(
        string $sourceType,
        string $precisionLevel,
        string $confidenceLevel
    ): int {
        $baseScore = match ($precisionLevel) {
            'exact' => 100,
            'approx' => 80,
            'city' => 75,
            'region' => 60,
            'country' => 40,
            'unknown' => 0,
            default => 0,
        };

        $confidenceMultiplier = match ($confidenceLevel) {
            'high' => 1.0,
            'medium' => 0.85,
            'low' => 0.6,
            default => 0.5,
        };

        $sourceBonus = match ($sourceType) {
            'override' => 10,
            'consent_precise' => 5,
            'ip' => 0,
            default => -5,
        };

        $finalScore = (int) ($baseScore * $confidenceMultiplier + $sourceBonus);

        return max(0, min(100, $finalScore));
    }
}
