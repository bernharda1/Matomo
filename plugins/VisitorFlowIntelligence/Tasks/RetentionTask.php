<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tasks;

use Piwik\Common;
use Piwik\Log;
use Piwik\Scheduler\Schedule\Daily;
use Piwik\Scheduler\Task;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\RetentionManager;

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

        $message = sprintf(
            'VisitorFlowIntelligence retention executed: %d raw records deleted, %d aggregate records deleted.',
            $result['rawDeleted'],
            $result['aggregateDeleted']
        );

        Log::info($message);
    }
}
