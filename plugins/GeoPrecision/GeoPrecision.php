<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision;

use Piwik\Menu\MenuReporting;
use Piwik\Plugin;
use Piwik\Plugins\GeoPrecision\Tasks\RetentionTask;
use Piwik\ArchiveProcessor;
use Piwik\Plugins\GeoPrecision\Infrastructure\GeoArchiver;

class GeoPrecision extends Plugin
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
            $archiver = new GeoArchiver($archiveProcessor);
            $archiver->aggregate();
        } catch (\Exception $e) {
            \Piwik\Log::warning("[GeoPrecision] Archiving error: " . $e->getMessage());
        }
    }

    public function addReportMenuItem(): void
    {
        MenuReporting::addItem(
            'General_Visitors',
            'GeoPrecision_MenuTitle',
            [
                'module' => 'GeoPrecision',
                'action' => 'index',
            ],
            true,
            47
        );
    }
}
