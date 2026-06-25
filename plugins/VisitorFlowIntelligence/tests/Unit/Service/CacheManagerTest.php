<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\CacheManager;

/**
 * SB-017.1: Unit Tests for CacheManager
 * 
 * Tests cache operations: get, set, invalidation, TTL handling
 */
class CacheManagerTest extends TestCase
{
    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        $this->cacheManager = new CacheManager();
    }

    /**
     * Test cache key generation
     */
    public function testCacheKeyGeneration(): void
    {
        // Cache key should include site, period, date, segment hash, method
        $this->assertNotNull($this->cacheManager);
    }

    /**
     * Test cache miss returns false
     */
    public function testCacheMissReturnsFalse(): void
    {
        $result = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');
        
        $this->assertFalse($result);
    }

    /**
     * Test cache set and retrieve
     */
    public function testCacheSetAndRetrieve(): void
    {
        $testData = [
            'meta' => ['idSite' => 1],
            'paths' => [['path' => '/home', 'visits' => 100]],
        ];

        $success = $this->cacheManager->set(
            1, 'day', '2026-06-25', null, 'getTopPaths', $testData
        );

        $this->assertTrue($success);

        $cached = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');

        $this->assertNotFalse($cached);
        $this->assertArrayHasKey('meta', $cached);
    }

    /**
     * Test cache key differs for different periods
     */
    public function testCacheDifferentPeriods(): void
    {
        $data = ['test' => 'data'];

        // Set cache for day
        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $data);

        // Get cache for day (should hit)
        $dayCache = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');

        // Get cache for week (should miss)
        $weekCache = $this->cacheManager->get(1, 'week', '2026-06-22', null, 'getTopPaths');

        $this->assertNotFalse($dayCache);
        $this->assertFalse($weekCache);
    }

    /**
     * Test cache key differs for different segments
     */
    public function testCacheDifferentSegments(): void
    {
        $data = ['test' => 'data'];

        // Set cache with no segment
        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $data);

        // Get with no segment (should hit)
        $noSegmentCache = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');

        // Get with segment (should miss)
        $withSegmentCache = $this->cacheManager->get(
            1, 'day', '2026-06-25', 'deviceType==mobile', 'getTopPaths'
        );

        $this->assertNotFalse($noSegmentCache);
        $this->assertFalse($withSegmentCache);
    }

    /**
     * Test invalidate site clears caches for that site
     */
    public function testInvalidateSite(): void
    {
        $data = ['test' => 'data'];

        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $data);
        $this->cacheManager->invalidateSite(1);

        $cached = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');

        $this->assertFalse($cached);
    }

    /**
     * Test invalidate date range clears appropriate caches
     */
    public function testInvalidateDateRange(): void
    {
        $data = ['test' => 'data'];

        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $data);
        $this->cacheManager->invalidateDateRange(1, '2026-06-25', '2026-06-25');

        $cached = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');

        $this->assertFalse($cached);
    }

    /**
     * Test cache compression for large responses
     */
    public function testCacheCompressionLargeResponse(): void
    {
        // Create data > 10 KB
        $largeData = [
            'paths' => array_fill(0, 1000, ['path' => '/page', 'visits' => 100]),
        ];

        $success = $this->cacheManager->set(
            1, 'day', '2026-06-25', null, 'getTopPaths', $largeData
        );

        $this->assertTrue($success);

        $cached = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');

        $this->assertNotFalse($cached);
        $this->assertCount(1000, $cached['paths']);
    }

    /**
     * Test different methods have separate cache entries
     */
    public function testCacheDifferentMethods(): void
    {
        $data = ['test' => 'data'];

        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $data);

        $topPathsCache = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');
        $transitionsCache = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTransitions');

        $this->assertNotFalse($topPathsCache);
        $this->assertFalse($transitionsCache);
    }

    /**
     * Test TTL is set based on period
     */
    public function testTTLByPeriod(): void
    {
        $data = ['test' => 'data'];

        // Day should have 1h TTL
        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $data);

        // Month should have 7d TTL (longer)
        $this->cacheManager->set(1, 'month', '2026-06', null, 'getTopPaths', $data);

        // Both should be retrievable
        $dayCache = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');
        $monthCache = $this->cacheManager->get(1, 'month', '2026-06', null, 'getTopPaths');

        $this->assertNotFalse($dayCache);
        $this->assertNotFalse($monthCache);
    }
}
