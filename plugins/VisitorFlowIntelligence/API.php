<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence;

use DateTimeImmutable;
use DateTimeZone;
use Piwik\Period\Factory as PeriodFactory;
use Piwik\Piwik;
use Piwik\Plugin\API as PluginApi;
use Piwik\Plugins\VisitorFlowIntelligence\Domain\FlowQuery;
use Piwik\Plugins\VisitorFlowIntelligence\Domain\FlowResult;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\FlowEventRepository;
use Piwik\Plugins\VisitorFlowIntelligence\Service\FlowPathAggregator;

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
     * MVP endpoint for top visitor paths.
     *
     * @return array<string, mixed>
     */
    public function getTopPaths(
        int $idSite,
        string $period,
        string $date,
        ?string $segment = null,
        int $maxDepth = 5,
        int $limit = 20
    ): array {
        Piwik::checkUserHasViewAccess($idSite);

        $query = new FlowQuery($idSite, $period, $date, $segment, $maxDepth, $limit);

        if (($query->getSegment() ?? '') !== '') {
            throw new \DomainException('Segment support is not part of SB-005 MVP yet.');
        }

        [$startDateTime, $endDateTime] = $this->resolveDateRange($query->getPeriod(), $query->getDate());

        $repository = new FlowEventRepository();
        $visitSteps = $repository->fetchVisitSteps(
            $query->getIdSite(),
            $startDateTime,
            $endDateTime,
            $query->getMaxDepth()
        );

        $aggregator = new FlowPathAggregator();
        $aggregation = $aggregator->aggregate($visitSteps, $query->getLimit());

        $result = new FlowResult(
            $query->getIdSite(),
            $query->getPeriod(),
            $query->getDate(),
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            $aggregation['totalVisits'],
            $aggregation['paths'],
            $aggregation['transitions'],
            $aggregation['dropoffs'],
            $query->getSegment()
        );

        $payload = $result->toArray();

        return [
            'meta' => [
                'idSite' => $payload['idSite'],
                'period' => $payload['period'],
                'date' => $payload['date'],
                'segment' => $payload['segment'] ?? '',
                'generatedAt' => $payload['generatedAt'],
                'totalVisits' => $payload['totalVisits'],
            ],
            'paths' => $payload['paths'],
            'transitions' => $payload['transitions'],
            'dropoffs' => $payload['dropoffs'],
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(string $period, string $date): array
    {
        $periodObject = PeriodFactory::build($period, $date);

        $startDate = $periodObject->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $endDate = $periodObject->getDateEnd()->toString('Y-m-d') . ' 23:59:59';

        return [$startDate, $endDate];
    }
}
