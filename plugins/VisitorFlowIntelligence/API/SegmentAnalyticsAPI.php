<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\API;

use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentAnalyticsService;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\SegmentRepository;
use Piwik\Piwik;
use Piwik\Common;

/**
 * SB-022.1: Segment Analytics API
 * 
 * REST API endpoints for advanced segment analytics
 */
class SegmentAnalyticsAPI
{
    private SegmentAnalyticsService $analyticsService;
    private SegmentRepository $repository;

    public function __construct(SegmentAnalyticsService $analyticsService, SegmentRepository $repository)
    {
        $this->analyticsService = $analyticsService;
        $this->repository = $repository;
    }

    /**
     * Get comprehensive analytics for a segment
     * 
     * @param int $segmentId
     * @param string $period month|week|quarter|year
     * @param int|null $days Override period with specific number of days
     * @return array
     */
    public function getSegmentAnalytics($segmentId, $period = 'month', $days = null)
    {
        $segmentId = (int) $segmentId;
        Piwik::checkUserHasViewAccess($segmentId);

        return $this->analyticsService->getSegmentAnalytics($segmentId, $period, $days);
    }

    /**
     * Get segment metrics
     * 
     * @param int $segmentId
     * @param int $days
     * @return array
     */
    public function getSegmentMetrics($segmentId, $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        // Extract metrics from comprehensive analytics
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        return $analytics['metrics'] ?? [];
    }

    /**
     * Get segment trends
     * 
     * @param int $segmentId
     * @param int $days
     * @return array
     */
    public function getSegmentTrends($segmentId, $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        return $analytics['trends'] ?? [];
    }

    /**
     * Get drill-down data
     * 
     * @param int $segmentId
     * @param string $dimension traffic_source|device|browser|country|referrer
     * @param int $days
     * @return array
     */
    public function getDrillDown($segmentId, $dimension = 'traffic_source', $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        $drillDown = $analytics['drill_down'] ?? [];

        $dimensionMap = [
            'traffic_source' => 'by_traffic_source',
            'device' => 'by_device_type',
            'browser' => 'by_browser',
            'country' => 'by_country',
            'referrer' => 'by_referrer',
        ];

        $key = $dimensionMap[$dimension] ?? $dimension;
        return $drillDown[$key] ?? [];
    }

    /**
     * Get device breakdown
     * 
     * @param int $segmentId
     * @param int $days
     * @return array
     */
    public function getDeviceBreakdown($segmentId, $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        return $analytics['device_breakdown'] ?? [];
    }

    /**
     * Get browser breakdown
     * 
     * @param int $segmentId
     * @param int $days
     * @return array
     */
    public function getBrowserBreakdown($segmentId, $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        return $analytics['browser_breakdown'] ?? [];
    }

    /**
     * Get geographic breakdown
     * 
     * @param int $segmentId
     * @param int $days
     * @return array
     */
    public function getGeoBreakdown($segmentId, $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        return $analytics['geo_breakdown'] ?? [];
    }

    /**
     * Get top pages
     * 
     * @param int $segmentId
     * @param int $days
     * @return array
     */
    public function getTopPages($segmentId, $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        return $analytics['top_pages'] ?? [];
    }

    /**
     * Get conversion metrics
     * 
     * @param int $segmentId
     * @param int $days
     * @return array
     */
    public function getConversionMetrics($segmentId, $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        return $analytics['conversions'] ?? [];
    }

    /**
     * Compare multiple segments
     * 
     * @param string $segmentIds Comma-separated segment IDs
     * @param int $days
     * @return array
     */
    public function compareSegments($segmentIds, $days = 30)
    {
        $ids = array_map('intval', explode(',', $segmentIds));
        $days = (int) $days;

        foreach ($ids as $id) {
            Piwik::checkUserHasViewAccess($id);
        }

        return $this->analyticsService->compareSegments($ids, $days);
    }

    /**
     * Export segment analytics
     * 
     * @param int $segmentId
     * @param string $format csv|json
     * @param int $days
     * @return array
     */
    public function exportAnalytics($segmentId, $format = 'csv', $days = 30)
    {
        $segmentId = (int) $segmentId;
        $days = (int) $days;
        Piwik::checkUserHasViewAccess($segmentId);

        // Validate format
        if (!in_array($format, ['csv', 'json'])) {
            throw new \Exception('Invalid export format. Supported: csv, json');
        }

        return $this->analyticsService->exportAnalytics($segmentId, $format, $days);
    }

    /**
     * Get top segments by usage
     * 
     * @param int $days
     * @param int $limit
     * @return array
     */
    public function getTopSegments($days = 30, $limit = 10)
    {
        $days = (int) $days;
        $limit = (int) Common::getRequestVar('limit', $limit, 'int');
        Piwik::checkUserHasAdminAccess();

        return $this->repository->getTopSegmentsInPeriod($limit, $days);
    }

    /**
     * Get trending segments
     * 
     * @param int $days
     * @param int $limit
     * @return array
     */
    public function getTrendingSegments($days = 7, $limit = 10)
    {
        $days = (int) $days;
        $limit = (int) $limit;
        Piwik::checkUserHasAdminAccess();

        $topSegments = $this->repository->getTopSegmentsInPeriod($limit * 3, $days);
        
        $trending = [];
        foreach ($topSegments as $segment) {
            $trendScore = $this->calculateTrendScore($segment['id'], $days);
            if ($trendScore > 1.2) { // 20% growth
                $trending[] = array_merge($segment, ['trend_score' => $trendScore]);
            }
        }

        usort($trending, fn($a, $b) => $b['trend_score'] <=> $a['trend_score']);
        return array_slice($trending, 0, $limit);
    }

    /**
     * Calculate trend score
     */
    private function calculateTrendScore(int $segmentId, int $days): float
    {
        $currentMetrics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        $previousMetrics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days * 2);

        $currentVisits = $currentMetrics['metrics']['visits'] ?? 0;
        $previousVisits = $previousMetrics['metrics']['visits'] ?? 0;

        if ($previousVisits === 0) return 1;
        return $currentVisits / $previousVisits;
    }
}
