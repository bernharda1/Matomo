<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\RateLimiter;

/**
 * SB-018.3: RateLimiterTest
 * 
 * Tests for rate limiting and DDoS protection
 */
class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear rate limiter state before each test
        RateLimiter::reset('test_identifier');
        RateLimiter::clearBackoff('test_identifier');
    }

    /**
     * Test request allowed within limit
     */
    public function testRequestAllowedWithinLimit(): void
    {
        $this->assertTrue(RateLimiter::isAllowed('test_identifier', 10, 60));
    }

    /**
     * Test increment counter
     */
    public function testIncrementCounter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::increment('test_identifier');
        }

        $this->assertFalse(RateLimiter::isAllowed('test_identifier', 5, 60));
        $this->assertTrue(RateLimiter::isAllowed('test_identifier', 6, 60));
    }

    /**
     * Test reset counter
     */
    public function testResetCounter(): void
    {
        RateLimiter::increment('test_identifier');
        RateLimiter::increment('test_identifier');
        
        RateLimiter::reset('test_identifier');
        
        $this->assertTrue(RateLimiter::isAllowed('test_identifier', 1, 60));
    }

    /**
     * Test different identifiers have separate limits
     */
    public function testDifferentIdentifiersSeparate(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::increment('user_1');
        }

        $this->assertFalse(RateLimiter::isAllowed('user_1', 5, 60));
        $this->assertTrue(RateLimiter::isAllowed('user_2', 5, 60));
    }

    /**
     * Test violation tracking
     */
    public function testViolationTracking(): void
    {
        RateLimiter::recordViolation('test_identifier');
        RateLimiter::recordViolation('test_identifier');
        
        $this->assertEquals(2, RateLimiter::getViolationCount('test_identifier'));
    }

    /**
     * Test exponential backoff calculation
     */
    public function testExponentialBackoff(): void
    {
        // No violations = 0 seconds backoff
        $this->assertEquals(0, RateLimiter::getExponentialBackoff('test_identifier'));

        // 1 violation = 2 seconds
        RateLimiter::recordViolation('test_identifier');
        $this->assertEquals(2, RateLimiter::getExponentialBackoff('test_identifier'));

        // 2 violations = 4 seconds
        RateLimiter::recordViolation('test_identifier');
        $this->assertEquals(4, RateLimiter::getExponentialBackoff('test_identifier'));

        // 3 violations = 8 seconds
        RateLimiter::recordViolation('test_identifier');
        $this->assertEquals(8, RateLimiter::getExponentialBackoff('test_identifier'));
    }

    /**
     * Test backoff status
     */
    public function testBackoffStatus(): void
    {
        RateLimiter::recordViolation('test_identifier');
        
        $this->assertTrue(RateLimiter::isInBackoff('test_identifier'));
    }

    /**
     * Test clear backoff
     */
    public function testClearBackoff(): void
    {
        RateLimiter::recordViolation('test_identifier');
        $this->assertTrue(RateLimiter::isInBackoff('test_identifier'));

        RateLimiter::clearBackoff('test_identifier');
        $this->assertFalse(RateLimiter::isInBackoff('test_identifier'));
    }

    /**
     * Test remaining requests
     */
    public function testRemainingRequests(): void
    {
        RateLimiter::increment('test_identifier');
        RateLimiter::increment('test_identifier');

        $remaining = RateLimiter::getRemaining('test_identifier', 10, 60);
        $this->assertEquals(8, $remaining);
    }

    /**
     * Test remaining requests capped at zero
     */
    public function testRemainingRequestsCappedAtZero(): void
    {
        for ($i = 0; $i < 15; $i++) {
            RateLimiter::increment('test_identifier');
        }

        $remaining = RateLimiter::getRemaining('test_identifier', 10, 60);
        $this->assertEquals(0, $remaining);
    }

    /**
     * Test retry-after header
     */
    public function testRetryAfterHeader(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::increment('test_identifier');
        }

        $retryAfter = RateLimiter::getRetryAfter('test_identifier', 60);
        
        // Retry after should be > 0 and <= 60
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    /**
     * Test per-IP rate limiting scenario
     */
    public function testPerIPRateLimiting(): void
    {
        $ipAddress = '192.168.1.1';
        $limit = 100;
        $window = 3600; // 1 hour

        // Simulate 100 requests
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue(RateLimiter::isAllowed($ipAddress, $limit, $window));
            RateLimiter::increment($ipAddress);
        }

        // 101st request should be denied
        $this->assertFalse(RateLimiter::isAllowed($ipAddress, $limit, $window));
    }

    /**
     * Test progressive backoff scenario
     */
    public function testProgressiveBackoffScenario(): void
    {
        $identifier = 'attacker_ip';

        // 1st violation
        RateLimiter::recordViolation($identifier);
        $this->assertTrue(RateLimiter::isInBackoff($identifier));
        $this->assertEquals(2, RateLimiter::getExponentialBackoff($identifier));

        // 2nd violation
        RateLimiter::recordViolation($identifier);
        $this->assertEquals(4, RateLimiter::getExponentialBackoff($identifier));

        // 3rd violation
        RateLimiter::recordViolation($identifier);
        $this->assertEquals(8, RateLimiter::getExponentialBackoff($identifier));

        // 4th violation
        RateLimiter::recordViolation($identifier);
        $this->assertEquals(16, RateLimiter::getExponentialBackoff($identifier));
    }

    /**
     * Test backoff cap at max seconds
     */
    public function testBackoffCapAtMaxSeconds(): void
    {
        $identifier = 'persistent_attacker';

        // Record many violations to reach exponential backoff cap
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::recordViolation($identifier);
        }

        // Backoff should be capped at 3600 seconds
        $backoff = RateLimiter::getExponentialBackoff($identifier);
        $this->assertEquals(3600, $backoff);
    }

    /**
     * Test cleanup of expired entries
     */
    public function testCleanupExpiredEntries(): void
    {
        RateLimiter::increment('old_identifier');
        RateLimiter::increment('new_identifier');

        // Cleanup with maxAge = 0 (should remove everything)
        RateLimiter::cleanup(0);

        // All should be reset
        $this->assertTrue(RateLimiter::isAllowed('old_identifier', 1, 60));
        $this->assertTrue(RateLimiter::isAllowed('new_identifier', 1, 60));
    }

    /**
     * Test multiple concurrent identifiers
     */
    public function testMultipleConcurrentIdentifiers(): void
    {
        $users = ['user1', 'user2', 'user3', 'user4', 'user5'];

        foreach ($users as $user) {
            for ($i = 0; $i < 10; $i++) {
                RateLimiter::increment($user);
            }
        }

        // All should be at limit 10
        foreach ($users as $user) {
            $this->assertFalse(RateLimiter::isAllowed($user, 10, 60));
            $this->assertTrue(RateLimiter::isAllowed($user, 11, 60));
        }
    }
}
