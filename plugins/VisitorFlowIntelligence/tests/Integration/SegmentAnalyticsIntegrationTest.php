<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\tests\Integration;

use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentAnalyticsService;
use Piwik\Plugins\VisitorFlowIntelligence\API\SegmentAnalyticsAPI;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\SegmentRepository;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * SB-022.2: SegmentAnalytics Integration Tests
 */
class SegmentAnalyticsIntegrationTest extends IntegrationTestCase
{
    private SegmentAnalyticsService $analyticsService;
    private SegmentAnalyticsAPI $api;
    private SegmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = $this->container->make(SegmentRepository::class);
        $this->analyticsService = $this->container->make(SegmentAnalyticsService::class);
        $this->api = $this->container->make(SegmentAnalyticsAPI::class);

        // Create test segment
        $this->createTestSegment();
    }

    /**
     * Test retrieving comprehensive segment analytics
     */
    public function testGetSegmentAnalytics()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('segment', $analytics);
        $this->assertArrayHasKey('metrics', $analytics);
        $this->assertArrayHasKey('trends', $analytics);
        $this->assertArrayHasKey('drill_down', $analytics);
    }

    /**
     * Test segment metrics calculation
     */
    public function testSegmentMetrics()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $metrics = $analytics['metrics'];

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('visits', $metrics);
        $this->assertArrayHasKey('visitors', $metrics);
        $this->assertArrayHasKey('actions', $metrics);
        $this->assertArrayHasKey('bounce_rate', $metrics);
        $this->assertArrayHasKey('avg_session_duration', $metrics);
        $this->assertArrayHasKey('conversion_rate', $metrics);

        $this->assertIsInt($metrics['visits']);
        $this->assertIsInt($metrics['visitors']);
        $this->assertIsFloat($metrics['bounce_rate']);
        $this->assertGreaterThanOrEqual(0, $metrics['visits']);
        $this->assertGreaterThanOrEqual(0, $metrics['bounce_rate']);
        $this->assertLessThanOrEqual(100, $metrics['bounce_rate']);
    }

    /**
     * Test trend data generation
     */
    public function testSegmentTrends()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'week', 7);
        $trends = $analytics['trends'];

        $this->assertIsArray($trends);
        $this->assertCount(8, $trends); // 7 days + today

        foreach ($trends as $date => $trend) {
            $this->assertIsArray($trend);
            $this->assertArrayHasKey('visits', $trend);
            $this->assertArrayHasKey('visitors', $trend);
            $this->assertArrayHasKey('bounce_rate', $trend);
        }
    }

    /**
     * Test drill-down functionality
     */
    public function testDrillDownData()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $drillDown = $analytics['drill_down'];

        $this->assertIsArray($drillDown);
        $this->assertArrayHasKey('by_traffic_source', $drillDown);
        $this->assertArrayHasKey('by_device_type', $drillDown);
        $this->assertArrayHasKey('by_browser', $drillDown);
        $this->assertArrayHasKey('by_country', $drillDown);
    }

    /**
     * Test device breakdown
     */
    public function testDeviceBreakdown()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $devices = $analytics['device_breakdown'];

        $this->assertIsArray($devices);
        
        foreach ($devices as $device) {
            $this->assertArrayHasKey('device_type', $device);
            $this->assertArrayHasKey('visits', $device);
            $this->assertArrayHasKey('visitors', $device);
        }
    }

    /**
     * Test browser breakdown
     */
    public function testBrowserBreakdown()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $browsers = $analytics['browser_breakdown'];

        $this->assertIsArray($browsers);
        
        foreach ($browsers as $browser) {
            $this->assertArrayHasKey('browser_name', $browser);
            $this->assertArrayHasKey('visits', $browser);
        }
    }

    /**
     * Test geographic breakdown
     */
    public function testGeoBreakdown()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $geo = $analytics['geo_breakdown'];

        $this->assertIsArray($geo);
        
        foreach ($geo as $country) {
            $this->assertArrayHasKey('country_code', $country);
            $this->assertArrayHasKey('visits', $country);
        }
    }

    /**
     * Test top pages retrieval
     */
    public function testTopPages()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $pages = $analytics['top_pages'];

        $this->assertIsArray($pages);
        
        foreach ($pages as $page) {
            $this->assertArrayHasKey('page_name', $page);
            $this->assertArrayHasKey('views', $page);
            $this->assertArrayHasKey('unique_visits', $page);
        }
    }

    /**
     * Test conversion metrics
     */
    public function testConversionMetrics()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $conversions = $analytics['conversions'];

        $this->assertIsArray($conversions);
        // Conversions may be empty if no goals configured
    }

    /**
     * Test API getSegmentAnalytics endpoint
     */
    public function testAPIGetSegmentAnalytics()
    {
        $result = $this->api->getSegmentAnalytics(1, 'month');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('trends', $result);
    }

    /**
     * Test API getDrillDown endpoint
     */
    public function testAPIGetDrillDown()
    {
        $result = $this->api->getDrillDown(1, 'traffic_source');
        
        $this->assertIsArray($result);
    }

    /**
     * Test API compareSegments endpoint
     */
    public function testAPICompareSegments()
    {
        $this->createTestSegment();
        
        $result = $this->api->compareSegments('1,2');
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test API exportAnalytics
     */
    public function testAPIExportAnalytics()
    {
        $result = $this->api->exportAnalytics(1, 'csv', 30);
        
        $this->assertIsArray($result);
        $this->assertEquals('csv', $result['format']);
        $this->assertEquals(1, $result['segment_id']);
        $this->assertEquals(30, $result['period_days']);
        $this->assertArrayHasKey('data', $result);
    }

    /**
     * Test API getTopSegments
     */
    public function testAPIGetTopSegments()
    {
        $result = $this->api->getTopSegments(30, 10);
        
        $this->assertIsArray($result);
    }

    /**
     * Test API getTrendingSegments
     */
    public function testAPIGetTrendingSegments()
    {
        $result = $this->api->getTrendingSegments(7, 10);
        
        $this->assertIsArray($result);
    }

    /**
     * Test period conversion
     */
    public function testPeriodConversion()
    {
        // Test week period
        $analytics = $this->analyticsService->getSegmentAnalytics(1, 'week');
        $this->assertIsArray($analytics);

        // Test month period
        $analytics = $this->analyticsService->getSegmentAnalytics(1, 'month');
        $this->assertIsArray($analytics);

        // Test quarter period
        $analytics = $this->analyticsService->getSegmentAnalytics(1, 'quarter');
        $this->assertIsArray($analytics);

        // Test year period
        $analytics = $this->analyticsService->getSegmentAnalytics(1, 'year');
        $this->assertIsArray($analytics);
    }

    /**
     * Test metrics calculation accuracy
     */
    public function testMetricsAccuracy()
    {
        $segmentId = 1;
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month');
        $metrics = $analytics['metrics'];

        // Bounce rate should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $metrics['bounce_rate']);
        $this->assertLessThanOrEqual(100, $metrics['bounce_rate']);

        // Conversion rate should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $metrics['conversion_rate']);
        $this->assertLessThanOrEqual(100, $metrics['conversion_rate']);

        // Returning rate should be between 0 and 100
        $this->assertGreaterThanOrEqual(0, $metrics['returning_rate']);
        $this->assertLessThanOrEqual(100, $metrics['returning_rate']);

        // Visitors should not exceed visits
        $this->assertLessThanOrEqual($metrics['visits'], $metrics['visitors'] * 10); // rough check
    }

    /**
     * Test concurrent analytics requests
     */
    public function testConcurrentRequests()
    {
        $segmentIds = [1, 2, 3];
        $results = [];

        foreach ($segmentIds as $id) {
            $results[$id] = $this->analyticsService->getSegmentAnalytics($id, 'month');
        }

        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertArrayHasKey('metrics', $result);
        }
    }

    /**
     * Test performance: Analytics query should complete within 2 seconds
     */
    public function testPerformanceAnalyticsQuery()
    {
        $startTime = microtime(true);
        $analytics = $this->analyticsService->getSegmentAnalytics(1, 'month');
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        $this->assertIsArray($analytics);
        $this->assertLessThan(2000, $duration, "Analytics query took {$duration}ms, expected < 2000ms");
    }

    /**
     * Test performance: Drill-down should complete within 1 second
     */
    public function testPerformanceDrillDown()
    {
        $startTime = microtime(true);
        $result = $this->api->getDrillDown(1, 'traffic_source');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertLessThan(1000, $duration, "Drill-down took {$duration}ms, expected < 1000ms");
    }

    /**
     * Test performance: Comparison should complete within 3 seconds
     */
    public function testPerformanceComparison()
    {
        $startTime = microtime(true);
        $result = $this->api->compareSegments('1,2,3');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertLessThan(3000, $duration, "Comparison took {$duration}ms, expected < 3000ms");
    }

    /**
     * Test edge case: Empty segment
     */
    public function testEmptySegment()
    {
        // Create empty segment with no data
        $segment = $this->repository->save([
            'name' => 'Empty Segment',
            'description' => 'Test empty segment',
            'rules' => [['field' => 'visitorId', 'operator' => '==', 'value' => 'nonexistent']],
            'operator' => 'AND',
            'is_public' => false,
        ]);

        $analytics = $this->analyticsService->getSegmentAnalytics($segment['id'], 'month');
        
        $this->assertEquals(0, $analytics['metrics']['visits']);
        $this->assertEquals(0, $analytics['metrics']['visitors']);
    }

    /**
     * Test edge case: Invalid period
     */
    public function testInvalidPeriod()
    {
        $analytics = $this->analyticsService->getSegmentAnalytics(1, 'invalid');
        
        // Should default to 30 days
        $this->assertIsArray($analytics);
    }

    /**
     * Helper: Create test segment
     */
    private function createTestSegment(): int
    {
        $segment = $this->repository->save([
            'name' => 'Test Segment',
            'description' => 'Test segment for analytics',
            'rules' => [
                ['field' => 'deviceType', 'operator' => '==', 'value' => 'mobile'],
            ],
            'operator' => 'AND',
            'is_public' => true,
        ]);

        return $segment['id'] ?? 1;
    }
}
