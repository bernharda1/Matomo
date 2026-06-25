<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence;

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
        $maxDepth = Common::getRequestVar('maxDepth', 5, 'int');
        $limit = Common::getRequestVar('limit', 20, 'int');

        $payload = null;
        $errorMessage = null;

        try {
            $payload = API::getInstance()->getTopPaths($idSite, $period, $date, null, $maxDepth, $limit);
        } catch (DomainException $exception) {
            $errorMessage = $exception->getMessage();
        }

        $view = new View('@VisitorFlowIntelligence/index');

        $view->title = 'VisitorFlowIntelligence_ReportTitle';
        $view->payload = $payload;
        $view->errorMessage = $errorMessage;
        $view->currentFilters = [
            'idSite' => $idSite,
            'period' => $period,
            'date' => $date,
            'maxDepth' => $maxDepth,
            'limit' => $limit,
        ];

        return $view->render();
    }
}
