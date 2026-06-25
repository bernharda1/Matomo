<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Infrastructure;

use Piwik\Common;
use Piwik\Db;

final class GeoQualityRepository
{
    /**
     * @return array{totalVisits:int, averageConfidenceScore:float, dimensions:array<int, array<string, mixed>>}
     */
    public function fetchConfidenceDistribution(int $idSite, string $startDateTime, string $endDateTime): array
    {
        $logVisitTable = Common::prefixTable('log_visit');

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

        $avgScore = (float) (Db::fetchOne(
            sprintf(
                'SELECT COALESCE(AVG(CASE
                   WHEN config_country IS NULL OR TRIM(config_country) = \'\' THEN 0
                   WHEN config_region IS NULL OR TRIM(config_region) = \'\' THEN 40
                   WHEN config_city IS NULL OR TRIM(config_city) = \'\' THEN 60
                   ELSE 80
                   END), 0)
                 FROM %s
                 WHERE idsite = ?
                   AND visit_last_action_time >= ?
                   AND visit_last_action_time <= ?',
                $logVisitTable
            ),
            [$idSite, $startDateTime, $endDateTime]
        ) ?? 0);

        $confidenceDistribution = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        $rows = Db::fetchAll(
            sprintf(
                'SELECT
                   CASE
                     WHEN config_country IS NULL OR TRIM(config_country) = \'\' THEN \'low\'
                     WHEN config_region IS NULL OR TRIM(config_region) = \'\' THEN \'medium\'
                     WHEN config_city IS NULL OR TRIM(config_city) = \'\' THEN \'medium\'
                     ELSE \'high\'
                   END as confidence_level,
                   COUNT(*) as cnt
                 FROM %s
                 WHERE idsite = ?
                   AND visit_last_action_time >= ?
                   AND visit_last_action_time <= ?
                 GROUP BY confidence_level',
                $logVisitTable
            ),
            [$idSite, $startDateTime, $endDateTime]
        );

        foreach ($rows as $row) {
            $level = strtolower((string) ($row['confidence_level'] ?? ''));
            $count = (int) ($row['cnt'] ?? 0);
            if (isset($confidenceDistribution[$level])) {
                $confidenceDistribution[$level] = $count;
            }
        }

        $dimensions = [
            [
                'dimension' => 'Country',
                'dataPoints' => $totalVisits,
                'knownCount' => $this->countNonNull($logVisitTable, 'config_country', $idSite, $startDateTime, $endDateTime),
                'unknownCount' => $totalVisits - $this->countNonNull($logVisitTable, 'config_country', $idSite, $startDateTime, $endDateTime),
            ],
            [
                'dimension' => 'Region',
                'dataPoints' => $totalVisits,
                'knownCount' => $this->countNonNull($logVisitTable, 'config_region', $idSite, $startDateTime, $endDateTime),
                'unknownCount' => $totalVisits - $this->countNonNull($logVisitTable, 'config_region', $idSite, $startDateTime, $endDateTime),
            ],
            [
                'dimension' => 'City',
                'dataPoints' => $totalVisits,
                'knownCount' => $this->countNonNull($logVisitTable, 'config_city', $idSite, $startDateTime, $endDateTime),
                'unknownCount' => $totalVisits - $this->countNonNull($logVisitTable, 'config_city', $idSite, $startDateTime, $endDateTime),
            ],
        ];

        return [
            'totalVisits' => $totalVisits,
            'averageConfidenceScore' => $avgScore,
            'confidenceDistribution' => $confidenceDistribution,
            'dimensions' => $dimensions,
        ];
    }

    private function countNonNull(
        string $tableName,
        string $column,
        int $idSite,
        string $startDateTime,
        string $endDateTime
    ): int {
        return (int) Db::fetchOne(
            sprintf(
                "SELECT COUNT(*)
                 FROM %s
                 WHERE idsite = ?
                   AND visit_last_action_time >= ?
                   AND visit_last_action_time <= ?
                   AND %s IS NOT NULL
                   AND TRIM(%s) != ''
                   AND LOWER(TRIM(%s)) != 'unknown'",
                $tableName,
                $column,
                $column,
                $column
            ),
            [$idSite, $startDateTime, $endDateTime]
        );
    }
}
