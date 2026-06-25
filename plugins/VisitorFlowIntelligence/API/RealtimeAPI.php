<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\API;

use Piwik\Plugins\VisitorFlowIntelligence\Service\RealtimeProcessor;
use Piwik\Plugins\VisitorFlowIntelligence\Service\WebSocketBroadcaster;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\FlowEventRepository;

/**
 * SB-020.3: RealtimeAPI
 * 
 * Public API for real-time data endpoints
 * Used by WebSocket connections and polling clients
 */
class RealtimeAPI
{
    private RealtimeProcessor $processor;
    private FlowEventRepository $repository;

    /**
     * Constructor
     */
    public function __construct(
        RealtimeProcessor $processor,
        FlowEventRepository $repository
    ) {
        $this->processor = $processor;
        $this->repository = $repository;
    }

    /**
     * Get real-time flows endpoint
     */
    public function getRealtimeFlows(int $idSite, ?string $segment = null): array
    {
        // Rate limit check (delegated to RateLimiter middleware)
        $processor = new RealtimeProcessor(
            $this->repository,
            $idSite,
            $segment,
            updateIntervalSeconds: 10
        );

        return $processor->getRealtimeFlows();
    }

    /**
     * Get real-time transitions endpoint
     */
    public function getRealtimeTransitions(int $idSite, ?string $segment = null): array
    {
        $processor = new RealtimeProcessor(
            $this->repository,
            $idSite,
            $segment,
            updateIntervalSeconds: 10
        );

        return $processor->getRealtimeTransitions();
    }

    /**
     * Get real-time dropoffs endpoint
     */
    public function getRealtimeDropoffs(int $idSite, ?string $segment = null): array
    {
        $processor = new RealtimeProcessor(
            $this->repository,
            $idSite,
            $segment,
            updateIntervalSeconds: 10
        );

        return $processor->getRealtimeDropoffs();
    }

    /**
     * Get real-time visitor count endpoint
     */
    public function getRealtimeVisitorCount(int $idSite, ?string $segment = null): array
    {
        $processor = new RealtimeProcessor(
            $this->repository,
            $idSite,
            $segment,
            updateIntervalSeconds: 10
        );

        return $processor->getRealtimeVisitorCount();
    }

    /**
     * Get comprehensive real-time data
     */
    public function getComprehensiveRealtimeData(int $idSite, ?string $segment = null): array
    {
        $processor = new RealtimeProcessor(
            $this->repository,
            $idSite,
            $segment,
            updateIntervalSeconds: 10
        );

        return $processor->getComprehensiveRealtimeData();
    }

    /**
     * Subscribe to real-time events (WebSocket)
     */
    public function subscribeToRealtimeEvents(
        int $idSite,
        string $clientId,
        ?string $segment = null
    ): array {
        WebSocketBroadcaster::subscribe($clientId, $idSite, $segment);

        return [
            'status' => 'subscribed',
            'client_id' => $clientId,
            'site_id' => $idSite,
            'segment' => $segment ?? 'all',
            'connected_clients' => WebSocketBroadcaster::getConnectedCount(),
        ];
    }

    /**
     * Unsubscribe from real-time events
     */
    public function unsubscribeFromRealtimeEvents(string $clientId): array
    {
        WebSocketBroadcaster::unsubscribe($clientId);

        return [
            'status' => 'unsubscribed',
            'client_id' => $clientId,
            'connected_clients' => WebSocketBroadcaster::getConnectedCount(),
        ];
    }

    /**
     * Get real-time statistics
     */
    public function getRealtimeStatistics(): array
    {
        return WebSocketBroadcaster::getStatistics();
    }

    /**
     * Broadcast event (admin only)
     */
    public function broadcastEvent(
        int $idSite,
        string $eventType,
        array $data,
        ?string $segment = null
    ): array {
        if ($segment) {
            WebSocketBroadcaster::broadcastToSegment($idSite, $segment, $eventType, $data);
        } else {
            WebSocketBroadcaster::broadcastToSite($idSite, $eventType, $data);
        }

        return [
            'status' => 'broadcasted',
            'event_type' => $eventType,
            'site_id' => $idSite,
            'segment' => $segment ?? 'all',
        ];
    }
}
