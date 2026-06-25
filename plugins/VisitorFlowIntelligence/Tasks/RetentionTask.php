<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tasks;

use Piwik\Common;
use Piwik\Log;
use Piwik\Scheduler\Schedule\Daily;
use Piwik\Scheduler\Task;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\RetentionManager;
use Piwik\Plugins\VisitorFlowIntelligence\Service\CacheManager;

final class RetentionTask extends Task
{
    public function __construct()
    {
        $this->setSchedule(new Daily(3));
    }

    public function getName(): string
    {
        return 'VisitorFlowIntelligence_RetentionTask';
    }

    public function getDescription(): string
    {
        return 'Purges old visitor flow data and aggregates according to retention policy.';
    }

    public function execute(): void
    {
        $manager = new RetentionManager();
        $result = $manager->purgeOldData(dryRun: false);

        // SB-015: Invalidate caches after data purge
        $cacheManager = new CacheManager();
        $this->invalidateCacheForPurgedData($cacheManager, $result);

        $message = sprintf(
            'VisitorFlowIntelligence retention executed: %d raw records deleted, %d aggregate records deleted.',
            $result['rawDeleted'],
            $result['aggregateDeleted']
        );

        Log::info($message);
    }

    /**
     * SB-015: Invalidate caches for purged date ranges
     * 
     * When retention purges data, we must invalidate the corresponding caches
     * to ensure reports don't serve stale aggregated data
     */
    private function invalidateCacheForPurgedData(CacheManager $cacheManager, array $result): void
    {
        if (!isset($result['purgedDateStart']) || !isset($result['purgedDateEnd'])) {
            // No specific date range tracked, invalidate all
            $cacheManager->flush();
            Log::debug('[VisitorFlowIntelligence] Flushed all caches (no date range info)');
            return;
        }

        // Invalidate caches for purged date range
        $sites = $result['sites'] ?? [1]; // Default to site 1 if not tracked
        foreach ($sites as $idSite) {
            $count = $cacheManager->invalidateDateRange(
                $idSite,
                $result['purgedDateStart'],
                $result['purgedDateEnd']
            );
            Log::debug(
                "[VisitorFlowIntelligence] Invalidated {$count} cache entries for site {$idSite} " .
                "({$result['purgedDateStart']} to {$result['purgedDateEnd']})"
            );
        }
    }
}
