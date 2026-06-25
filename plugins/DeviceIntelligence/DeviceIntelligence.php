<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence;

use Piwik\Menu\MenuReporting;
use Piwik\Plugin;
use Piwik\Plugins\DeviceIntelligence\Tasks\RetentionTask;

class DeviceIntelligence extends Plugin
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
