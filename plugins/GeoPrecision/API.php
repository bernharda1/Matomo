<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision;

use DateTimeImmutable;
use DateTimeZone;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugin\API as PluginApi;
use Piwik\Plugins\GeoPrecision\Infrastructure\GeoQualityRepository;
use Piwik\Plugins\GeoPrecision\Service\ConsentGatekeeper;
use Piwik\Plugins\GeoPrecision\Service\GeoConfidenceScorer;

final class API extends PluginApi
{
    private static ?self $instance = null;
    private ConsentGatekeeper $consentGatekeeper;

    public function __construct()
    {
        $this->consentGatekeeper = new ConsentGatekeeper();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfidenceSummary(
        int $idSite,
        string $period,
        string $date,
        ?string $segment = null,
        ?bool $hasConsentForPreciseGeo = false
    ): array {
        Piwik::checkUserHasViewAccess($idSite);

        if (($segment ?? '') !== '') {
            throw new \DomainException('Segment support is not part of SB-009 MVP yet.');
        }

        if (!$this->consentGatekeeper->allowsPreciseGeoData((bool) $hasConsentForPreciseGeo)) {
            throw new \DomainException('Precise geo data requires explicit consent. Pass hasConsentForPreciseGeo=true to override.');
        }

        [$startDateTime, $endDateTime] = $this->resolveDateRange($period, $date);

        $repository = new GeoQualityRepository();
        $summary = $repository->fetchConfidenceDistribution($idSite, $startDateTime, $endDateTime);

        return [
            'meta' => [
                'idSite' => $idSite,
                'period' => $period,
                'date' => $date,
                'segment' => $segment ?? '',
                'generatedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
                'totalVisits' => $summary['totalVisits'],
                'averageConfidenceScore' => round($summary['averageConfidenceScore'], 2),
                'consentGated' => !$hasConsentForPreciseGeo,
            ],
            'confidenceDistribution' => $summary['confidenceDistribution'],
            'dimensions' => $summary['dimensions'],
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(string $period, string $date): array
    {
        $periodObject = PeriodFactory::build($period, $date);

        return [
            $periodObject->getDateStart()->toString('Y-m-d') . ' 00:00:00',
            $periodObject->getDateEnd()->toString('Y-m-d') . ' 23:59:59',
        ];
    }
}
