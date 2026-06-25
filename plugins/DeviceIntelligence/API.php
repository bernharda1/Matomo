<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence;

use DateTimeImmutable;
use DateTimeZone;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugin\API as PluginApi;
use Piwik\Plugins\DeviceIntelligence\Infrastructure\DeviceQualityRepository;
use Piwik\Plugins\DeviceIntelligence\Service\ClientHintsMapper;

final class API extends PluginApi
{
    private static ?self $instance = null;

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
    public function getQualitySummary(
        int $idSite,
        string $period,
        string $date,
        ?string $segment = null,
        ?string $uaDataRaw = null
    ): array {
        Piwik::checkUserHasViewAccess($idSite);

        if (($segment ?? '') !== '') {
            throw new \DomainException('Segment support is not part of SB-007 MVP yet.');
        }

        [$startDateTime, $endDateTime] = $this->resolveDateRange($period, $date);

        $repository = new DeviceQualityRepository();
        $summary = $repository->fetchUnknownRates($idSite, $startDateTime, $endDateTime);

        $clientHintsMapping = null;
        if (($uaDataRaw ?? '') !== '') {
            $mapper = new ClientHintsMapper();
            $clientHintsMapping = $mapper->mapRaw((string) $uaDataRaw);
        }

        return [
            'meta' => [
                'idSite' => $idSite,
                'period' => $period,
                'date' => $date,
                'segment' => $segment ?? '',
                'generatedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
                'totalVisits' => $summary['totalVisits'],
            ],
            'dimensions' => $summary['dimensions'],
            'clientHintsMapping' => $clientHintsMapping,
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
