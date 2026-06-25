<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Unit\Service\RateLimiter;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\RateLimiter\GeoBlocker;
use Piwik\Plugins\VisitorFlowIntelligence\Service\RateLimiter\ExponentialBackoff;

/**
 * SB-019.1-2: GeoBlocker & ExponentialBackoff Tests
 */
class GeoRateLimitingTest extends TestCase
{
    /**
     * Test geo-blocker blocked countries
     */
    public function testGeoBlockerBlockedCountries(): void
    {
        GeoBlocker::blockCountry('CN');
        $blocked = GeoBlocker::getBlockedCountries();

        $this->assertContains('CN', $blocked);
    }

    /**
     * Test geo-blocker unblock country
     */
    public function testGeoBlockerUnblockCountry(): void
    {
        GeoBlocker::blockCountry('CN');
        GeoBlocker::unblockCountry('CN');

        $this->assertNotContains('CN', GeoBlocker::getBlockedCountries());
    }

    /**
     * Test suspicious countries list
     */
    public function testSuspiciousCountries(): void
    {
        $suspicious = GeoBlocker::getSuspiciousCountries();

        $this->assertContains('CN', $suspicious);
        $this->assertContains('RU', $suspicious);
    }

    /**
     * Test mark country suspicious
     */
    public function testMarkSuspicious(): void
    {
        GeoBlocker::markSuspicious('XX');

        $this->assertContains('XX', GeoBlocker::getSuspiciousCountries());
    }

    /**
     * Test geo details structure
     */
    public function testGeoDetails(): void
    {
        $details = GeoBlocker::getGeoDetails('8.8.8.8');

        $this->assertArrayHasKey('ip', $details);
        $this->assertArrayHasKey('country', $details);
        $this->assertArrayHasKey('risk_level', $details);
        $this->assertArrayHasKey('rate_limit', $details);
        $this->assertArrayHasKey('blocked', $details);
    }

    /**
     * Test exponential backoff - attempt 1
     */
    public function testExponentialBackoffAttempt1(): void
    {
        $backoff = ExponentialBackoff::calculateExponential(1, 1, 3600, 2);

        $this->assertEquals(1, $backoff);
    }

    /**
     * Test exponential backoff - attempt 2
     */
    public function testExponentialBackoffAttempt2(): void
    {
        $backoff = ExponentialBackoff::calculateExponential(2, 1, 3600, 2);

        $this->assertEquals(2, $backoff);
    }

    /**
     * Test exponential backoff - attempt 3
     */
    public function testExponentialBackoffAttempt3(): void
    {
        $backoff = ExponentialBackoff::calculateExponential(3, 1, 3600, 2);

        $this->assertEquals(4, $backoff);
    }

    /**
     * Test exponential backoff - attempt 10
     */
    public function testExponentialBackoffAttempt10(): void
    {
        $backoff = ExponentialBackoff::calculateExponential(10, 1, 3600, 2);

        // 2^9 = 512
        $this->assertEquals(512, $backoff);
    }

    /**
     * Test exponential backoff capped at max
     */
    public function testExponentialBackoffCapped(): void
    {
        $backoff = ExponentialBackoff::calculateExponential(20, 1, 3600, 2);

        // Should be capped at 3600
        $this->assertEquals(3600, $backoff);
    }

    /**
     * Test linear backoff
     */
    public function testLinearBackoff(): void
    {
        $backoff1 = ExponentialBackoff::calculateLinear(1, 1, 3600, 1);
        $backoff2 = ExponentialBackoff::calculateLinear(2, 1, 3600, 1);
        $backoff3 = ExponentialBackoff::calculateLinear(3, 1, 3600, 1);

        $this->assertEquals(2, $backoff1);
        $this->assertEquals(3, $backoff2);
        $this->assertEquals(4, $backoff3);
    }

    /**
     * Test Fibonacci backoff
     */
    public function testFibonacciBackoff(): void
    {
        // Fibonacci sequence: 1, 1, 2, 3, 5, 8, 13, ...
        $backoff1 = ExponentialBackoff::calculateFibonacci(1);
        $backoff2 = ExponentialBackoff::calculateFibonacci(2);
        $backoff3 = ExponentialBackoff::calculateFibonacci(3);
        $backoff4 = ExponentialBackoff::calculateFibonacci(4);
        $backoff5 = ExponentialBackoff::calculateFibonacci(5);

        $this->assertEquals(1, $backoff1);
        $this->assertEquals(1, $backoff2);
        $this->assertEquals(2, $backoff3);
        $this->assertEquals(3, $backoff4);
        $this->assertEquals(5, $backoff5);
    }

    /**
     * Test backoff with jitter
     */
    public function testBackoffWithJitter(): void
    {
        $backoff = ExponentialBackoff::calculateWithJitter(3, 'exponential');

        // Expected: 4 * 0.5 to 1.0 = 2 to 4
        $this->assertGreaterThanOrEqual(2, $backoff);
        $this->assertLessThanOrEqual(4, $backoff);
    }

    /**
     * Test backoff schedule
     */
    public function testBackoffSchedule(): void
    {
        $schedule = ExponentialBackoff::getSchedule(5, 'exponential');

        $this->assertCount(5, $schedule);
        $this->assertEquals(1, $schedule[1]);
        $this->assertEquals(2, $schedule[2]);
        $this->assertEquals(4, $schedule[3]);
        $this->assertEquals(8, $schedule[4]);
        $this->assertEquals(16, $schedule[5]);
    }

    /**
     * Test format backoff time - seconds
     */
    public function testFormatBackoffTimeSeconds(): void
    {
        $formatted = ExponentialBackoff::formatBackoffTime(30);

        $this->assertEquals('30s', $formatted);
    }

    /**
     * Test format backoff time - minutes
     */
    public function testFormatBackoffTimeMinutes(): void
    {
        $formatted = ExponentialBackoff::formatBackoffTime(150);

        $this->assertEquals('2m 30s', $formatted);
    }

    /**
     * Test format backoff time - hours
     */
    public function testFormatBackoffTimeHours(): void
    {
        $formatted = ExponentialBackoff::formatBackoffTime(3665);

        $this->assertEquals('1h 1m', $formatted);
    }

    /**
     * Test backoff schedule comparison
     */
    public function testBackoffScheduleComparison(): void
    {
        $exponential = ExponentialBackoff::getSchedule(5, 'exponential');
        $linear = ExponentialBackoff::getSchedule(5, 'linear');
        $fibonacci = ExponentialBackoff::getSchedule(5, 'fibonacci');

        // Exponential grows fastest
        $this->assertGreaterThan($linear[5], $exponential[5]);
        $this->assertGreaterThan($fibonacci[5], $exponential[5]);

        // Linear grows constant
        $this->assertEquals(2, $linear[1]);
        $this->assertEquals(3, $linear[2]);
        $this->assertEquals(4, $linear[3]);
    }
}
