<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Tasks;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Log;
use Piwik\Scheduler\Schedule\Daily;
use Piwik\Scheduler\Task;
use Piwik\Plugins\GeoPrecision\Infrastructure\RetentionManager;

final class RetentionTask extends Task
{
    public function __construct()
    {
        $this->setSchedule(new Daily(3));
    }

    public function getName(): string
    {
        return 'GeoPrecision_RetentionTask';
    }

    public function getDescription(): string
    {
        return 'Purges old geo precision data and aggregates according to retention policy.';
    }

    public function execute(): void
    {
        $manager = new RetentionManager();
        $result = $manager->purgeOldData(dryRun: false);

        $message = sprintf(
            'GeoPrecision retention executed: %d raw records deleted, %d aggregate records deleted.',
            $result['rawDeleted'],
            $result['aggregateDeleted']
        );

        Log::info($message);
    }
}
