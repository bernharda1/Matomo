<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\SecurityValidator;
use Piwik\Plugins\VisitorFlowIntelligence\Exception\SecurityException;

/**
 * SB-018.1: SecurityValidatorTest
 * 
 * Tests for input validation and security checks
 */
class SecurityValidatorTest extends TestCase
{
    /**
     * Test empty segment is allowed
     */
    public function testEmptySegmentAllowed(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateSegment('');
    }

    /**
     * Test valid single segment
     */
    public function testValidSingleSegment(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateSegment('deviceType==mobile');
    }

    /**
     * Test valid multiple segments (AND logic)
     */
    public function testValidMultipleSegments(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateSegment('deviceType==mobile;country==DE;browserName==Chrome');
    }

    /**
     * Test segment exceeding max length
     */
    public function testSegmentExceedsMaxLength(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment(str_repeat('x', 1001));
    }

    /**
     * Test SQL injection - UNION SELECT
     */
    public function testSQLInjectionUnionSelect(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment("deviceType==mobile' UNION SELECT * FROM users--");
    }

    /**
     * Test SQL injection - DROP TABLE
     */
    public function testSQLInjectionDropTable(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment("deviceType==mobile'; DROP TABLE log_visit;--");
    }

    /**
     * Test SQL injection - xp_cmdshell
     */
    public function testSQLInjectionXpCmdshell(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment("deviceType==mobile'; EXEC xp_cmdshell 'dir';--");
    }

    /**
     * Test XSS - Script tag
     */
    public function testXSSScriptTag(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment("<script>alert('xss')</script>");
    }

    /**
     * Test XSS - Event handler
     */
    public function testXSSEventHandler(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment("deviceType==mobile' onload='alert(1)'");
    }

    /**
     * Test XSS - JavaScript URI
     */
    public function testXSSJavaScriptURI(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment("deviceType==javascript:alert('xss')");
    }

    /**
     * Test valid site ID
     */
    public function testValidSiteId(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateSiteId(1);
        SecurityValidator::validateSiteId(999999);
    }

    /**
     * Test invalid site ID - zero
     */
    public function testInvalidSiteIdZero(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSiteId(0);
    }

    /**
     * Test invalid site ID - negative
     */
    public function testInvalidSiteIdNegative(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSiteId(-1);
    }

    /**
     * Test invalid site ID - exceeds max
     */
    public function testInvalidSiteIdExceedsMax(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSiteId(1000000);
    }

    /**
     * Test valid period
     */
    public function testValidPeriod(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validatePeriod('day');
        SecurityValidator::validatePeriod('week');
        SecurityValidator::validatePeriod('month');
        SecurityValidator::validatePeriod('year');
    }

    /**
     * Test invalid period
     */
    public function testInvalidPeriod(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validatePeriod('invalid');
    }

    /**
     * Test valid date
     */
    public function testValidDate(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateDate('2026-06-25');
    }

    /**
     * Test invalid date format
     */
    public function testInvalidDateFormat(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateDate('2026/06/25');
    }

    /**
     * Test invalid date value
     */
    public function testInvalidDateValue(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateDate('2026-13-01');
    }

    /**
     * Test valid cache key
     */
    public function testValidCacheKey(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateCacheKey('cache_key_1');
        SecurityValidator::validateCacheKey('cache-key-1');
        SecurityValidator::validateCacheKey('cachekey');
    }

    /**
     * Test invalid cache key - special characters
     */
    public function testInvalidCacheKeySpecialCharacters(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateCacheKey('cache/key');
    }

    /**
     * Test invalid cache key - exceeds length
     */
    public function testInvalidCacheKeyExceedsLength(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateCacheKey(str_repeat('x', 256));
    }

    /**
     * Test segment without operator
     */
    public function testSegmentWithoutOperator(): void
    {
        $this->expectException(SecurityException::class);
        SecurityValidator::validateSegment('deviceType');
    }

    /**
     * Test sanitize for display
     */
    public function testSanitizeForDisplay(): void
    {
        $input = '<script>alert("xss")</script>';
        $sanitized = SecurityValidator::sanitizeForDisplay($input);
        
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized);
    }

    /**
     * Test sanitize for database
     */
    public function testSanitizeForDatabase(): void
    {
        $input = '  deviceType==mobile  ';
        $sanitized = SecurityValidator::sanitizeForDatabase($input);
        
        $this->assertEquals('deviceType==mobile', $sanitized);
    }

    /**
     * Test complex valid segment with multiple operators
     */
    public function testComplexValidSegment(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateSegment('visitDuration>300;actions>=5;country!=US');
    }

    /**
     * Test segment with numeric values
     */
    public function testSegmentWithNumericValues(): void
    {
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateSegment('visitDuration>300');
    }

    /**
     * Test segment with OR-like syntax (using comma - future enhancement)
     */
    public function testSegmentFieldNameValidation(): void
    {
        // Valid field names should be alphanumeric with underscores
        $this->expectNotToPerformAssertions();
        SecurityValidator::validateSegment('device_type==mobile');
    }
}
