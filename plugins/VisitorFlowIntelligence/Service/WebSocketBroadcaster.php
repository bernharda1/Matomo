<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

/**
 * SB-020.2: WebSocketBroadcaster
 * 
 * Broadcasts real-time events to WebSocket clients
 * Manages client subscriptions and connections
 */
class WebSocketBroadcaster
{
    private static array $subscribers = [];
    private static array $subscriptionFilters = [];

    private const MAX_CLIENTS = 1000;
    private const CONNECTION_TIMEOUT = 300; // 5 minutes

    /**
     * Subscribe client to real-time events
     */
    public static function subscribe(
        string $clientId,
        int $siteId,
        ?string $segment = null,
        ?callable $onMessage = null
    ): void {
        if (count(self::$subscribers) >= self::MAX_CLIENTS) {
            throw new \Exception("Maximum concurrent clients reached");
        }

        self::$subscribers[$clientId] = [
            'connected_at' => time(),
            'last_heartbeat' => time(),
            'callback' => $onMessage,
        ];

        self::$subscriptionFilters[$clientId] = [
            'site_id' => $siteId,
            'segment' => $segment,
        ];
    }

    /**
     * Unsubscribe client
     */
    public static function unsubscribe(string $clientId): void
    {
        unset(self::$subscribers[$clientId]);
        unset(self::$subscriptionFilters[$clientId]);
    }

    /**
     * Broadcast event to all subscribers
     */
    public static function broadcast(string $eventType, array $data): void
    {
        $message = json_encode([
            'type' => $eventType,
            'timestamp' => time(),
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        foreach (self::$subscribers as $clientId => $subscriber) {
            self::sendToClient($clientId, $message);
        }
    }

    /**
     * Broadcast to subscribers of specific site
     */
    public static function broadcastToSite(
        int $siteId,
        string $eventType,
        array $data
    ): void {
        $message = json_encode([
            'type' => $eventType,
            'timestamp' => time(),
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        foreach (self::$subscriptionFilters as $clientId => $filter) {
            if ($filter['site_id'] === $siteId) {
                self::sendToClient($clientId, $message);
            }
        }
    }

    /**
     * Broadcast to subscribers with specific segment
     */
    public static function broadcastToSegment(
        int $siteId,
        string $segment,
        string $eventType,
        array $data
    ): void {
        $message = json_encode([
            'type' => $eventType,
            'timestamp' => time(),
            'data' => $data,
        ], JSON_THROW_ON_ERROR);

        foreach (self::$subscriptionFilters as $clientId => $filter) {
            if ($filter['site_id'] === $siteId && $filter['segment'] === $segment) {
                self::sendToClient($clientId, $message);
            }
        }
    }

    /**
     * Send message to specific client
     */
    private static function sendToClient(string $clientId, string $message): void
    {
        if (!isset(self::$subscribers[$clientId])) {
            return;
        }

        $callback = self::$subscribers[$clientId]['callback'];
        if ($callback && is_callable($callback)) {
            try {
                $callback($message);
            } catch (\Exception $e) {
                error_log("WebSocket client error: " . $e->getMessage());
                self::unsubscribe($clientId);
            }
        }
    }

    /**
     * Send heartbeat to keep connections alive
     */
    public static function sendHeartbeat(): void
    {
        $now = time();
        $deadClients = [];

        foreach (self::$subscribers as $clientId => $subscriber) {
            // Check for inactive clients
            if ($now - $subscriber['last_heartbeat'] > self::CONNECTION_TIMEOUT) {
                $deadClients[] = $clientId;
            } else {
                // Send heartbeat
                $heartbeat = json_encode([
                    'type' => 'heartbeat',
                    'timestamp' => $now,
                ]);

                self::sendToClient($clientId, $heartbeat);
                self::$subscribers[$clientId]['last_heartbeat'] = $now;
            }
        }

        // Remove dead clients
        foreach ($deadClients as $clientId) {
            self::unsubscribe($clientId);
        }
    }

    /**
     * Get connected clients count
     */
    public static function getConnectedCount(): int
    {
        return count(self::$subscribers);
    }

    /**
     * Get subscribers for specific site
     */
    public static function getSubscribersForSite(int $siteId): int
    {
        $count = 0;

        foreach (self::$subscriptionFilters as $filter) {
            if ($filter['site_id'] === $siteId) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get connection statistics
     */
    public static function getStatistics(): array
    {
        $stats = [
            'total_connected' => count(self::$subscribers),
            'max_allowed' => self::MAX_CLIENTS,
            'sites' => [],
            'segments' => [],
        ];

        foreach (self::$subscriptionFilters as $filter) {
            $siteId = $filter['site_id'];
            $segment = $filter['segment'] ?? 'all';

            // Count per site
            $stats['sites'][$siteId] = ($stats['sites'][$siteId] ?? 0) + 1;

            // Count per segment
            $key = "{$siteId}:{$segment}";
            $stats['segments'][$key] = ($stats['segments'][$key] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Cleanup expired connections
     */
    public static function cleanup(): void
    {
        self::sendHeartbeat();
    }
}
