<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence\Tasks;

use Piwik\Common;
use Piwik\Log;
use Piwik\Scheduler\Schedule\Daily;
use Piwik\Scheduler\Task;
use Piwik\Plugins\DeviceIntelligence\Infrastructure\RetentionManager;

final class RetentionTask extends Task
{
    public function __construct()
    {
        $this->setSchedule(new Daily(3));
    }

    public function getName(): string
    {
        return 'DeviceIntelligence_RetentionTask';
    }

    public function getDescription(): string
    {
        return 'Purges old device intelligence data and aggregates according to retention policy.';
    }

    public function execute(): void
    {
        $manager = new RetentionManager();
        $result = $manager->purgeOldData(dryRun: false);

        $message = sprintf(
            'DeviceIntelligence retention executed: %d raw records deleted, %d aggregate records deleted.',
            $result['rawDeleted'],
            $result['aggregateDeleted']
        );

        Log::info($message);
    }
}
