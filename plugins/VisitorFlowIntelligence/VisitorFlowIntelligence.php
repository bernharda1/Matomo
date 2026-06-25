<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence;

use Piwik\Menu\MenuReporting;
use Piwik\Plugin;
use Piwik\Plugins\VisitorFlowIntelligence\Tasks\RetentionTask;

class VisitorFlowIntelligence extends Plugin
{
    public function registerEvents(): array
    {
        return [
            'Menu.Reporting.addItems' => 'addReportMenuItem',
            'ScheduledTaskScheduler.scheduleTask' => 'scheduleRetentionTask',
        ];
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
