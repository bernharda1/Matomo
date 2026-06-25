<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence\Infrastructure;

use Piwik\Common;
use Piwik\Db;

final class DeviceQualityRepository
{
    /**
     * @return array{totalVisits:int, dimensions:array<int, array<string, mixed>>}
     */
    public function fetchUnknownRates(int $idSite, string $startDateTime, string $endDateTime): array
    {
        $logVisitTable = Common::prefixTable('log_visit');

        $availableColumns = $this->getAvailableColumns($logVisitTable);
        $dimensionColumnMap = [
            'deviceType' => 'config_device_type',
            'brand' => 'config_device_brand',
            'model' => 'config_device_model',
            'osName' => 'config_os',
            'browserName' => 'config_browser_name',
            'resolution' => 'config_resolution',
        ];

        $resolvedDimensions = [];
        foreach ($dimensionColumnMap as $key => $column) {
            if (isset($availableColumns[$column])) {
                $resolvedDimensions[$key] = $column;
            }
        }

        $totalVisits = (int) Db::fetchOne(
            sprintf(
                'SELECT COUNT(*)
                 FROM %s
                 WHERE idsite = ?
                   AND visit_last_action_time >= ?
                   AND visit_last_action_time <= ?',
                $logVisitTable
            ),
            [$idSite, $startDateTime, $endDateTime]
        );

        $rows = [];
        foreach ($resolvedDimensions as $dimensionKey => $columnName) {
            $unknownCount = (int) Db::fetchOne(
                sprintf(
                    "SELECT COUNT(*)
                     FROM %s
                     WHERE idsite = ?
                       AND visit_last_action_time >= ?
                       AND visit_last_action_time <= ?
                       AND (
                           %s IS NULL
                           OR TRIM(%s) = ''
                           OR LOWER(TRIM(%s)) = 'unknown'
                       )",
                    $logVisitTable,
                    $columnName,
                    $columnName,
                    $columnName
                ),
                [$idSite, $startDateTime, $endDateTime]
            );

            $unknownRate = $totalVisits > 0 ? $unknownCount / $totalVisits : 0.0;

            $rows[] = [
                'dimension' => $dimensionKey,
                'column' => $columnName,
                'unknownCount' => $unknownCount,
                'knownCount' => max(0, $totalVisits - $unknownCount),
                'unknownRate' => $unknownRate,
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => $right['unknownRate'] <=> $left['unknownRate']
        );

        return [
            'totalVisits' => $totalVisits,
            'dimensions' => $rows,
        ];
    }

    /**
     * @return array<string, true>
     */
    private function getAvailableColumns(string $tableName): array
    {
        $columns = Db::fetchAll(sprintf('SHOW COLUMNS FROM %s', $tableName));
        $lookup = [];

        foreach ($columns as $column) {
            $name = (string) ($column['Field'] ?? '');
            if ($name !== '') {
                $lookup[$name] = true;
            }
        }

        return $lookup;
    }
}
