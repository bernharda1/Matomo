<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\SegmentRepository;
use Piwik\Common;
use Piwik\Db;

/**
 * SB-022.1: Advanced Segment Analytics Service
 * 
 * Comprehensive analytics engine for segment performance analysis,
 * drill-down capabilities, and comparative analytics.
 */
class SegmentAnalyticsService
{
    private SegmentRepository $repository;

    public function __construct(SegmentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get comprehensive segment analytics
     */
    public function getSegmentAnalytics(int $segmentId, string $period = 'month', ?int $days = null): array
    {
        if ($days === null) {
            $days = $this->getPeriodDays($period);
        }

        return [
            'segment' => $this->repository->getById($segmentId),
            'metrics' => $this->getSegmentMetrics($segmentId, $days),
            'trends' => $this->getSegmentTrends($segmentId, $days),
            'drill_down' => $this->getDrillDownData($segmentId, $days),
            'top_sources' => $this->getTopTrafficSources($segmentId, $days),
            'device_breakdown' => $this->getDeviceBreakdown($segmentId, $days),
            'browser_breakdown' => $this->getBrowserBreakdown($segmentId, $days),
            'geo_breakdown' => $this->getGeoBreakdown($segmentId, $days),
            'top_pages' => $this->getTopPages($segmentId, $days),
            'conversions' => $this->getConversionMetrics($segmentId, $days),
        ];
    }

    /**
     * Get key metrics for segment
     */
    private function getSegmentMetrics(int $segmentId, int $days): array
    {
        $segment = $this->repository->getById($segmentId);
        if (!$segment) return [];

        return [
            'visits' => $this->countVisits($segmentId, $days),
            'visitors' => $this->countUniqueVisitors($segmentId, $days),
            'actions' => $this->countActions($segmentId, $days),
            'bounce_rate' => $this->calculateBounceRate($segmentId, $days),
            'avg_session_duration' => $this->averageSessionDuration($segmentId, $days),
            'conversion_rate' => $this->getConversionRate($segmentId, $days),
            'avg_actions_per_visit' => $this->averageActionsPerVisit($segmentId, $days),
            'returning_rate' => $this->returningVisitorRate($segmentId, $days),
        ];
    }

    /**
     * Get trend data
     */
    private function getSegmentTrends(int $segmentId, int $days): array
    {
        $trends = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $trends[$date] = [
                'visits' => $this->countVisitsForDate($segmentId, $date),
                'visitors' => $this->countVisitorsForDate($segmentId, $date),
                'bounce_rate' => $this->bounceRateForDate($segmentId, $date),
            ];
        }
        return $trends;
    }

    /**
     * Get drill-down data by dimension
     */
    private function getDrillDownData(int $segmentId, int $days): array
    {
        return [
            'by_traffic_source' => $this->getTrafficSourceDrill($segmentId, $days),
            'by_device_type' => $this->getDeviceDrill($segmentId, $days),
            'by_browser' => $this->getBrowserDrill($segmentId, $days),
            'by_country' => $this->getCountryDrill($segmentId, $days),
            'by_referrer' => $this->getReferrerDrill($segmentId, $days),
        ];
    }

    /**
     * Get top traffic sources for segment
     */
    private function getTopTrafficSources(int $segmentId, int $days): array
    {
        $query = "
            SELECT 
                traffic_source,
                COUNT(*) as visits,
                COUNT(DISTINCT visitor_id) as visitors,
                SUM(bounce) / COUNT(*) as bounce_rate
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
            GROUP BY traffic_source
            ORDER BY visits DESC
            LIMIT 10
        ";

        return (array) Db::query($query, [$days, $segmentId]);
    }

    /**
     * Get device breakdown
     */
    private function getDeviceBreakdown(int $segmentId, int $days): array
    {
        $query = "
            SELECT 
                device_type,
                COUNT(*) as visits,
                COUNT(DISTINCT visitor_id) as visitors,
                AVG(visit_duration) as avg_duration,
                SUM(bounce) / COUNT(*) as bounce_rate
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
            GROUP BY device_type
            ORDER BY visits DESC
        ";

        return (array) Db::query($query, [$days, $segmentId]);
    }

    /**
     * Get browser breakdown
     */
    private function getBrowserBreakdown(int $segmentId, int $days): array
    {
        $query = "
            SELECT 
                browser_name,
                COUNT(*) as visits,
                COUNT(DISTINCT visitor_id) as visitors,
                AVG(visit_duration) as avg_duration
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
            GROUP BY browser_name
            ORDER BY visits DESC
            LIMIT 10
        ";

        return (array) Db::query($query, [$days, $segmentId]);
    }

    /**
     * Get geographic breakdown
     */
    private function getGeoBreakdown(int $segmentId, int $days): array
    {
        $query = "
            SELECT 
                country_code,
                COUNT(*) as visits,
                COUNT(DISTINCT visitor_id) as visitors,
                SUM(bounce) / COUNT(*) as bounce_rate
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
            GROUP BY country_code
            ORDER BY visits DESC
            LIMIT 10
        ";

        return (array) Db::query($query, [$days, $segmentId]);
    }

    /**
     * Get top pages/actions
     */
    private function getTopPages(int $segmentId, int $days): array
    {
        $query = "
            SELECT 
                page_name,
                COUNT(*) as views,
                AVG(time_on_page) as avg_time,
                COUNT(DISTINCT visit_id) as unique_visits
            FROM " . Common::prefixTable('log_action') . " la
            JOIN " . Common::prefixTable('log_visit') . " lv ON la.visit_id = lv.visit_id
            WHERE DATE(lv.visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND lv.segment_id = %d
            AND la.type = 1
            GROUP BY la.page_name
            ORDER BY views DESC
            LIMIT 10
        ";

        return (array) Db::query($query, [$days, $segmentId]);
    }

    /**
     * Get conversion metrics
     */
    private function getConversionMetrics(int $segmentId, int $days): array
    {
        $query = "
            SELECT 
                goal_id,
                COUNT(*) as conversions,
                SUM(revenue) as revenue,
                COUNT(DISTINCT visitor_id) as unique_converters
            FROM " . Common::prefixTable('log_conversion') . " lc
            JOIN " . Common::prefixTable('log_visit') . " lv ON lc.visit_id = lv.visit_id
            WHERE DATE(lv.visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND lv.segment_id = %d
            GROUP BY goal_id
            ORDER BY conversions DESC
        ";

        return (array) Db::query($query, [$days, $segmentId]);
    }

    /**
     * Compare multiple segments
     */
    public function compareSegments(array $segmentIds, int $days = 30): array
    {
        $comparison = [];
        foreach ($segmentIds as $segmentId) {
            $metrics = $this->getSegmentMetrics($segmentId, $days);
            $comparison[$segmentId] = [
                'segment' => $this->repository->getById($segmentId),
                'metrics' => $metrics,
            ];
        }
        return $comparison;
    }

    /**
     * Export segment analytics to array
     */
    public function exportAnalytics(int $segmentId, string $format = 'csv', int $days = 30): array
    {
        $analytics = $this->getSegmentAnalytics($segmentId, 'month', $days);
        
        return [
            'format' => $format,
            'segment_id' => $segmentId,
            'period_days' => $days,
            'generated_at' => date('Y-m-d H:i:s'),
            'data' => $analytics,
        ];
    }

    /**
     * Helper: Count visits for segment in period
     */
    private function countVisits(int $segmentId, int $days): int
    {
        $query = "
            SELECT COUNT(*) as count FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Helper: Count unique visitors
     */
    private function countUniqueVisitors(int $segmentId, int $days): int
    {
        $query = "
            SELECT COUNT(DISTINCT visitor_id) as count FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Helper: Count actions
     */
    private function countActions(int $segmentId, int $days): int
    {
        $query = "
            SELECT COUNT(*) as count FROM " . Common::prefixTable('log_action') . " la
            JOIN " . Common::prefixTable('log_visit') . " lv ON la.visit_id = lv.visit_id
            WHERE DATE(lv.visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND lv.segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Helper: Calculate bounce rate
     */
    private function calculateBounceRate(int $segmentId, int $days): float
    {
        $query = "
            SELECT 
                SUM(CASE WHEN nb_actions = 1 THEN 1 ELSE 0 END) as bounces,
                COUNT(*) as total
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        $data = $result[0] ?? ['bounces' => 0, 'total' => 0];
        return $data['total'] > 0 ? round(($data['bounces'] / $data['total']) * 100, 2) : 0;
    }

    /**
     * Helper: Average session duration
     */
    private function averageSessionDuration(int $segmentId, int $days): float
    {
        $query = "
            SELECT AVG(visit_duration) as avg_duration
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        return (float) ($result[0]['avg_duration'] ?? 0);
    }

    /**
     * Helper: Get conversion rate
     */
    private function getConversionRate(int $segmentId, int $days): float
    {
        $visits = $this->countVisits($segmentId, $days);
        if ($visits === 0) return 0;

        $query = "
            SELECT COUNT(DISTINCT visitor_id) as converters
            FROM " . Common::prefixTable('log_conversion') . " lc
            JOIN " . Common::prefixTable('log_visit') . " lv ON lc.visit_id = lv.visit_id
            WHERE DATE(lv.visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND lv.segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        $converters = (int) ($result[0]['converters'] ?? 0);

        return $visits > 0 ? round(($converters / $visits) * 100, 2) : 0;
    }

    /**
     * Helper: Average actions per visit
     */
    private function averageActionsPerVisit(int $segmentId, int $days): float
    {
        $query = "
            SELECT AVG(nb_actions) as avg_actions
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        return (float) ($result[0]['avg_actions'] ?? 0);
    }

    /**
     * Helper: Returning visitor rate
     */
    private function returningVisitorRate(int $segmentId, int $days): float
    {
        $query = "
            SELECT 
                SUM(CASE WHEN visit_count > 1 THEN 1 ELSE 0 END) as returning,
                COUNT(*) as total
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
        ";
        $result = Db::query($query, [$days, $segmentId]);
        $data = $result[0] ?? ['returning' => 0, 'total' => 0];
        return $data['total'] > 0 ? round(($data['returning'] / $data['total']) * 100, 2) : 0;
    }

    /**
     * Helper: Count visits for specific date
     */
    private function countVisitsForDate(int $segmentId, string $date): int
    {
        $query = "
            SELECT COUNT(*) as count FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) = %s
            AND segment_id = %d
        ";
        $result = Db::query($query, [$date, $segmentId]);
        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Helper: Count visitors for specific date
     */
    private function countVisitorsForDate(int $segmentId, string $date): int
    {
        $query = "
            SELECT COUNT(DISTINCT visitor_id) as count FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) = %s
            AND segment_id = %d
        ";
        $result = Db::query($query, [$date, $segmentId]);
        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Helper: Bounce rate for specific date
     */
    private function bounceRateForDate(int $segmentId, string $date): float
    {
        $query = "
            SELECT 
                SUM(CASE WHEN nb_actions = 1 THEN 1 ELSE 0 END) as bounces,
                COUNT(*) as total
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) = %s
            AND segment_id = %d
        ";
        $result = Db::query($query, [$date, $segmentId]);
        $data = $result[0] ?? ['bounces' => 0, 'total' => 0];
        return $data['total'] > 0 ? round(($data['bounces'] / $data['total']) * 100, 2) : 0;
    }

    /**
     * Helper: Traffic source drill-down
     */
    private function getTrafficSourceDrill(int $segmentId, int $days): array
    {
        return $this->getTopTrafficSources($segmentId, $days);
    }

    /**
     * Helper: Device drill-down
     */
    private function getDeviceDrill(int $segmentId, int $days): array
    {
        return $this->getDeviceBreakdown($segmentId, $days);
    }

    /**
     * Helper: Browser drill-down
     */
    private function getBrowserDrill(int $segmentId, int $days): array
    {
        return $this->getBrowserBreakdown($segmentId, $days);
    }

    /**
     * Helper: Country drill-down
     */
    private function getCountryDrill(int $segmentId, int $days): array
    {
        return $this->getGeoBreakdown($segmentId, $days);
    }

    /**
     * Helper: Referrer drill-down
     */
    private function getReferrerDrill(int $segmentId, int $days): array
    {
        $query = "
            SELECT 
                referrer_name,
                COUNT(*) as visits,
                COUNT(DISTINCT visitor_id) as visitors
            FROM " . Common::prefixTable('log_visit') . "
            WHERE DATE(visit_first_action_time) >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND segment_id = %d
            GROUP BY referrer_name
            ORDER BY visits DESC
            LIMIT 10
        ";

        return (array) Db::query($query, [$days, $segmentId]);
    }

    /**
     * Helper: Get period days
     */
    private function getPeriodDays(string $period): int
    {
        return match($period) {
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'year' => 365,
            default => 30,
        };
    }
}
