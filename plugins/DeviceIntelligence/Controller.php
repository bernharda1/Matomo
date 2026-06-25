<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence;

use DomainException;
use Piwik\Common;
use Piwik\Plugin\Controller as PluginController;
use Piwik\View;

final class Controller extends PluginController
{
    public function index(): string
    {
        $idSite = Common::getRequestVar('idSite', 1, 'int');
        $period = Common::getRequestVar('period', 'day', 'string');
        $date = Common::getRequestVar('date', 'yesterday', 'string');
        $uaDataRaw = Common::getRequestVar('uadata', '', 'string');

        $payload = null;
        $errorMessage = null;

        try {
            $payload = API::getInstance()->getQualitySummary(
                $idSite,
                $period,
                $date,
                null,
                $uaDataRaw !== '' ? $uaDataRaw : null
            );
        } catch (DomainException $exception) {
            $errorMessage = $exception->getMessage();
        }

        $view = new View('@DeviceIntelligence/index');
        $view->title = 'DeviceIntelligence_ReportTitle';
        $view->payload = $payload;
        $view->errorMessage = $errorMessage;
        $view->currentFilters = [
            'idSite' => $idSite,
            'period' => $period,
            'date' => $date,
            'uaDataRaw' => $uaDataRaw,
        ];

        return $view->render();
    }
}
