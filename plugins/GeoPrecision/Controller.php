<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision;

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
        $hasConsent = Common::getRequestVar('hasConsent', 0, 'int');

        $payload = null;
        $errorMessage = null;
        $consentNotice = null;

        if (!$hasConsent) {
            $consentNotice = 'Precise geo data is gated by consent. City/region data is masked. To view precise data, ensure consent is active.';
        }

        try {
            $payload = API::getInstance()->getConfidenceSummary($idSite, $period, $date, null, (bool) $hasConsent);
        } catch (DomainException $exception) {
            $errorMessage = $exception->getMessage();
        }

        $view = new View('@GeoPrecision/index');
        $view->title = 'GeoPrecision_ReportTitle';
        $view->payload = $payload;
        $view->errorMessage = $errorMessage;
        $view->consentNotice = $consentNotice;
        $view->currentFilters = [
            'idSite' => $idSite,
            'period' => $period,
            'date' => $date,
            'hasConsent' => $hasConsent,
        ];

        return $view->render();
    }
}
