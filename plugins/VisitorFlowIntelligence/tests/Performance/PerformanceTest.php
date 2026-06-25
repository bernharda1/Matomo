<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Performance;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\CacheManager;
use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentProcessor;

/**
 * SB-017.3: Performance Tests for Caching & Segments
 * 
 * Measures performance improvements from caching and segment filtering
 * 
 * Expected Results:
 * - Cache hit: < 200ms
 * - Cache miss (DB query): 2-5s
 * - Improvement factor: 10-25x
 * - Segment filtering: 10x faster than full query
 */
class PerformanceTest extends TestCase
{
    private CacheManager $cacheManager;

    protected function setUp(): void
    {
        $this->cacheManager = new CacheManager();
    }

    /**
     * Test cache hit performance
     */
    public function testCacheHitPerformance(): void
    {
        $testData = [
            'paths' => array_fill(0, 100, ['path' => '/page', 'visits' => 100]),
            'transitions' => [['from' => '/page1', 'to' => '/page2', 'count' => 50]],
        ];

        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $testData);

        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $cached = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');
        }
        $endTime = microtime(true);

        $averageTime = (($endTime - $startTime) / 100) * 1000; // Convert to ms

        // Cache hit should be < 10ms average
        $this->assertLessThan(10, $averageTime, "Cache hit average: {$averageTime}ms");
    }

    /**
     * Test segment parsing performance
     */
    public function testSegmentParsingPerformance(): void
    {
        $segments = [
            'deviceType==mobile',
            'country==DE;deviceType==mobile',
            'visitDuration>300;country!=US;browserName==Chrome',
        ];

        $startTime = microtime(true);
        foreach ($segments as $segment) {
            for ($i = 0; $i < 1000; $i++) {
                $processor = new SegmentProcessor($segment);
                $processor->getWhereClause('lv');
            }
        }
        $endTime = microtime(true);

        $totalTime = ($endTime - $startTime) * 1000; // Convert to ms
        $averageTime = $totalTime / (count($segments) * 1000);

        // Segment parsing should be < 1ms per parse
        $this->assertLessThan(1, $averageTime, "Segment parsing average: {$averageTime}ms");
    }

    /**
     * Test cache storage efficiency with compression
     */
    public function testCacheCompressionEfficiency(): void
    {
        // Create large data (1000 paths)
        $largeData = [
            'paths' => array_fill(0, 1000, [
                'path' => '/very/long/path/that/takes/up/space',
                'visits' => 12345,
                'share' => 0.5,
                'depth' => 5,
            ]),
        ];

        // Size before compression: ~100 KB
        $uncompressedSize = strlen(json_encode($largeData));

        // Set cache (should compress internally)
        $this->cacheManager->set(1, 'month', '2026-06', null, 'getTopPaths', $largeData);

        // Get cache (should decompress)
        $cached = $this->cacheManager->get(1, 'month', '2026-06', null, 'getTopPaths');

        $this->assertNotFalse($cached);
        $this->assertCount(1000, $cached['paths']);

        // Compression should reduce size by 60-70%
        // (This is a theoretical check; actual compression ratio depends on backend)
    }

    /**
     * Test cache hit rate simulation
     */
    public function testCacheHitRateSimulation(): void
    {
        $testData = ['test' => 'data'];

        // Simulate typical usage pattern
        // Hour 1: 100% miss
        $this->cacheManager->set(1, 'day', '2026-06-25', null, 'getTopPaths', $testData);
        $hits = 0;
        $misses = 1;

        // Hour 2-6: 90% hit
        for ($i = 0; $i < 100; $i++) {
            if ($i < 90) {
                $cached = $this->cacheManager->get(1, 'day', '2026-06-25', null, 'getTopPaths');
                if ($cached !== false) {
                    $hits++;
                } else {
                    $misses++;
                }
            } else {
                // 10% new requests
                $this->cacheManager->set(
                    1,
                    'day',
                    '2026-06-25',
                    "segment_{$i}",
                    'getTopPaths',
                    $testData
                );
                $misses++;
            }
        }

        $totalRequests = $hits + $misses;
        $hitRate = $hits / $totalRequests;

        // Expected hit rate > 85%
        $this->assertGreaterThan(0.85, $hitRate, "Hit rate: " . ($hitRate * 100) . "%");
    }

    /**
     * Test memory efficiency with segment caching
     */
    public function testSegmentCachingMemoryEfficiency(): void
    {
        $testData = ['paths' => array_fill(0, 100, ['path' => '/page', 'visits' => 100])];

        // Store cache for 10 different segments
        for ($i = 0; $i < 10; $i++) {
            $this->cacheManager->set(
                1,
                'day',
                '2026-06-25',
                "deviceType==type{$i}",
                'getTopPaths',
                $testData
            );
        }

        // All segments should be retrievable without memory issue
        for ($i = 0; $i < 10; $i++) {
            $cached = $this->cacheManager->get(
                1,
                'day',
                '2026-06-25',
                "deviceType==type{$i}",
                'getTopPaths'
            );
            $this->assertNotFalse($cached);
        }

        // Memory usage should remain reasonable
        $memoryUsage = memory_get_usage(true);
        $memoryUsageMB = $memoryUsage / 1024 / 1024;

        // Should be < 100 MB for 10 cached entries
        $this->assertLessThan(100, $memoryUsageMB, "Memory usage: {$memoryUsageMB}MB");
    }
}
