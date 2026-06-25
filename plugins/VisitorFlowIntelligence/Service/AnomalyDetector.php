<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\SegmentRepository;

/**
 * SB-022.3: Advanced Analytics - Anomaly Detection
 * 
 * Detects unusual patterns in segment analytics
 */
class AnomalyDetector
{
    private SegmentAnalyticsService $analyticsService;
    private SegmentRepository $repository;

    public function __construct(SegmentAnalyticsService $analyticsService, SegmentRepository $repository)
    {
        $this->analyticsService = $analyticsService;
        $this->repository = $repository;
    }

    /**
     * Detect anomalies in segment metrics
     */
    public function detectAnomalies(int $segmentId, int $days = 30): array
    {
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        $trends = $analytics['trends'];
        
        $anomalies = [];
        
        if (empty($trends)) return $anomalies;

        $visits = array_map(fn($t) => $t['visits'] ?? 0, $trends);
        $bounceRates = array_map(fn($t) => $t['bounce_rate'] ?? 0, $trends);

        // Detect visit spike/drop
        $visitAnomalies = $this->detectSpikes($visits, 'visits');
        if (!empty($visitAnomalies)) {
            $anomalies['visit_anomalies'] = $visitAnomalies;
        }

        // Detect bounce rate anomalies
        $bounceAnomalies = $this->detectSpikes($bounceRates, 'bounce_rate');
        if (!empty($bounceAnomalies)) {
            $anomalies['bounce_rate_anomalies'] = $bounceAnomalies;
        }

        // Detect trend reversal
        $reversals = $this->detectTrendReversal($visits);
        if (!empty($reversals)) {
            $anomalies['trend_reversals'] = $reversals;
        }

        return [
            'segment_id' => $segmentId,
            'period_days' => $days,
            'has_anomalies' => !empty($anomalies),
            'anomalies' => $anomalies,
            'severity' => $this->calculateSeverity($anomalies),
        ];
    }

    /**
     * Detect spikes or drops in data
     */
    private function detectSpikes(array $data, string $metric): array
    {
        $anomalies = [];
        
        if (count($data) < 3) return $anomalies;

        $mean = array_sum($data) / count($data);
        $variance = array_reduce($data, fn($carry, $x) => $carry + pow($x - $mean, 2), 0) / count($data);
        $stdDev = sqrt($variance);

        if ($stdDev === 0) return $anomalies;

        foreach ($data as $i => $value) {
            $zScore = abs(($value - $mean) / $stdDev);
            
            if ($zScore > 2.5) { // 2.5 standard deviations = ~1.2% probability
                $anomalies[] = [
                    'day_index' => $i,
                    'value' => $value,
                    'expected' => round($mean, 2),
                    'z_score' => round($zScore, 2),
                    'severity' => $zScore > 3.5 ? 'critical' : 'warning',
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Detect trend reversals (momentum changes)
     */
    private function detectTrendReversal(array $data): array
    {
        $reversals = [];
        
        if (count($data) < 5) return $reversals;

        // Calculate momentum (rate of change)
        $momentum = [];
        for ($i = 1; $i < count($data); $i++) {
            $momentum[] = $data[$i] - $data[$i - 1];
        }

        // Check for sign changes (reversals)
        for ($i = 1; $i < count($momentum); $i++) {
            if (($momentum[$i] > 0 && $momentum[$i - 1] < 0) ||
                ($momentum[$i] < 0 && $momentum[$i - 1] > 0)) {
                $reversals[] = [
                    'day_index' => $i + 1,
                    'previous_momentum' => round($momentum[$i - 1], 2),
                    'current_momentum' => round($momentum[$i], 2),
                    'change_magnitude' => round(abs($momentum[$i] - $momentum[$i - 1]), 2),
                ];
            }
        }

        return $reversals;
    }

    /**
     * Calculate overall anomaly severity
     */
    private function calculateSeverity(array $anomalies): string
    {
        $criticalCount = 0;
        
        foreach ($anomalies as $category) {
            if (is_array($category)) {
                foreach ($category as $anomaly) {
                    if (is_array($anomaly) && ($anomaly['severity'] ?? null) === 'critical') {
                        $criticalCount++;
                    }
                }
            }
        }

        if ($criticalCount > 2) return 'critical';
        if ($criticalCount > 0) return 'high';
        return 'low';
    }

    /**
     * Get anomaly insights for display
     */
    public function getInsights(int $segmentId, int $days = 30): array
    {
        $anomalies = $this->detectAnomalies($segmentId, $days);
        $insights = [];

        if (!$anomalies['has_anomalies']) {
            $insights[] = [
                'type' => 'positive',
                'message' => 'Segment showing stable, predictable behavior.',
            ];
            return $insights;
        }

        // Visit anomalies
        if (!empty($anomalies['anomalies']['visit_anomalies'])) {
            $visitAnomalies = $anomalies['anomalies']['visit_anomalies'];
            $count = count($visitAnomalies);
            $insights[] = [
                'type' => 'warning',
                'message' => "Detected $count unusual traffic spike(s) or drop(s).",
                'details' => $visitAnomalies,
            ];
        }

        // Bounce rate anomalies
        if (!empty($anomalies['anomalies']['bounce_rate_anomalies'])) {
            $insights[] = [
                'type' => 'warning',
                'message' => 'Bounce rate showing unusual patterns. May indicate content quality changes.',
            ];
        }

        // Trend reversals
        if (!empty($anomalies['anomalies']['trend_reversals'])) {
            $count = count($anomalies['anomalies']['trend_reversals']);
            $insights[] = [
                'type' => 'info',
                'message' => "Momentum shifted $count time(s) during the period.",
            ];
        }

        return $insights;
    }
}
