<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

/**
 * SB-022.3: Advanced Analytics - Segment Predictor
 * 
 * Forecasts segment trends and predicts future performance
 */
class SegmentPredictor
{
    private SegmentAnalyticsService $analyticsService;

    public function __construct(SegmentAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Predict segment trend for next N days
     */
    public function predictTrend(int $segmentId, int $historicalDays = 30, int $forecastDays = 7): array
    {
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $historicalDays);
        $trends = $analytics['trends'] ?? [];

        if (empty($trends)) {
            return ['error' => 'Insufficient data for prediction'];
        }

        $visits = array_values(array_map(fn($t) => $t['visits'] ?? 0, $trends));
        
        // Use exponential smoothing for forecast
        $forecast = $this->exponentialSmoothing($visits, $forecastDays);

        return [
            'segment_id' => $segmentId,
            'historical_days' => $historicalDays,
            'forecast_days' => $forecastDays,
            'forecast' => $forecast,
            'trend_direction' => $this->getTrendDirection($visits),
            'confidence' => $this->calculateConfidence($visits),
            'recommendation' => $this->getRecommendation($visits, $forecast),
        ];
    }

    /**
     * Exponential smoothing forecast
     */
    private function exponentialSmoothing(array $data, int $periods, float $alpha = 0.3): array
    {
        if (empty($data)) return [];

        $forecast = [];
        $s = $data[0];

        for ($i = 1; $i < count($data); $i++) {
            $s = $alpha * $data[$i] + (1 - $alpha) * $s;
        }

        for ($i = 0; $i < $periods; $i++) {
            $forecast[] = round($s, 0);
        }

        return $forecast;
    }

    /**
     * Determine trend direction
     */
    private function getTrendDirection(array $data): array
    {
        if (count($data) < 2) {
            return ['direction' => 'unknown', 'strength' => 0];
        }

        $first_third = array_slice($data, 0, ceil(count($data) / 3));
        $last_third = array_slice($data, floor(count($data) * 2 / 3));

        $first_avg = array_sum($first_third) / count($first_third);
        $last_avg = array_sum($last_third) / count($last_third);

        $change_percent = ($last_avg - $first_avg) / ($first_avg ?: 1) * 100;

        if ($change_percent > 5) {
            return [
                'direction' => 'upward',
                'strength' => min(100, abs($change_percent)),
                'change_percent' => round($change_percent, 1),
            ];
        } elseif ($change_percent < -5) {
            return [
                'direction' => 'downward',
                'strength' => min(100, abs($change_percent)),
                'change_percent' => round($change_percent, 1),
            ];
        }

        return [
            'direction' => 'stable',
            'strength' => 0,
            'change_percent' => round($change_percent, 1),
        ];
    }

    /**
     * Calculate forecast confidence
     */
    private function calculateConfidence(array $data): float
    {
        if (count($data) < 3) return 0.3;

        $variance = $this->calculateVariance($data);
        $mean = array_sum($data) / count($data);

        if ($mean === 0) return 0;

        $cv = sqrt($variance) / $mean; // Coefficient of variation

        // Lower CV = higher confidence
        $confidence = max(0.2, min(0.95, 1 - $cv));

        return round($confidence, 2);
    }

    /**
     * Calculate variance
     */
    private function calculateVariance(array $data): float
    {
        $mean = array_sum($data) / count($data);
        $variance = array_reduce($data, fn($carry, $x) => $carry + pow($x - $mean, 2), 0) / count($data);
        return $variance;
    }

    /**
     * Get recommendation based on trend
     */
    private function getRecommendation(array $data, array $forecast): string
    {
        $lastValue = end($data);
        $forecastAvg = array_sum($forecast) / count($forecast);

        $change_percent = ($forecastAvg - $lastValue) / ($lastValue ?: 1) * 100;

        if ($change_percent > 15) {
            return 'Strong growth expected. Consider optimizing for scale.';
        } elseif ($change_percent > 5) {
            return 'Modest growth expected. Monitor performance.';
        } elseif ($change_percent > -5) {
            return 'Stable performance expected. No immediate action needed.';
        } elseif ($change_percent > -15) {
            return 'Slight decline expected. Review segment targeting.';
        }

        return 'Significant decline expected. Consider segment optimization.';
    }

    /**
     * Predict optimal timing for campaigns
     */
    public function predictOptimalTiming(int $segmentId, int $days = 30): array
    {
        $analytics = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', $days);
        $trends = $analytics['trends'] ?? [];

        if (empty($trends)) {
            return ['error' => 'Insufficient data'];
        }

        $visits = array_map(fn($t) => $t['visits'] ?? 0, $trends);
        $maxVisits = max($visits);
        $maxDate = array_key_first(array_filter($visits, fn($v) => $v === $maxVisits));

        $avgVisits = array_sum($visits) / count($visits);
        $aboveAverage = array_filter($visits, fn($v) => $v > $avgVisits * 1.2);

        return [
            'segment_id' => $segmentId,
            'peak_traffic_date' => array_keys($trends)[$maxDate] ?? null,
            'peak_traffic_value' => $maxVisits,
            'above_average_days' => count($aboveAverage),
            'recommendation' => count($aboveAverage) > 0 
                ? 'Schedule campaigns on identified peak days for maximum reach.'
                : 'Traffic is stable. Any day is suitable for campaigns.',
        ];
    }

    /**
     * Growth prediction for next quarter
     */
    public function predictQuarterlyGrowth(int $segmentId): array
    {
        // Compare last 30 days vs previous 30 days
        $recent = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', 30);
        $previous = $this->analyticsService->getSegmentAnalytics($segmentId, 'month', 60);

        $recentVisits = $recent['metrics']['visits'] ?? 0;
        $previousVisits = $previous['metrics']['visits'] ?? 0;

        $growthRate = $previousVisits > 0 
            ? (($recentVisits - $previousVisits) / $previousVisits) * 100 
            : 0;

        // Project to quarter
        $quarterlyProjection = $recentVisits * (1 + ($growthRate / 100)) * 3;

        return [
            'segment_id' => $segmentId,
            'current_monthly_visits' => $recentVisits,
            'growth_rate_percent' => round($growthRate, 1),
            'quarterly_projection' => round($quarterlyProjection, 0),
            'trend' => match (true) {
                $growthRate > 20 => 'high_growth',
                $growthRate > 5 => 'moderate_growth',
                $growthRate > -5 => 'stable',
                $growthRate > -20 => 'moderate_decline',
                default => 'high_decline',
            },
        ];
    }
}
