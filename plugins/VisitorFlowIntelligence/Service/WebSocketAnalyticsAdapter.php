<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

/**
 * SB-022.3: Real-time Integration - WebSocket Analytics Adapter
 * 
 * Streams analytics updates via WebSocket from SB-020 integration
 */
class WebSocketAnalyticsAdapter
{
    private SegmentAnalyticsService $analyticsService;
    private string $wsUrl;

    public function __construct(SegmentAnalyticsService $analyticsService, string $wsUrl = 'ws://localhost:8080/analytics')
    {
        $this->analyticsService = $analyticsService;
        $this->wsUrl = $wsUrl;
    }

    /**
     * Stream analytics updates
     */
    public function streamAnalytics(int $segmentId, callable $callback, int $interval = 10): void
    {
        $lastUpdate = 0;
        
        while (true) {
            $current = time();
            
            if (($current - $lastUpdate) >= $interval) {
                $analytics = $this->getCompactAnalytics($segmentId);
                $callback($analytics);
                $lastUpdate = $current;
            }
            
            usleep(1000000); // Sleep 1 second before next check
        }
    }

    /**
     * Get compact analytics for WebSocket transmission
     */
    public function getCompactAnalytics(int $segmentId): array
    {
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', 30);
        
        // Get latest day data
        $latestTrend = end($analytics['trends']);

        return [
            'segment_id' => $segmentId,
            'timestamp' => time(),
            'metrics' => [
                'v' => $analytics['metrics']['visits'] ?? 0,           // visits
                'vs' => $analytics['metrics']['visitors'] ?? 0,        // visitors
                'b' => $analytics['metrics']['bounce_rate'] ?? 0,      // bounce_rate
                'c' => $analytics['metrics']['conversion_rate'] ?? 0,  // conversion_rate
                'd' => $analytics['metrics']['avg_session_duration'] ?? 0, // duration
            ],
            'latest' => [
                'v' => $latestTrend['visits'] ?? 0,
                'b' => $latestTrend['bounce_rate'] ?? 0,
                'ts' => key($analytics['trends']),
            ],
            'top_source' => $analytics['top_sources'][0] ?? null,
        ];
    }

    /**
     * Subscribe to segment analytics updates
     */
    public function subscribeToUpdates(int $segmentId, string $clientId, int $updateInterval = 10): array
    {
        return [
            'action' => 'subscribe_analytics',
            'segment_id' => $segmentId,
            'client_id' => $clientId,
            'update_interval' => $updateInterval,
            'ws_url' => $this->wsUrl,
        ];
    }

    /**
     * Unsubscribe from updates
     */
    public function unsubscribeFromUpdates(int $segmentId, string $clientId): array
    {
        return [
            'action' => 'unsubscribe_analytics',
            'segment_id' => $segmentId,
            'client_id' => $clientId,
        ];
    }

    /**
     * Get delta (changes only) since last update
     */
    public function getDelta(int $segmentId, array $previousMetrics): array
    {
        $current = $this->getCompactAnalytics($segmentId);
        
        $delta = [
            'segment_id' => $segmentId,
            'timestamp' => $current['timestamp'],
            'changes' => [],
        ];

        // Compare metrics
        foreach ($current['metrics'] as $key => $value) {
            if (!isset($previousMetrics[$key]) || $previousMetrics[$key] !== $value) {
                $delta['changes'][$key] = $value;
            }
        }

        return $delta;
    }

    /**
     * Format analytics for frontend display
     */
    public function formatForDisplay(array $analytics): array
    {
        return [
            'id' => $analytics['segment_id'],
            'ts' => date('H:i:s', $analytics['timestamp']),
            'visits' => $this->formatNumber($analytics['metrics']['v']),
            'visitors' => $this->formatNumber($analytics['metrics']['vs']),
            'bounce_rate' => round($analytics['metrics']['b'], 1) . '%',
            'conversion_rate' => round($analytics['metrics']['c'], 1) . '%',
            'avg_duration' => $this->formatDuration($analytics['metrics']['d']),
            'latest_visits_today' => $this->formatNumber($analytics['latest']['v']),
            'source' => $analytics['top_source']['traffic_source'] ?? 'N/A',
        ];
    }

    /**
     * Format number for display
     */
    private function formatNumber(int $num): string
    {
        if ($num < 1000) return (string)$num;
        if ($num < 1000000) return round($num / 1000, 1) . 'K';
        return round($num / 1000000, 1) . 'M';
    }

    /**
     * Format duration for display
     */
    private function formatDuration(float $seconds): string
    {
        $secs = (int)$seconds;
        $mins = floor($secs / 60);
        $secs = $secs % 60;
        return $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";
    }

    /**
     * Create WebSocket message
     */
    public function createWSMessage(string $action, array $payload): array
    {
        return [
            'action' => $action,
            'payload' => $payload,
            'timestamp' => time(),
        ];
    }

    /**
     * Parse WebSocket message
     */
    public function parseWSMessage(array $message): array
    {
        return [
            'action' => $message['action'] ?? null,
            'segment_id' => $message['segment_id'] ?? null,
            'client_id' => $message['client_id'] ?? null,
            'data' => $message['data'] ?? [],
        ];
    }

    /**
     * Check connection health
     */
    public function checkConnectionHealth(): array
    {
        $isHealthy = true;
        
        // Would check actual WebSocket server health
        // For now, return stub
        
        return [
            'ws_url' => $this->wsUrl,
            'is_healthy' => $isHealthy,
            'last_heartbeat' => time(),
            'subscriptions_active' => 0,
        ];
    }
}
