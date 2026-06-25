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
use Piwik\Plugins\VisitorFlowIntelligence\Service\CacheManager;

final class API extends PluginApi
{
    private static ?self $instance = null;
    private CacheManager $cacheManager;

    private function __construct()
    {
        $this->cacheManager = new CacheManager();
    }

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
     * SB-015: Integrated caching layer
     * - Cache hits: ~50-100ms (cache fetch)
     * - Cache misses: ~2-5s (DB query + aggregation)
     * - TTL: 1h (day), 24h (week), 7d (month)
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

        // SB-015: Check cache first
        $cached = $this->cacheManager->get(
            $query->getIdSite(),
            $query->getPeriod(),
            $query->getDate(),
            $query->getSegment(),
            'getTopPaths'
        );

        if ($cached !== false) {
            return $cached;
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

        $response = [
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

        // SB-015: Store in cache for next request
        $this->cacheManager->set(
            $query->getIdSite(),
            $query->getPeriod(),
            $query->getDate(),
            $query->getSegment(),
            'getTopPaths',
            $response
        );

        return $response;
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
