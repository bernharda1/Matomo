<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service\RateLimiter;

/**
 * SB-019.2: ExponentialBackoff
 * 
 * Advanced exponential backoff strategies for rate limiting
 * Supports multiple backoff algorithms
 */
class ExponentialBackoff
{
    private const DEFAULT_ALGORITHM = 'exponential';

    /**
     * Calculate backoff time using exponential algorithm
     */
    public static function calculateExponential(
        int $attempts,
        int $baseSeconds = 1,
        int $maxSeconds = 3600,
        float $multiplier = 2.0
    ): int {
        // Formula: min(maxSeconds, baseSeconds * multiplier^(attempts-1))
        $backoff = (int)($baseSeconds * pow($multiplier, $attempts - 1));

        return min($backoff, $maxSeconds);
    }

    /**
     * Calculate backoff using linear algorithm
     */
    public static function calculateLinear(
        int $attempts,
        int $baseSeconds = 1,
        int $maxSeconds = 3600,
        int $increment = 1
    ): int {
        // Formula: min(maxSeconds, baseSeconds + (increment * attempts))
        $backoff = $baseSeconds + ($increment * $attempts);

        return min($backoff, $maxSeconds);
    }

    /**
     * Calculate backoff using Fibonacci algorithm
     */
    public static function calculateFibonacci(
        int $attempts,
        int $maxSeconds = 3600
    ): int {
        // Fibonacci sequence: 1, 1, 2, 3, 5, 8, 13, 21, ...
        $fib = self::fibonacciAtPosition($attempts);

        return min($fib, $maxSeconds);
    }

    /**
     * Calculate backoff with jitter (randomization)
     */
    public static function calculateWithJitter(
        int $attempts,
        string $algorithm = self::DEFAULT_ALGORITHM,
        int $baseSeconds = 1,
        int $maxSeconds = 3600
    ): int {
        // Get base backoff
        $backoff = match ($algorithm) {
            'linear' => self::calculateLinear($attempts, $baseSeconds, $maxSeconds),
            'fibonacci' => self::calculateFibonacci($attempts, $maxSeconds),
            'exponential' => self::calculateExponential($attempts, $baseSeconds, $maxSeconds),
            default => self::calculateExponential($attempts, $baseSeconds, $maxSeconds),
        };

        // Add jitter: backoff * (0.5 to 1.0)
        $jitter = rand(50, 100) / 100;

        return (int)($backoff * $jitter);
    }

    /**
     * Get Fibonacci number at position
     */
    private static function fibonacciAtPosition(int $n): int
    {
        if ($n <= 0) return 1;
        if ($n === 1) return 1;
        if ($n === 2) return 1;

        $prev = 1;
        $current = 1;

        for ($i = 3; $i <= $n; $i++) {
            $next = $prev + $current;
            $prev = $current;
            $current = $next;
        }

        return $current;
    }

    /**
     * Get backoff schedule for next N attempts
     */
    public static function getSchedule(
        int $nextAttempts = 5,
        string $algorithm = self::DEFAULT_ALGORITHM,
        int $baseSeconds = 1,
        int $maxSeconds = 3600
    ): array {
        $schedule = [];

        for ($i = 1; $i <= $nextAttempts; $i++) {
            $schedule[$i] = match ($algorithm) {
                'linear' => self::calculateLinear($i, $baseSeconds, $maxSeconds),
                'fibonacci' => self::calculateFibonacci($i, $maxSeconds),
                'exponential' => self::calculateExponential($i, $baseSeconds, $maxSeconds),
                default => self::calculateExponential($i, $baseSeconds, $maxSeconds),
            };
        }

        return $schedule;
    }

    /**
     * Format backoff time for display
     */
    public static function formatBackoffTime(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        if ($seconds < 3600) {
            $minutes = (int)($seconds / 60);
            $remaining = $seconds % 60;

            return $remaining > 0 ? "{$minutes}m {$remaining}s" : "{$minutes}m";
        }

        $hours = (int)($seconds / 3600);
        $remaining = $seconds % 3600;
        $minutes = (int)($remaining / 60);

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }
}
