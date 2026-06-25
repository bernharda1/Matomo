<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence;

use Piwik\Menu\MenuReporting;
use Piwik\Plugin;
use Piwik\Plugins\DeviceIntelligence\Tasks\RetentionTask;
use Piwik\ArchiveProcessor;
use Piwik\Plugins\DeviceIntelligence\Infrastructure\DeviceArchiver;

class DeviceIntelligence extends Plugin
{
    public function registerEvents(): array
    {
        return [
            'Menu.Reporting.addItems' => 'addReportMenuItem',
            'ScheduledTaskScheduler.scheduleTask' => 'scheduleRetentionTask',
            'ArchiveProcessor.new' => 'onArchiveProcess',
        ];
    }

    public function scheduleRetentionTask(): void
    {
        new RetentionTask();
    }

    /**
     * SB-014.3: Hook into Matomo's archiving process
     */
    public function onArchiveProcess(ArchiveProcessor $archiveProcessor): void
    {
        try {
            $archiver = new DeviceArchiver($archiveProcessor);
            $archiver->aggregate();
        } catch (\Exception $e) {
            \Piwik\Log::warning("[DeviceIntelligence] Archiving error: " . $e->getMessage());
        }
    }

    public function addReportMenuItem(): void
    {
        MenuReporting::addItem(
            'General_Visitors',
            'DeviceIntelligence_MenuTitle',
            [
                'module' => 'DeviceIntelligence',
                'action' => 'index',
            ],
            true,
            46
        );
    }
}
