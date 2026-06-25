<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Infrastructure;

use Piwik\Db;
use Piwik\Common;

/**
 * SB-014.1: GeoPrecision Archiver
 * 
 * Aggregates geographic precision data:
 * - Confidence score distribution
 * - Precision level breakdown
 * - Geographic coverage by country/region/city
 */
class GeoArchiver extends BaseArchiver
{
    protected string $pluginName = 'GeoPrecision';

    public function aggregate(): void
    {
        if ($this->isArchiveAlreadyDone()) {
            $this->log("Archive already exists for {$this->date}, skipping");
            return;
        }

        try {
            $dateRange = $this->getDateRange();
            
            $this->aggregateConfidenceDistribution($dateRange);
            $this->aggregateGeographicCoverage($dateRange);
            $this->aggregatePrecisionLevels($dateRange);
            
            $this->log("Successfully completed archiving for {$this->date}");
        } catch (\Exception $e) {
            $this->log("Archiving failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function aggregateConfidenceDistribution(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        $sql = "
            SELECT 
                confidence_level,
                COUNT(*) as count,
                AVG(confidence_score) as avg_score
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
            GROUP BY confidence_level
        ";

        $results = Db::fetchAll(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        $distribution = [];
        $totalVisits = 0;

        foreach ($results as $row) {
            $distribution[$row['confidence_level']] = [
                'count' => (int)$row['count'],
                'avg_score' => (float)$row['avg_score'],
                'share' => 0,
            ];
            $totalVisits += $row['count'];
        }

        // Calculate shares
        foreach ($distribution as &$item) {
            $item['share'] = $totalVisits > 0 ? $item['count'] / $totalVisits : 0;
        }

        $this->saveDataTable('GeoPrecision_ConfidenceDistribution', $distribution);
        $this->saveMetric('GeoPrecision_TotalVisits', (float)$totalVisits);

        $this->log("Archived confidence distribution (" . count($distribution) . " levels)");
    }

    private function aggregateGeographicCoverage(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        // Coverage by country
        $sql = "
            SELECT 
                country_code,
                COUNT(*) as total_visits,
                SUM(CASE WHEN country_name IS NOT NULL THEN 1 ELSE 0 END) as known_visits,
                SUM(CASE WHEN region_name IS NOT NULL THEN 1 ELSE 0 END) as region_known,
                SUM(CASE WHEN city_name IS NOT NULL THEN 1 ELSE 0 END) as city_known
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
            GROUP BY country_code
            ORDER BY total_visits DESC
            LIMIT 50
        ";

        $results = Db::fetchAll(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        $coverage = [];
        foreach ($results as $row) {
            $coverage[] = [
                'country' => $row['country_code'],
                'visits' => (int)$row['total_visits'],
                'known_rate' => (float)($row['total_visits'] > 0 ? $row['known_visits'] / $row['total_visits'] : 0),
                'region_rate' => (float)($row['total_visits'] > 0 ? $row['region_known'] / $row['total_visits'] : 0),
                'city_rate' => (float)($row['total_visits'] > 0 ? $row['city_known'] / $row['total_visits'] : 0),
            ];
        }

        $this->saveDataTable('GeoPrecision_GeographicCoverage', $coverage);
        $this->log("Archived geographic coverage for " . count($coverage) . " countries");
    }

    private function aggregatePrecisionLevels(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        $sql = "
            SELECT 
                precision_level,
                COUNT(*) as count
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
            GROUP BY precision_level
        ";

        $results = Db::fetchAll(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        $precision = [];
        $totalVisits = 0;

        foreach ($results as $row) {
            $precision[$row['precision_level']] = (int)$row['count'];
            $totalVisits += $row['count'];
        }

        $this->saveDataTable('GeoPrecision_PrecisionLevels', $precision);
        $this->log("Archived precision level breakdown");
    }
}
