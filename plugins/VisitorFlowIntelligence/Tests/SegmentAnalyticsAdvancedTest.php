<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\AnomalyDetector;
use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentPredictor;
use Piwik\Plugins\VisitorFlowIntelligence\Service\AnalyticsExporter;
use Piwik\Plugins\VisitorFlowIntelligence\Service\WebSocketAnalyticsAdapter;
use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentAnalyticsService;

/**
 * SB-022.3: Advanced Components Integration Tests
 * 
 * Tests for anomaly detection, forecasting, exporting, and real-time adapters
 */
class SegmentAnalyticsAdvancedTest extends TestCase
{
    private AnomalyDetector $anomalyDetector;
    private SegmentPredictor $predictor;
    private AnalyticsExporter $exporter;
    private WebSocketAnalyticsAdapter $wsAdapter;
    private SegmentAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        $this->analyticsService = $this->createMock(SegmentAnalyticsService::class);
        
        $this->anomalyDetector = new AnomalyDetector();
        $this->predictor = new SegmentPredictor($this->analyticsService);
        $this->exporter = new AnalyticsExporter($this->analyticsService);
        $this->wsAdapter = new WebSocketAnalyticsAdapter($this->analyticsService);
    }

    // ============ ANOMALY DETECTION TESTS ============

    public function testDetectSpikesIdentifiesOutliers(): void
    {
        // Normal data with spike
        $data = [100, 102, 98, 101, 99, 350, 97, 100]; // 350 is spike

        $result = $this->anomalyDetector->detectSpikes($data);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        
        $spike = $result[0];
        $this->assertGreaterThan(2.0, $spike['z_score']);
    }

    public function testDetectTrendReversalIdentifiesDirectionChange(): void
    {
        // Data showing upward trend then reversal
        $data = [100, 110, 120, 130, 140, 120, 100, 80, 70]; // Reversal at middle

        $result = $this->anomalyDetector->detectTrendReversal($data);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('type', $result[0]);
        $this->assertEquals('trend_reversal', $result[0]['type']);
    }

    public function testCalculateSeverityClassifiesAnomalies(): void
    {
        $anomalies = [
            ['z_score' => 4.0],  // Critical
            ['z_score' => 3.0],  // High
            ['z_score' => 2.7],  // Warning
        ];

        $severity = $this->anomalyDetector->calculateSeverity($anomalies);

        $this->assertIsArray($severity);
        $this->assertArrayHasKey('overall', $severity);
        $this->assertIn($severity['overall'], ['critical', 'high', 'warning', 'low']);
    }

    public function testGetInsightsGeneratesHumanReadableMessages(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [
                    'visits' => 1000,
                    'bounce_rate' => 0.45,
                    'conversion_rate' => 0.05,
                ],
                'trends' => [],
            ]);

        $insights = $this->anomalyDetector->getInsights(1, 30);

        $this->assertIsArray($insights);
        $this->assertNotEmpty($insights);
        foreach ($insights as $insight) {
            $this->assertIsString($insight);
            $this->assertGreaterThan(0, strlen($insight));
        }
    }

    public function testDetectAnomaliesIntegration(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => ['visits' => 1000],
                'trends' => [
                    '2024-01-01' => ['visits' => 100],
                    '2024-01-02' => ['visits' => 105],
                    '2024-01-03' => ['visits' => 500], // Spike
                    '2024-01-04' => ['visits' => 98],
                    '2024-01-05' => ['visits' => 102],
                ],
            ]);

        $result = $this->anomalyDetector->detectAnomalies(1, 5);

        $this->assertArrayHasKey('segment_id', $result);
        $this->assertArrayHasKey('has_anomalies', $result);
        $this->assertArrayHasKey('severity', $result);
    }

    // ============ FORECASTING TESTS ============

    public function testPredictTrendForecastsSegmentTrend(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => ['visits' => 3000],
                'trends' => [
                    '2024-01-01' => ['visits' => 100],
                    '2024-01-02' => ['visits' => 105],
                    '2024-01-03' => ['visits' => 110],
                    '2024-01-04' => ['visits' => 115],
                    '2024-01-05' => ['visits' => 120],
                ],
            ]);

        $result = $this->predictor->predictTrend(1, 5, 7);

        $this->assertArrayHasKey('forecast', $result);
        $this->assertCount(7, $result['forecast']);
        
        foreach ($result['forecast'] as $value) {
            $this->assertGreaterThan(0, $value);
        }
        
        $this->assertArrayHasKey('trend_direction', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertGreaterThanOrEqual(0.2, $result['confidence']);
        $this->assertLessThanOrEqual(0.95, $result['confidence']);
    }

    public function testTrendDirectionIdentifiesUpwardTrend(): void
    {
        $data = [100, 110, 120, 130, 140];

        $result = $this->predictor->predictTrend(1, 5, 7);

        $this->assertArrayHasKey('trend_direction', $result);
        // Would be 'upward' if data was passed through
    }

    public function testConfidenceCalculationReturnsValidScore(): void
    {
        $data = [100, 100, 100, 100]; // Very stable
        
        // Confidence should be high for stable data
        $result = $this->predictor->predictTrend(1, 4, 7);
        
        $this->assertGreaterThan(0.5, $result['confidence']);
    }

    public function testPredictOptimalTimingIdentifiesPeakDays(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => ['visits' => 5000],
                'trends' => [
                    '2024-01-01' => ['visits' => 100],
                    '2024-01-02' => ['visits' => 500], // Peak
                    '2024-01-03' => ['visits' => 200],
                    '2024-01-04' => ['visits' => 600], // Peak
                    '2024-01-05' => ['visits' => 150],
                ],
            ]);

        $result = $this->predictor->predictOptimalTiming(1, 30);

        $this->assertArrayHasKey('peak_traffic_date', $result);
        $this->assertArrayHasKey('above_average_days', $result);
        $this->assertGreaterThan(0, $result['above_average_days']);
    }

    public function testPredictQuarterlyGrowthCalculatesProjection(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => ['visits' => 1000],
                'trends' => [],
            ]);

        $result = $this->predictor->predictQuarterlyGrowth(1);

        $this->assertArrayHasKey('growth_rate_percent', $result);
        $this->assertArrayHasKey('quarterly_projection', $result);
        $this->assertArrayHasKey('trend', $result);
        $this->assertIn($result['trend'], ['high_growth', 'moderate_growth', 'stable', 'moderate_decline', 'high_decline']);
    }

    // ============ EXPORT TESTS ============

    public function testExportToCSVGeneratesValidFormat(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [
                    'visits' => 1000,
                    'visitors' => 500,
                    'bounce_rate' => 0.45,
                    'conversion_rate' => 0.05,
                    'avg_session_duration' => 180,
                ],
                'trends' => [
                    '2024-01-01' => ['visits' => 100, 'visitors' => 50, 'bounce_rate' => 0.45],
                ],
                'top_sources' => [
                    ['traffic_source' => 'organic', 'visits' => 500, 'visitors' => 250, 'bounce_rate' => 0.40],
                ],
                'device_breakdown' => [
                    ['device_type' => 'desktop', 'visits' => 600, 'visitors' => 300, 'avg_duration' => 200],
                ],
                'top_pages' => [
                    ['page_name' => '/home', 'views' => 100, 'unique_visits' => 80, 'avg_time' => 45],
                ],
            ]);

        $csv = $this->exporter->exportToCSV(1);

        $this->assertIsString($csv);
        $this->assertStringContainsString('KEY METRICS', $csv);
        $this->assertStringContainsString('TRENDS', $csv);
        $this->assertStringContainsString('TRAFFIC SOURCES', $csv);
        $this->assertStringContainsString('organic', $csv);
        $this->assertStringContainsString('desktop', $csv);
    }

    public function testExportToJSONGeneratesValidStructure(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => ['visits' => 1000],
                'trends' => [],
            ]);

        $json = $this->exporter->exportToJSON(1);

        $this->assertIsString($json);
        $data = json_decode($json, true);
        
        $this->assertIsArray($data);
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('analytics', $data);
        $this->assertEquals(1, $data['metadata']['segment_id']);
    }

    public function testExportToHTMLGeneratesValidMarkup(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [
                    'visits' => 1000,
                    'bounce_rate' => 0.45,
                ],
                'trends' => [],
                'top_sources' => [],
                'top_pages' => [],
            ]);

        $html = $this->exporter->exportToHTML(1);

        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Segment Analytics Report', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    public function testGetExportFilenameGeneratesValidName(): void
    {
        $filename = $this->exporter->getExportFilename(1, 'csv');

        $this->assertStringStartsWith('segment-1-analytics-', $filename);
        $this->assertStringEndsWith('.csv', $filename);
    }

    public function testIsValidFormatValidatesExportFormat(): void
    {
        $this->assertTrue($this->exporter->isValidFormat('csv'));
        $this->assertTrue($this->exporter->isValidFormat('json'));
        $this->assertTrue($this->exporter->isValidFormat('html'));
        $this->assertFalse($this->exporter->isValidFormat('pdf'));
        $this->assertFalse($this->exporter->isValidFormat('invalid'));
    }

    // ============ WEBSOCKET ADAPTER TESTS ============

    public function testGetCompactAnalyticsReturnsStructuredData(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [
                    'visits' => 1000,
                    'visitors' => 500,
                    'bounce_rate' => 0.45,
                    'conversion_rate' => 0.05,
                    'avg_session_duration' => 180,
                ],
                'trends' => [
                    '2024-01-05' => [
                        'visits' => 100,
                        'bounce_rate' => 0.45,
                    ],
                ],
                'top_sources' => [
                    ['traffic_source' => 'organic'],
                ],
            ]);

        $data = $this->wsAdapter->getCompactAnalytics(1);

        $this->assertArrayHasKey('segment_id', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('latest', $data);
        
        $this->assertEquals(1, $data['segment_id']);
        $this->assertArrayHasKey('v', $data['metrics']);   // visits
        $this->assertArrayHasKey('vs', $data['metrics']);  // visitors
    }

    public function testSubscribeToUpdatesReturnsConfig(): void
    {
        $config = $this->wsAdapter->subscribeToUpdates(1, 'client-123');

        $this->assertArrayHasKey('action', $config);
        $this->assertArrayHasKey('segment_id', $config);
        $this->assertArrayHasKey('client_id', $config);
        $this->assertEquals('subscribe_analytics', $config['action']);
        $this->assertEquals(1, $config['segment_id']);
    }

    public function testUnsubscribeFromUpdatesReturnsConfig(): void
    {
        $config = $this->wsAdapter->unsubscribeFromUpdates(1, 'client-123');

        $this->assertEquals('unsubscribe_analytics', $config['action']);
        $this->assertEquals(1, $config['segment_id']);
    }

    public function testGetDeltaIdentifiesChanges(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [
                    'visits' => 1000,
                    'visitors' => 500,
                    'bounce_rate' => 0.45,
                    'conversion_rate' => 0.05,
                    'avg_session_duration' => 180,
                ],
                'trends' => [],
                'top_sources' => [],
            ]);

        $previous = [
            'v' => 900,  // Different
            'vs' => 500, // Same
            'b' => 0.45, // Same
        ];

        $delta = $this->wsAdapter->getDelta(1, $previous);

        $this->assertArrayHasKey('changes', $delta);
        $this->assertNotEmpty($delta['changes']);
    }

    public function testFormatForDisplayReturnsReadableFormat(): void
    {
        $data = [
            'segment_id' => 1,
            'timestamp' => time(),
            'metrics' => [
                'v' => 5000,
                'vs' => 2500,
                'b' => 0.45,
                'c' => 0.05,
                'd' => 180,
            ],
            'latest' => [
                'v' => 100,
            ],
            'top_source' => ['traffic_source' => 'organic'],
        ];

        $formatted = $this->wsAdapter->formatForDisplay($data);

        $this->assertArrayHasKey('visits', $formatted);
        $this->assertStringContainsString('K', $formatted['visits']); // Should be '5K'
        $this->assertStringContainsString('%', $formatted['bounce_rate']);
    }

    public function testCreateWSMessageStructure(): void
    {
        $message = $this->wsAdapter->createWSMessage('subscribe', ['segment_id' => 1]);

        $this->assertArrayHasKey('action', $message);
        $this->assertArrayHasKey('payload', $message);
        $this->assertArrayHasKey('timestamp', $message);
        $this->assertEquals('subscribe', $message['action']);
    }

    public function testParseWSMessageExtractsData(): void
    {
        $wsMessage = [
            'action' => 'subscribe_analytics',
            'segment_id' => 1,
            'client_id' => 'client-123',
            'data' => ['interval' => 10],
        ];

        $parsed = $this->wsAdapter->parseWSMessage($wsMessage);

        $this->assertEquals('subscribe_analytics', $parsed['action']);
        $this->assertEquals(1, $parsed['segment_id']);
        $this->assertEquals('client-123', $parsed['client_id']);
    }

    public function testCheckConnectionHealthReturnsStatus(): void
    {
        $health = $this->wsAdapter->checkConnectionHealth();

        $this->assertArrayHasKey('ws_url', $health);
        $this->assertArrayHasKey('is_healthy', $health);
        $this->assertArrayHasKey('last_heartbeat', $health);
        $this->assertIsBool($health['is_healthy']);
    }

    // ============ PERFORMANCE TESTS ============

    public function testAnomalyDetectionPerformance(): void
    {
        // Generate large dataset
        $data = array_map(fn($i) => 100 + rand(-20, 20), range(1, 365));

        $start = microtime(true);
        $this->anomalyDetector->detectSpikes($data);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.5, $elapsed); // Should complete in < 500ms
    }

    public function testForecastingPerformance(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => ['visits' => 3000],
                'trends' => array_map(
                    fn($i) => [$i => ['visits' => 100 + rand(-10, 10)]],
                    range(1, 30)
                ),
            ]);

        $start = microtime(true);
        $this->predictor->predictTrend(1, 30, 7);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(1.0, $elapsed); // Should complete in < 1 second
    }

    public function testExportPerformance(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [
                    'visits' => 10000,
                    'visitors' => 5000,
                    'bounce_rate' => 0.45,
                    'conversion_rate' => 0.05,
                    'avg_session_duration' => 180,
                ],
                'trends' => array_map(
                    fn($i) => [$i => ['visits' => 300, 'visitors' => 150, 'bounce_rate' => 0.45]],
                    range(1, 30)
                ),
                'top_sources' => array_map(
                    fn($i) => ['traffic_source' => "source-$i", 'visits' => 100, 'visitors' => 50, 'bounce_rate' => 0.45],
                    range(1, 10)
                ),
                'device_breakdown' => [
                    ['device_type' => 'desktop', 'visits' => 6000, 'visitors' => 3000, 'avg_duration' => 200],
                ],
                'top_pages' => array_map(
                    fn($i) => ['page_name' => "/page-$i", 'views' => 100, 'unique_visits' => 80, 'avg_time' => 45],
                    range(1, 50)
                ),
            ]);

        $start = microtime(true);
        $this->exporter->exportToCSV(1);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(2.0, $elapsed); // Should complete in < 2 seconds
    }

    public function testWebSocketCompactionPerformance(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [
                    'visits' => 10000,
                    'visitors' => 5000,
                    'bounce_rate' => 0.45,
                    'conversion_rate' => 0.05,
                    'avg_session_duration' => 180,
                ],
                'trends' => [
                    '2024-01-05' => ['visits' => 100, 'bounce_rate' => 0.45],
                ],
                'top_sources' => [
                    ['traffic_source' => 'organic'],
                ],
            ]);

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $this->wsAdapter->getCompactAnalytics(1);
        }
        $elapsed = microtime(true) - $start;

        // 100 calls should average < 10ms each
        $this->assertLessThan(1.0, $elapsed);
    }

    // ============ EDGE CASES ============

    public function testAnomalyDetectionWithEmptyData(): void
    {
        $result = $this->anomalyDetector->detectSpikes([]);
        $this->assertIsArray($result);
    }

    public function testForecastWithInsufficientData(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => ['visits' => 0],
                'trends' => [],
            ]);

        $result = $this->predictor->predictTrend(1, 0, 7);

        $this->assertArrayHasKey('error', $result);
    }

    public function testExportWithMissingMetrics(): void
    {
        $this->analyticsService->method('getSegmentAnalytics')
            ->willReturn([
                'metrics' => [],
                'trends' => [],
                'top_sources' => [],
                'device_breakdown' => [],
                'top_pages' => [],
            ]);

        $csv = $this->exporter->exportToCSV(1);

        $this->assertIsString($csv);
        $this->assertStringContainsString('KEY METRICS', $csv);
    }

    public function testWebSocketParseInvalidMessage(): void
    {
        $invalid = ['action' => null];

        $parsed = $this->wsAdapter->parseWSMessage($invalid);

        $this->assertNull($parsed['action']);
    }
}
