<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision;

use Piwik\Menu\MenuReporting;
use Piwik\Plugin;
use Piwik\Plugins\GeoPrecision\Tasks\RetentionTask;

class GeoPrecision extends Plugin
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
