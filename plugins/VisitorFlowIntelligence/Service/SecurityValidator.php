<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Exception\SecurityException;

/**
 * SB-018: SecurityValidator
 * 
 * Central security validation layer for all user inputs
 * Prevents SQL injection, XSS, and malformed data attacks
 */
class SecurityValidator
{
    private const MAX_SEGMENT_LENGTH = 1000;
    private const MAX_SITE_ID = 999999;
    private const VALID_PERIODS = ['day', 'week', 'month', 'year'];
    private const VALID_DATE_FORMAT = 'Y-m-d';
    
    // SQL injection patterns
    private const SQL_INJECTION_PATTERNS = [
        "/'(.*?)(union|select|insert|update|delete|drop|create|alter)/i",
        "/;.*?(union|select|insert|update|delete|drop|create|alter)/i",
        "/.*?xp_|.*?sp_/i",
        "/.*?(exec|execute)\s*\(/i",
    ];
    
    // XSS patterns
    private const XSS_PATTERNS = [
        '/<script[^>]*>/i',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<iframe/i',
        '/<object/i',
        '/<embed/i',
    ];

    /**
     * Validate and sanitize segment string
     */
    public static function validateSegment(string $segment): void
    {
        // Empty segment is allowed
        if (empty($segment)) {
            return;
        }

        // Length check
        if (strlen($segment) > self::MAX_SEGMENT_LENGTH) {
            throw new SecurityException(
                "Segment string exceeds maximum length of " . self::MAX_SEGMENT_LENGTH
            );
        }

        // SQL injection check
        self::checkSQLInjection($segment);

        // XSS check
        self::checkXSS($segment);

        // Valid segment syntax check
        self::validateSegmentSyntax($segment);
    }

    /**
     * Validate site ID
     */
    public static function validateSiteId(int $siteId): void
    {
        if ($siteId <= 0 || $siteId > self::MAX_SITE_ID) {
            throw new SecurityException(
                "Invalid site ID: {$siteId}. Must be between 1 and " . self::MAX_SITE_ID
            );
        }
    }

    /**
     * Validate period string
     */
    public static function validatePeriod(string $period): void
    {
        if (!in_array($period, self::VALID_PERIODS, true)) {
            throw new SecurityException(
                "Invalid period: {$period}. Valid periods are: " . implode(', ', self::VALID_PERIODS)
            );
        }
    }

    /**
     * Validate date format
     */
    public static function validateDate(string $date): void
    {
        $parsed = \DateTime::createFromFormat(self::VALID_DATE_FORMAT, $date);
        
        if (!$parsed || $parsed->format(self::VALID_DATE_FORMAT) !== $date) {
            throw new SecurityException(
                "Invalid date format: {$date}. Expected format: " . self::VALID_DATE_FORMAT
            );
        }
    }

    /**
     * Validate and sanitize cache key
     */
    public static function validateCacheKey(string $key): void
    {
        // Cache keys should be alphanumeric and underscore/dash
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            throw new SecurityException(
                "Invalid cache key format: {$key}. Only alphanumeric, underscore, and dash allowed"
            );
        }

        if (strlen($key) > 255) {
            throw new SecurityException(
                "Cache key too long: " . strlen($key) . " characters. Maximum: 255"
            );
        }
    }

    /**
     * Check for SQL injection patterns
     */
    private static function checkSQLInjection(string $input): void
    {
        foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                throw new SecurityException(
                    "Potential SQL injection detected in input"
                );
            }
        }
    }

    /**
     * Check for XSS patterns
     */
    private static function checkXSS(string $input): void
    {
        foreach (self::XSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                throw new SecurityException(
                    "Potential XSS attack detected in input"
                );
            }
        }
    }

    /**
     * Validate segment syntax (basic structure)
     */
    private static function validateSegmentSyntax(string $segment): void
    {
        // Split by semicolon (AND operator)
        $conditions = explode(';', $segment);

        foreach ($conditions as $condition) {
            $condition = trim($condition);
            
            // Each condition should have an operator
            $hasOperator = preg_match('/[!=<>]+/', $condition);
            
            if (!$hasOperator) {
                throw new SecurityException(
                    "Invalid segment condition: {$condition}. Missing operator (=, !=, >, <, >=, <=)"
                );
            }

            // Validate condition format: field operator value
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*[!=<>]+.*$/', $condition)) {
                throw new SecurityException(
                    "Invalid segment condition format: {$condition}"
                );
            }
        }
    }

    /**
     * Sanitize string for display (HTML escaping)
     */
    public static function sanitizeForDisplay(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize string for database (parameterized queries)
     */
    public static function sanitizeForDatabase(string $input): string
    {
        // Parameterized queries handle escaping, but we can trim whitespace
        return trim($input);
    }
}
