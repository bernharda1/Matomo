<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure;

use Piwik\Db;
use Piwik\Common;

/**
 * SB-014.1: VisitorFlowIntelligence Archiver
 * 
 * Aggregates visitor flow data into:
 * - Top paths (by visits)
 * - Transitions (step A → step B)
 * - Drop-offs (where visitors exit)
 */
class FlowArchiver extends BaseArchiver
{
    protected string $pluginName = 'VisitorFlowIntelligence';

    /**
     * Aggregate flow data for this period
     */
    public function aggregate(): void
    {
        if ($this->isArchiveAlreadyDone()) {
            $this->log("Archive already exists for {$this->date}, skipping");
            return;
        }

        try {
            $dateRange = $this->getDateRange();
            
            // Aggregate top paths
            $this->aggregateTopPaths($dateRange);
            
            // Aggregate transitions
            $this->aggregateTransitions($dateRange);
            
            // Aggregate drop-offs
            $this->aggregateDropoffs($dateRange);
            
            $this->log("Successfully completed archiving for {$this->date}");
        } catch (\Exception $e) {
            $this->log("Archiving failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Aggregate top paths from raw flow data
     */
    private function aggregateTopPaths(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        $sql = "
            SELECT 
                path_hash,
                COUNT(*) as visits,
                AVG(depth) as avg_depth,
                SUM(visit_duration) as total_duration,
                MAX(server_time) as last_seen
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
            GROUP BY path_hash
            ORDER BY visits DESC
            LIMIT 100
        ";

        $results = Db::fetchAll(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        if (empty($results)) {
            $this->log("No flow paths found for archiving");
            return;
        }

        // Prepare data for storage
        $pathsData = [];
        foreach ($results as $row) {
            $pathsData[] = [
                'path_hash' => $row['path_hash'],
                'visits' => (int)$row['visits'],
                'avg_depth' => (float)$row['avg_depth'],
                'total_duration' => (int)$row['total_duration'],
                'share' => 0, // Will calculate below
                'last_seen' => $row['last_seen'],
            ];
        }

        // Calculate share (%)
        $totalVisits = array_sum(array_column($pathsData, 'visits'));
        foreach ($pathsData as &$path) {
            $path['share'] = $totalVisits > 0 ? $path['visits'] / $totalVisits : 0;
        }

        // Save to archive
        $this->saveDataTable('VisitorFlowIntelligence_TopPaths', $pathsData);
        $this->saveMetric('VisitorFlowIntelligence_TotalFlows', (float)$totalVisits);

        $this->log("Archived " . count($pathsData) . " top paths");
    }

    /**
     * Aggregate transitions (step A → step B)
     */
    private function aggregateTransitions(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        // For simplicity, we'll aggregate from raw data via step JSON parsing
        // In production, this would be optimized with materialized transition tables
        
        $sql = "
            SELECT 
                COUNT(*) as transition_count
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
                AND transition_count > 0
        ";

        $result = Db::fetchOne(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        $totalTransitions = $result ?? 0;
        
        // Note: Full transition parsing (step A → step B) requires JSON parsing
        // This is optimized in SB-014.2 with dedicated transition aggregation
        
        $this->saveMetric('VisitorFlowIntelligence_TotalTransitions', (float)$totalTransitions);
        $this->log("Archived {$totalTransitions} transitions");
    }

    /**
     * Aggregate drop-offs (exit points)
     */
    private function aggregateDropoffs(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        // Drop-off analysis: compare path depth distribution
        $sql = "
            SELECT 
                depth,
                COUNT(*) as count
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
            GROUP BY depth
            ORDER BY depth ASC
        ";

        $results = Db::fetchAll(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        $dropoffs = [];
        $previousCount = null;

        foreach ($results as $row) {
            $currentCount = (int)$row['count'];
            $depth = (int)$row['depth'];

            if ($previousCount !== null && $previousCount > $currentCount) {
                $dropoffCount = $previousCount - $currentCount;
                $dropoffRate = $dropoffCount / $previousCount;

                $dropoffs[] = [
                    'depth' => $depth,
                    'dropoff_count' => $dropoffCount,
                    'dropoff_rate' => $dropoffRate,
                    'continuing' => $currentCount,
                ];
            }

            $previousCount = $currentCount;
        }

        if (!empty($dropoffs)) {
            $this->saveDataTable('VisitorFlowIntelligence_Dropoffs', $dropoffs);
            $this->log("Archived " . count($dropoffs) . " drop-off points");
        }
    }
}
