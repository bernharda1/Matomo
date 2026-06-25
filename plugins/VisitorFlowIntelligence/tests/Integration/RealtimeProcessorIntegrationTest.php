<?php

declare(strict_types=1);

namespace Piwik\Tests\Integration\Plugins\VisitorFlowIntelligence;

use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\RealtimeProcessor;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\FlowEventRepository;
use Piwik\Plugins\VisitorFlowIntelligence\API\RealtimeAPI;

/**
 * SB-020.3: RealtimeProcessorIntegrationTest
 * 
 * Integration tests for real-time data processing
 */
class RealtimeProcessorIntegrationTest extends IntegrationTestCase
{
    private RealtimeProcessor $processor;
    private FlowEventRepository $repository;
    private RealtimeAPI $api;
    private int $siteId;

    /**
     * Setup test fixtures
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->siteId = $this->createSite();
        $this->repository = new FlowEventRepository();
        $this->processor = new RealtimeProcessor($this->repository, $this->siteId);
        $this->api = new RealtimeAPI($this->processor, $this->repository);
    }

    /**
     * Test real-time flows retrieval
     */
    public function testGetRealtimeFlows()
    {
        $flows = $this->processor->getRealtimeFlows();

        $this->assertIsArray($flows);
        $this->assertArrayHasKey('timestamp', $flows);
        $this->assertArrayHasKey('site_id', $flows);
        $this->assertArrayHasKey('flows', $flows);
        $this->assertArrayHasKey('total_visitors', $flows);
        $this->assertEquals($this->siteId, $flows['site_id']);
    }

    /**
     * Test real-time transitions aggregation
     */
    public function testGetRealtimeTransitions()
    {
        $transitions = $this->processor->getRealtimeTransitions();

        $this->assertIsArray($transitions);
        $this->assertArrayHasKey('timestamp', $transitions);
        $this->assertArrayHasKey('transitions', $transitions);
        $this->assertArrayHasKey('top_transitions', $transitions);
        $this->assertArrayHasKey('total_transitions', $transitions);
        $this->assertIsArray($transitions['top_transitions']);
        $this->assertLessThanOrEqual(10, count($transitions['top_transitions']));
    }

    /**
     * Test real-time dropoffs detection
     */
    public function testGetRealtimeDropoffs()
    {
        $dropoffs = $this->processor->getRealtimeDropoffs();

        $this->assertIsArray($dropoffs);
        $this->assertArrayHasKey('timestamp', $dropoffs);
        $this->assertArrayHasKey('recent_dropoffs', $dropoffs);
        $this->assertArrayHasKey('top_dropoff_locations', $dropoffs);
        $this->assertArrayHasKey('total_dropoffs', $dropoffs);
        $this->assertIsArray($dropoffs['top_dropoff_locations']);
        $this->assertLessThanOrEqual(10, count($dropoffs['top_dropoff_locations']));
    }

    /**
     * Test real-time visitor count
     */
    public function testGetRealtimeVisitorCount()
    {
        $count = $this->processor->getRealtimeVisitorCount();

        $this->assertIsArray($count);
        $this->assertArrayHasKey('current_visitors', $count);
        $this->assertArrayHasKey('trend_30_min', $count);
        $this->assertIsArray($count['trend_30_min']);
        $this->assertGreaterThanOrEqual(0, $count['current_visitors']);
    }

    /**
     * Test comprehensive real-time data
     */
    public function testGetComprehensiveRealtimeData()
    {
        $data = $this->processor->getComprehensiveRealtimeData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('flows', $data);
        $this->assertArrayHasKey('transitions', $data);
        $this->assertArrayHasKey('dropoffs', $data);
        $this->assertArrayHasKey('visitor_count', $data);
        $this->assertEquals($this->siteId, $data['site_id']);
    }

    /**
     * Test segment filtering
     */
    public function testGetRealtimeFlowsWithSegment()
    {
        $processorWithSegment = new RealtimeProcessor(
            $this->repository,
            $this->siteId,
            'deviceType==mobile'
        );

        $flows = $processorWithSegment->getRealtimeFlows();

        $this->assertIsArray($flows);
        $this->assertEquals('deviceType==mobile', $flows['segment']);
    }

    /**
     * Test API endpoint: getRealtimeFlows
     */
    public function testAPIGetRealtimeFlows()
    {
        $flows = $this->api->getRealtimeFlows($this->siteId);

        $this->assertIsArray($flows);
        $this->assertArrayHasKey('timestamp', $flows);
        $this->assertArrayHasKey('flows', $flows);
    }

    /**
     * Test API endpoint: subscribeToRealtimeEvents
     */
    public function testAPISubscribeToRealtimeEvents()
    {
        $result = $this->api->subscribeToRealtimeEvents(
            $this->siteId,
            'test_client_123',
            'deviceType==mobile'
        );

        $this->assertIsArray($result);
        $this->assertEquals('subscribed', $result['status']);
        $this->assertEquals('test_client_123', $result['client_id']);
        $this->assertEquals($this->siteId, $result['site_id']);
    }

    /**
     * Test API endpoint: unsubscribeFromRealtimeEvents
     */
    public function testAPIUnsubscribeFromRealtimeEvents()
    {
        // Subscribe first
        $this->api->subscribeToRealtimeEvents($this->siteId, 'test_client_456');

        // Then unsubscribe
        $result = $this->api->unsubscribeFromRealtimeEvents('test_client_456');

        $this->assertIsArray($result);
        $this->assertEquals('unsubscribed', $result['status']);
        $this->assertEquals('test_client_456', $result['client_id']);
    }

    /**
     * Test API endpoint: getRealtimeStatistics
     */
    public function testAPIGetRealtimeStatistics()
    {
        // Subscribe some clients
        $this->api->subscribeToRealtimeEvents($this->siteId, 'client_1');
        $this->api->subscribeToRealtimeEvents($this->siteId, 'client_2');

        $stats = $this->api->getRealtimeStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_connected', $stats);
        $this->assertArrayHasKey('max_allowed', $stats);
        $this->assertGreaterThanOrEqual(2, $stats['total_connected']);
    }

    /**
     * Test performance: Real-time data retrieval should be fast
     */
    public function testPerformanceRealtimeDataRetrieval()
    {
        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $this->processor->getComprehensiveRealtimeData();
        }

        $elapsed = microtime(true) - $startTime;
        $averageTime = $elapsed / 100;

        // Should average less than 50ms per call
        $this->assertLessThan(0.05, $averageTime);
    }

    /**
     * Test memory usage for multiple processors
     */
    public function testMemoryUsageMultipleProcessors()
    {
        $memStart = memory_get_usage();

        // Create 50 processor instances
        $processors = [];
        for ($i = 0; $i < 50; $i++) {
            $processors[] = new RealtimeProcessor($this->repository, $this->siteId);
        }

        $memEnd = memory_get_usage();
        $memUsed = ($memEnd - $memStart) / 1024 / 1024; // Convert to MB

        // Should use less than 5MB for 50 instances
        $this->assertLessThan(5, $memUsed);
    }

    /**
     * Test error handling: Invalid site ID
     */
    public function testErrorHandlingInvalidSiteId()
    {
        $this->expectException(\Exception::class);

        new RealtimeProcessor($this->repository, 0);
    }

    /**
     * Test error handling: Invalid segment
     */
    public function testErrorHandlingInvalidSegment()
    {
        $this->expectException(\Exception::class);

        new RealtimeProcessor(
            $this->repository,
            $this->siteId,
            "'; DROP TABLE logs; --"
        );
    }

    /**
     * Test concurrent subscriptions
     */
    public function testConcurrentSubscriptions()
    {
        $clientIds = [];

        // Subscribe 100 clients
        for ($i = 0; $i < 100; $i++) {
            $clientId = "client_{$i}";
            $clientIds[] = $clientId;
            $this->api->subscribeToRealtimeEvents($this->siteId, $clientId);
        }

        $stats = $this->api->getRealtimeStatistics();

        // Verify all subscribed
        $this->assertEquals(100, $stats['total_connected']);

        // Unsubscribe half
        for ($i = 0; $i < 50; $i++) {
            $this->api->unsubscribeFromRealtimeEvents($clientIds[$i]);
        }

        $stats = $this->api->getRealtimeStatistics();

        // Verify correct count
        $this->assertEquals(50, $stats['total_connected']);
    }

    /**
     * Helper: Create test site
     */
    private function createSite(): int
    {
        // Use existing test site or create new
        $idSite = self::getTestRawSiteIdFromFixtures();
        return $idSite ?? 1;
    }
}
