<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\SegmentRepository;

/**
 * SB-021.3: SegmentAnalytics
 * 
 * Analytics and insights for segment usage and performance
 */
class SegmentAnalytics
{
    private SegmentRepository $repository;

    public function __construct(SegmentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get top used segments
     */
    public function getTopSegments(int $limit = 10, ?int $days = 30): array
    {
        return $this->repository->getTopSegmentsInPeriod($limit, $days);
    }

    /**
     * Get segment usage trend
     */
    public function getSegmentTrend(int $segmentId, int $days = 30): array
    {
        $trend = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = $this->repository->getSegmentUsageForDate($segmentId, $date);
            $trend[$date] = $count;
        }
        return $trend;
    }

    /**
     * Get most shared segments
     */
    public function getMostSharedSegments(int $limit = 10): array
    {
        return $this->repository->getMostSharedSegments($limit);
    }

    /**
     * Get segment performance metrics
     */
    public function getSegmentMetrics(int $segmentId): array
    {
        return [
            'uses' => $this->repository->countUsages($segmentId),
            'last_used' => $this->repository->getLastUsed($segmentId),
            'shares' => $this->repository->countShares($segmentId),
            'trending' => $this->isTrending($segmentId),
            'efficiency' => $this->calculateEfficiency($segmentId),
        ];
    }

    /**
     * Check if segment is trending
     */
    private function isTrending(int $segmentId, int $days = 7): bool
    {
        $current = 0;
        $previous = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $current += $this->repository->getSegmentUsageForDate($segmentId, $date);
        }

        for ($i = $days; $i < $days * 2; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $previous += $this->repository->getSegmentUsageForDate($segmentId, $date);
        }

        return $current > $previous * 1.2; // 20% increase
    }

    /**
     * Calculate segment efficiency (usage / complexity)
     */
    private function calculateEfficiency(int $segmentId): float
    {
        $segment = $this->repository->getById($segmentId);
        if (!$segment) return 0;

        $ruleCount = count($segment['rules'] ?? []);
        $uses = $this->repository->countUsages($segmentId);

        if ($ruleCount === 0) return 0;

        return round($uses / $ruleCount, 2);
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(): array
    {
        return [
            'total_segments' => $this->repository->countAllSegments(),
            'top_segments' => $this->getTopSegments(5),
            'most_shared' => $this->getMostSharedSegments(5),
            'trending' => $this->getTrendingSegments(5),
            'new_segments' => $this->getRecentSegments(5),
        ];
    }

    /**
     * Get trending segments
     */
    private function getTrendingSegments(int $limit = 5): array
    {
        // Placeholder - would query usage trends
        return [];
    }

    /**
     * Get recent segments
     */
    private function getRecentSegments(int $limit = 5): array
    {
        return $this->repository->getRecentSegments($limit);
    }
}
