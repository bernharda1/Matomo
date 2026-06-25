<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence;

use Piwik\Menu\MenuReporting;
use Piwik\Plugin;
use Piwik\Plugins\VisitorFlowIntelligence\Tasks\RetentionTask;
use Piwik\ArchiveProcessor;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\FlowArchiver;

class VisitorFlowIntelligence extends Plugin
{
    public function registerEvents(): array
    {
        return [
            'Menu.Reporting.addItems' => 'addReportMenuItem',
            'ScheduledTaskScheduler.scheduleTask' => 'scheduleRetentionTask',
            'ArchiveProcessor.new' => 'onArchiveProcess',
        ];
    }

    /**
     * SB-014.3: Hook into Matomo's archiving process
     * 
     * Runs when ArchiveProcessor processes data for a site/period
     * Triggers aggregation of raw flow data into archive
     */
    public function onArchiveProcess(ArchiveProcessor $archiveProcessor): void
    {
        try {
            $archiver = new FlowArchiver($archiveProcessor);
            $archiver->aggregate();
        } catch (\Exception $e) {
            // Log but don't fail entire archiving process
            \Piwik\Log::warning("[VisitorFlowIntelligence] Archiving error: " . $e->getMessage());
        }
    }

    public function scheduleRetentionTask(): void
    {
        new RetentionTask();
    }

    public function addReportMenuItem(): void
    {
        MenuReporting::addItem(
            'General_Visitors',
            'VisitorFlowIntelligence_MenuTitle',
            [
                'module' => 'VisitorFlowIntelligence',
                'action' => 'index',
            ],
            true,
            45
        );
    }
}
