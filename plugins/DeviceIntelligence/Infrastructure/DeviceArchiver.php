<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence\Infrastructure;

use Piwik\Db;
use Piwik\Common;

/**
 * SB-014.1: DeviceIntelligence Archiver
 * 
 * Aggregates device data:
 * - Unknown-rate distribution
 * - Top devices (brand, model, OS, browser)
 * - Client Hints adoption
 */
class DeviceArchiver extends BaseArchiver
{
    protected string $pluginName = 'DeviceIntelligence';

    public function aggregate(): void
    {
        if ($this->isArchiveAlreadyDone()) {
            $this->log("Archive already exists for {$this->date}, skipping");
            return;
        }

        try {
            $dateRange = $this->getDateRange();
            
            $this->aggregateUnknownRates($dateRange);
            $this->aggregateTopDevices($dateRange);
            $this->aggregateClientHintsAdoption($dateRange);
            
            $this->log("Successfully completed archiving for {$this->date}");
        } catch (\Exception $e) {
            $this->log("Archiving failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function aggregateUnknownRates(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        $sql = "
            SELECT 
                device_type,
                COUNT(*) as total_visits,
                SUM(CASE WHEN device_type IS NULL OR device_type = 'unknown' THEN 1 ELSE 0 END) as unknown_device,
                SUM(CASE WHEN brand IS NULL THEN 1 ELSE 0 END) as unknown_brand,
                SUM(CASE WHEN os_name IS NULL THEN 1 ELSE 0 END) as unknown_os,
                SUM(CASE WHEN browser_name IS NULL THEN 1 ELSE 0 END) as unknown_browser
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
            GROUP BY device_type
        ";

        $results = Db::fetchAll(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        $rates = [];
        $totalVisits = 0;

        foreach ($results as $row) {
            $total = (int)$row['total_visits'];
            $rates[] = [
                'device_type' => $row['device_type'] ?? 'unknown',
                'visits' => $total,
                'unknown_device_rate' => $total > 0 ? $row['unknown_device'] / $total : 0,
                'unknown_brand_rate' => $total > 0 ? $row['unknown_brand'] / $total : 0,
                'unknown_os_rate' => $total > 0 ? $row['unknown_os'] / $total : 0,
                'unknown_browser_rate' => $total > 0 ? $row['unknown_browser'] / $total : 0,
            ];
            $totalVisits += $total;
        }

        $this->saveDataTable('DeviceIntelligence_UnknownRates', $rates);
        $this->saveMetric('DeviceIntelligence_TotalVisits', (float)$totalVisits);

        $this->log("Archived unknown-rate distribution");
    }

    private function aggregateTopDevices(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        // Top brands
        $sql = "
            SELECT 
                brand,
                COUNT(*) as count
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
                AND brand IS NOT NULL
            GROUP BY brand
            ORDER BY count DESC
            LIMIT 20
        ";

        $results = Db::fetchAll(
            $sql,
            [$this->idSite, $dateRange['start'], $dateRange['end']]
        );

        $topDevices = [
            'brands' => $results,
        ];

        $this->saveDataTable('DeviceIntelligence_TopDevices', $topDevices);
        $this->log("Archived top devices");
    }

    private function aggregateClientHintsAdoption(array $dateRange): void
    {
        $rawTable = $this->getRawDataTableName();
        
        $sql = "
            SELECT 
                SUM(CASE WHEN client_hints_present = TRUE THEN 1 ELSE 0 END) as with_hints,
                COUNT(*) as total_visits
            FROM {$rawTable}
            WHERE idsite = ?
                AND server_time BETWEEN ? AND ?
        ";

        $result = Db::fetchOne($sql, [$this->idSite, $dateRange['start'], $dateRange['end']]);

        if ($result) {
            $adoption = [
                'with_hints' => (int)($result['with_hints'] ?? 0),
                'total' => (int)$result['total_visits'],
                'adoption_rate' => (float)(($result['with_hints'] ?? 0) / $result['total_visits']),
            ];
            $this->saveDataTable('DeviceIntelligence_ClientHintsAdoption', $adoption);
        }

        $this->log("Archived Client Hints adoption metrics");
    }
}
