<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\FlowEventRepository;

/**
 * SB-020.1: RealtimeProcessor
 * 
 * Processes real-time visitor flow events for live dashboards
 * Provides streaming data for WebSocket clients
 */
class RealtimeProcessor
{
    private FlowEventRepository $repository;
    private int $siteId;
    private ?string $segment;
    private int $updateIntervalSeconds;

    /**
     * Constructor
     */
    public function __construct(
        FlowEventRepository $repository,
        int $siteId,
        ?string $segment = null,
        int $updateIntervalSeconds = 10
    ) {
        SecurityValidator::validateSiteId($siteId);
        if ($segment) {
            SecurityValidator::validateSegment($segment);
        }

        $this->repository = $repository;
        $this->siteId = $siteId;
        $this->segment = $segment;
        $this->updateIntervalSeconds = $updateIntervalSeconds;
    }

    /**
     * Get real-time flow data (latest visitors)
     */
    public function getRealtimeFlows(): array
    {
        $flows = $this->repository->fetchRecentFlows(
            $this->siteId,
            $this->segment,
            limit: 50  // Last 50 visitors
        );

        return [
            'timestamp' => time(),
            'interval' => $this->updateIntervalSeconds,
            'site_id' => $this->siteId,
            'segment' => $this->segment ?? 'all',
            'total_visitors' => count($flows),
            'flows' => $this->formatFlowsForDisplay($flows),
        ];
    }

    /**
     * Get real-time transitions (live path changes)
     */
    public function getRealtimeTransitions(): array
    {
        $transitions = $this->repository->fetchRecentTransitions(
            $this->siteId,
            $this->segment,
            limit: 100  // Last 100 transitions
        );

        // Aggregate by transition type
        $aggregated = $this->aggregateTransitions($transitions);

        return [
            'timestamp' => time(),
            'interval' => $this->updateIntervalSeconds,
            'site_id' => $this->siteId,
            'segment' => $this->segment ?? 'all',
            'total_transitions' => count($transitions),
            'top_transitions' => array_slice($aggregated, 0, 10),
            'transitions' => $transitions,
        ];
    }

    /**
     * Get real-time dropoffs (active users dropping off)
     */
    public function getRealtimeDropoffs(): array
    {
        $dropoffs = $this->repository->fetchRecentDropoffs(
            $this->siteId,
            $this->segment,
            limit: 50  // Last 50 dropoff events
        );

        // Aggregate by dropoff location
        $aggregated = $this->aggregateDropoffs($dropoffs);

        return [
            'timestamp' => time(),
            'interval' => $this->updateIntervalSeconds,
            'site_id' => $this->siteId,
            'segment' => $this->segment ?? 'all',
            'total_dropoffs' => count($dropoffs),
            'top_dropoff_locations' => array_slice($aggregated, 0, 10),
            'recent_dropoffs' => $dropoffs,
        ];
    }

    /**
     * Get real-time visitor count
     */
    public function getRealtimeVisitorCount(): array
    {
        $count = $this->repository->fetchCurrentVisitorCount($this->siteId, $this->segment);
        $trend = $this->repository->fetchVisitorCountTrend($this->siteId, $this->segment, minutes: 30);

        return [
            'timestamp' => time(),
            'interval' => $this->updateIntervalSeconds,
            'site_id' => $this->siteId,
            'segment' => $this->segment ?? 'all',
            'current_visitors' => $count,
            'trend_30_min' => $trend,
        ];
    }

    /**
     * Get all real-time data (comprehensive dashboard)
     */
    public function getComprehensiveRealtimeData(): array
    {
        return [
            'timestamp' => time(),
            'interval' => $this->updateIntervalSeconds,
            'site_id' => $this->siteId,
            'segment' => $this->segment ?? 'all',
            'flows' => $this->getRealtimeFlows(),
            'transitions' => $this->getRealtimeTransitions(),
            'dropoffs' => $this->getRealtimeDropoffs(),
            'visitor_count' => $this->getRealtimeVisitorCount(),
        ];
    }

    /**
     * Format flows for real-time display
     */
    private function formatFlowsForDisplay(array $flows): array
    {
        return array_map(fn($flow) => [
            'visitor_id' => $flow['idvisit'] ?? null,
            'path' => $flow['steps'] ?? [],
            'depth' => $flow['depth'] ?? 0,
            'duration' => $flow['visit_duration'] ?? 0,
            'timestamp' => $flow['server_time'] ?? null,
            'actions' => count($flow['steps'] ?? []),
        ], $flows);
    }

    /**
     * Aggregate transitions by source->destination
     */
    private function aggregateTransitions(array $transitions): array
    {
        $aggregated = [];

        foreach ($transitions as $transition) {
            $key = $transition['from_step'] . '->' . $transition['to_step'];
            $aggregated[$key] = ($aggregated[$key] ?? 0) + 1;
        }

        // Sort by frequency
        arsort($aggregated);

        return $aggregated;
    }

    /**
     * Aggregate dropoffs by location
     */
    private function aggregateDropoffs(array $dropoffs): array
    {
        $aggregated = [];

        foreach ($dropoffs as $dropoff) {
            $location = $dropoff['step_id'] ?? 'unknown';
            $aggregated[$location] = ($aggregated[$location] ?? 0) + 1;
        }

        // Sort by frequency
        arsort($aggregated);

        return $aggregated;
    }

    /**
     * Stream events for WebSocket clients (generator)
     */
    public function streamRealtimeEvents(callable $onData): void
    {
        $lastCheck = time();

        while (true) {
            $now = time();

            // Check for new data every updateInterval
            if ($now - $lastCheck >= $this->updateIntervalSeconds) {
                $data = $this->getComprehensiveRealtimeData();
                $onData($data);
                $lastCheck = $now;
            }

            // Sleep for 1 second to avoid busy waiting
            usleep(1000000);

            // In production, check for disconnect/stop signals
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }
}
