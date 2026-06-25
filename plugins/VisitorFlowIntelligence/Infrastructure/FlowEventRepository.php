<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure;

use Piwik\Common;
use Piwik\Db;

final class FlowEventRepository
{
    /**
     * @return array<int, array<int, string>>
     */
    public function fetchVisitSteps(
        int $idSite,
        string $startDateTime,
        string $endDateTime,
        int $maxDepth,
        int $maxVisits = 5000
    ): array {
        $logVisitTable = Common::prefixTable('log_visit');
        $logLinkVisitActionTable = Common::prefixTable('log_link_visit_action');
        $logActionTable = Common::prefixTable('log_action');

        $sql = sprintf(
            'SELECT lva.idvisit, action_url.name AS action_url
             FROM %s lva
             INNER JOIN %s lv ON lv.idvisit = lva.idvisit
             INNER JOIN %s action_url ON action_url.idaction = lva.idaction_url
             WHERE lv.idsite = ?
               AND lv.visit_last_action_time >= ?
               AND lv.visit_last_action_time <= ?
             ORDER BY lva.idvisit ASC, lva.server_time ASC, lva.pageview_position ASC, lva.idlink_va ASC',
            $logLinkVisitActionTable,
            $logVisitTable,
            $logActionTable
        );

        $rows = Db::fetchAll($sql, [$idSite, $startDateTime, $endDateTime]);

        $visitSteps = [];
        foreach ($rows as $row) {
            $idVisit = (int) ($row['idvisit'] ?? 0);
            if ($idVisit <= 0) {
                continue;
            }

            $normalizedStep = self::normalizeUrl((string) ($row['action_url'] ?? ''));
            if ($normalizedStep === '') {
                continue;
            }

            if (!array_key_exists($idVisit, $visitSteps)) {
                if (count($visitSteps) >= $maxVisits) {
                    continue;
                }
                $visitSteps[$idVisit] = [];
            }

            if (count($visitSteps[$idVisit]) < $maxDepth) {
                $visitSteps[$idVisit][] = $normalizedStep;
            }
        }

        return $visitSteps;
    }

    private static function normalizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parsedPath = parse_url($value, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $path = $parsedPath;
        } else {
            $path = $value;
        }

        $path = preg_replace('/\/{2,}/', '/', $path);
        $path = (string) $path;
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
    }
}
