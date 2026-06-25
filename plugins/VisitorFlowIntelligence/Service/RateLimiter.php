<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Exception\SecurityException;

/**
 * SB-018.3: RateLimiter
 * 
 * Implements rate limiting for DDoS protection
 * Supports per-IP and per-user rate limiting with sliding window counters
 */
class RateLimiter
{
    private const DEFAULT_LIMIT = 100;
    private const DEFAULT_WINDOW = 3600; // 1 hour
    private const EXPONENTIAL_BACKOFF_MULTIPLIER = 2;
    private const MAX_BACKOFF_SECONDS = 3600; // 1 hour max

    // In-memory storage (in production, use Redis)
    private static array $store = [];

    /**
     * Check if request is allowed
     */
    public static function isAllowed(
        string $identifier,
        int $limit = self::DEFAULT_LIMIT,
        int $window = self::DEFAULT_WINDOW
    ): bool {
        $key = self::getKey($identifier, $window);
        $currentCount = self::getCount($key);

        return $currentCount < $limit;
    }

    /**
     * Increment request counter
     */
    public static function increment(string $identifier, int $window = self::DEFAULT_WINDOW): int
    {
        $key = self::getKey($identifier, $window);
        
        if (!isset(self::$store[$key])) {
            self::$store[$key] = [
                'count' => 0,
                'first_request' => time(),
                'window_start' => time(),
            ];
        }

        // Check if window has expired
        $now = time();
        $windowStart = self::$store[$key]['window_start'];
        
        if ($now - $windowStart >= $window) {
            // Reset window
            self::$store[$key] = [
                'count' => 0,
                'first_request' => $now,
                'window_start' => $now,
            ];
        }

        self::$store[$key]['count']++;
        return self::$store[$key]['count'];
    }

    /**
     * Get current count for identifier
     */
    public static function getCount(string $key): int
    {
        return self::$store[$key]['count'] ?? 0;
    }

    /**
     * Reset counter for identifier
     */
    public static function reset(string $identifier, int $window = self::DEFAULT_WINDOW): void
    {
        $key = self::getKey($identifier, $window);
        unset(self::$store[$key]);
    }

    /**
     * Get violation count (failed attempts after limit)
     */
    public static function getViolationCount(string $identifier): int
    {
        $violationKey = "violations_{$identifier}";
        return self::$store[$violationKey]['count'] ?? 0;
    }

    /**
     * Record a violation (failed rate limit check)
     */
    public static function recordViolation(string $identifier): int
    {
        $violationKey = "violations_{$identifier}";
        $now = time();

        if (!isset(self::$store[$violationKey])) {
            self::$store[$violationKey] = [
                'count' => 0,
                'first_violation' => $now,
            ];
        }

        self::$store[$violationKey]['count']++;
        return self::$store[$violationKey]['count'];
    }

    /**
     * Calculate exponential backoff seconds
     */
    public static function getExponentialBackoff(string $identifier): int
    {
        $violations = self::getViolationCount($identifier);
        
        // Backoff = base * multiplier^violations, capped at max
        $backoff = (int)(1 * pow(self::EXPONENTIAL_BACKOFF_MULTIPLIER, $violations));
        
        return min($backoff, self::MAX_BACKOFF_SECONDS);
    }

    /**
     * Check if identifier is currently backoff (blocked)
     */
    public static function isInBackoff(string $identifier): bool
    {
        $violationKey = "violations_{$identifier}";
        
        if (!isset(self::$store[$violationKey])) {
            return false;
        }

        $firstViolation = self::$store[$violationKey]['first_violation'];
        $backoffSeconds = self::getExponentialBackoff($identifier);
        
        return (time() - $firstViolation) < $backoffSeconds;
    }

    /**
     * Clear backoff (reset violations)
     */
    public static function clearBackoff(string $identifier): void
    {
        $violationKey = "violations_{$identifier}";
        unset(self::$store[$violationKey]);
    }

    /**
     * Generate rate limit key
     */
    private static function getKey(string $identifier, int $window): string
    {
        return "ratelimit_{$identifier}_{$window}";
    }

    /**
     * Get remaining requests
     */
    public static function getRemaining(
        string $identifier,
        int $limit = self::DEFAULT_LIMIT,
        int $window = self::DEFAULT_WINDOW
    ): int {
        $key = self::getKey($identifier, $window);
        $currentCount = self::getCount($key);
        
        return max(0, $limit - $currentCount);
    }

    /**
     * Get retry-after seconds
     */
    public static function getRetryAfter(string $identifier, int $window = self::DEFAULT_WINDOW): int
    {
        $key = self::getKey($identifier, $window);
        
        if (!isset(self::$store[$key])) {
            return 0;
        }

        $windowStart = self::$store[$key]['window_start'];
        $now = time();
        $elapsedInWindow = $now - $windowStart;
        
        return max(0, $window - $elapsedInWindow);
    }

    /**
     * Cleanup expired entries
     */
    public static function cleanup(int $maxAge = 86400): void
    {
        $now = time();
        
        foreach (self::$store as $key => $entry) {
            if (isset($entry['first_request']) && $now - $entry['first_request'] > $maxAge) {
                unset(self::$store[$key]);
            }
        }
    }
}
