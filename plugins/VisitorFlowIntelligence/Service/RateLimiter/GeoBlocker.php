<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service\RateLimiter;

use Piwik\Plugins\VisitorFlowIntelligence\Exception\SecurityException;

/**
 * SB-019.1: GeoBlocker
 * 
 * Geo-IP based rate limiting and blocking
 * Prevents DDoS from specific regions and detects suspicious patterns
 */
class GeoBlocker
{
    // Blocked countries/regions (customizable)
    private static array $blockedCountries = [];
    
    // Suspicious countries with higher rate limits
    private static array $suspiciousCountries = [
        'CN', 'RU', 'KP', 'IR', 'SY', // High-risk regions
    ];

    // Geo-location database (mock - in production use MaxMind GeoIP2)
    private static array $geoDatabase = [
        // IP ranges -> Country mappings (simplified)
    ];

    private const HIGH_RISK_LIMIT = 50;
    private const NORMAL_LIMIT = 100;
    private const LOW_RISK_LIMIT = 200;

    /**
     * Check if IP is from blocked country
     */
    public static function isBlocked(string $ipAddress): bool
    {
        $country = self::getCountryCode($ipAddress);
        
        return in_array($country, self::$blockedCountries, true);
    }

    /**
     * Get country code from IP address
     */
    public static function getCountryCode(string $ipAddress): string
    {
        // In production, use MaxMind GeoIP2
        // For now, return empty (no blocking)
        
        // Fallback: if Matomo's geo-location is available
        try {
            if (function_exists('geoip_country_code_by_name')) {
                $code = geoip_country_code_by_name($ipAddress);
                return $code ?: 'XX';
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return 'XX'; // Unknown
    }

    /**
     * Get risk level for country
     */
    public static function getRiskLevel(string $ipAddress): string
    {
        $country = self::getCountryCode($ipAddress);

        if (in_array($country, self::$suspiciousCountries, true)) {
            return 'high';
        }

        // Check if recent violations from this country
        $violations = self::getCountryViolations($country);
        if ($violations > 5) {
            return 'high';
        }

        if ($violations > 2) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get rate limit based on risk level
     */
    public static function getLimitForIP(string $ipAddress): int
    {
        $riskLevel = self::getRiskLevel($ipAddress);

        return match ($riskLevel) {
            'high' => self::HIGH_RISK_LIMIT,
            'medium' => self::NORMAL_LIMIT,
            'low' => self::LOW_RISK_LIMIT,
            default => self::NORMAL_LIMIT,
        };
    }

    /**
     * Add country to blocklist
     */
    public static function blockCountry(string $countryCode): void
    {
        if (!in_array($countryCode, self::$blockedCountries, true)) {
            self::$blockedCountries[] = strtoupper($countryCode);
        }
    }

    /**
     * Remove country from blocklist
     */
    public static function unblockCountry(string $countryCode): void
    {
        self::$blockedCountries = array_filter(
            self::$blockedCountries,
            fn($c) => $c !== strtoupper($countryCode)
        );
    }

    /**
     * Mark country as suspicious
     */
    public static function markSuspicious(string $countryCode): void
    {
        if (!in_array($countryCode, self::$suspiciousCountries, true)) {
            self::$suspiciousCountries[] = strtoupper($countryCode);
        }
    }

    /**
     * Get violations for country
     */
    private static function getCountryViolations(string $country): int
    {
        // In production, query violation database
        // For now, return 0
        return 0;
    }

    /**
     * Get list of blocked countries
     */
    public static function getBlockedCountries(): array
    {
        return self::$blockedCountries;
    }

    /**
     * Get list of suspicious countries
     */
    public static function getSuspiciousCountries(): array
    {
        return self::$suspiciousCountries;
    }

    /**
     * Check if IP from whitelist country (high-trust)
     */
    public static function isWhitelistedCountry(string $ipAddress): bool
    {
        $whitelistCountries = ['US', 'DE', 'FR', 'GB', 'CA', 'AU']; // Customizable
        $country = self::getCountryCode($ipAddress);

        return in_array($country, $whitelistCountries, true);
    }

    /**
     * Get geo-location details
     */
    public static function getGeoDetails(string $ipAddress): array
    {
        return [
            'ip' => $ipAddress,
            'country' => self::getCountryCode($ipAddress),
            'risk_level' => self::getRiskLevel($ipAddress),
            'rate_limit' => self::getLimitForIP($ipAddress),
            'blocked' => self::isBlocked($ipAddress),
            'whitelisted' => self::isWhitelistedCountry($ipAddress),
        ];
    }
}
