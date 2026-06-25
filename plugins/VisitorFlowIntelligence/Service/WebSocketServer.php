<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * SB-020.2: WebSocketServer
 * 
 * Real-time WebSocket server for live dashboard updates
 * Handles client connections, subscriptions, and message routing
 */
class WebSocketServer implements MessageComponentInterface
{
    private $clients;
    private $subscriptions = [];
    private $heartbeatInterval = 30;
    private $lastHeartbeat = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        error_log("[WebSocket] Server initialized");
    }

    /**
     * Client connects
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $clientId = $conn->resourceId;
        $this->clients->attach($conn);

        error_log("[WebSocket] Client {$clientId} connected. Total: " . $this->clients->count());

        // Send welcome message
        $this->send($conn, [
            'type' => 'connected',
            'client_id' => $clientId,
            'message' => 'Welcome to VisitorFlow Real-time Dashboard',
            'timestamp' => time(),
        ]);
    }

    /**
     * Handle incoming message
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);

            if (!$data || !isset($data['action'])) {
                $this->send($from, ['type' => 'error', 'message' => 'Invalid message format']);
                return;
            }

            $clientId = $from->resourceId;
            $action = $data['action'];

            switch ($action) {
                case 'subscribe':
                    $this->handleSubscribe($from, $data);
                    break;

                case 'unsubscribe':
                    $this->handleUnsubscribe($from, $data);
                    break;

                case 'ping':
                    $this->send($from, ['type' => 'pong', 'timestamp' => time()]);
                    break;

                case 'get_stats':
                    $this->handleGetStats($from);
                    break;

                default:
                    $this->send($from, ['type' => 'error', 'message' => "Unknown action: {$action}"]);
                    break;
            }
        } catch (\Exception $e) {
            error_log("[WebSocket] Error: " . $e->getMessage());
            $this->send($from, ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Client disconnects
     */
    public function onClose(ConnectionInterface $conn)
    {
        $clientId = $conn->resourceId;

        // Remove subscriptions
        if (isset($this->subscriptions[$clientId])) {
            unset($this->subscriptions[$clientId]);
        }

        // Remove from clients
        $this->clients->detach($conn);

        error_log("[WebSocket] Client {$clientId} disconnected. Total: " . $this->clients->count());
    }

    /**
     * Error handler
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        error_log("[WebSocket] Error: " . $e->getMessage());
        $conn->close();
    }

    /**
     * Handle subscribe action
     */
    private function handleSubscribe(ConnectionInterface $conn, array $data)
    {
        $clientId = $conn->resourceId;
        $siteId = $data['site_id'] ?? null;
        $segment = $data['segment'] ?? null;

        if (!$siteId) {
            $this->send($conn, ['type' => 'error', 'message' => 'site_id is required']);
            return;
        }

        // Validate
        SecurityValidator::validateSiteId((int)$siteId);

        // Store subscription
        $subscriptionKey = "{$siteId}:" . ($segment ?? 'all');
        $this->subscriptions[$clientId][$subscriptionKey] = [
            'site_id' => $siteId,
            'segment' => $segment,
            'subscribed_at' => time(),
        ];

        // Confirm subscription
        $this->send($conn, [
            'type' => 'subscribed',
            'site_id' => $siteId,
            'segment' => $segment ?? 'all',
            'timestamp' => time(),
        ]);

        error_log("[WebSocket] Client {$clientId} subscribed to site {$siteId}:{$segment}");
    }

    /**
     * Handle unsubscribe action
     */
    private function handleUnsubscribe(ConnectionInterface $conn, array $data)
    {
        $clientId = $conn->resourceId;
        $siteId = $data['site_id'] ?? null;
        $segment = $data['segment'] ?? null;

        if (!$siteId) {
            $this->send($conn, ['type' => 'error', 'message' => 'site_id is required']);
            return;
        }

        $subscriptionKey = "{$siteId}:" . ($segment ?? 'all');

        if (isset($this->subscriptions[$clientId][$subscriptionKey])) {
            unset($this->subscriptions[$clientId][$subscriptionKey]);
        }

        $this->send($conn, [
            'type' => 'unsubscribed',
            'site_id' => $siteId,
            'segment' => $segment ?? 'all',
        ]);

        error_log("[WebSocket] Client {$clientId} unsubscribed from site {$siteId}:{$segment}");
    }

    /**
     * Handle stats request
     */
    private function handleGetStats(ConnectionInterface $conn)
    {
        $stats = [
            'total_clients' => $this->clients->count(),
            'subscriptions' => count($this->subscriptions),
            'timestamp' => time(),
        ];

        $this->send($conn, ['type' => 'stats', 'data' => $stats]);
    }

    /**
     * Send message to client
     */
    private function send(ConnectionInterface $conn, array $message)
    {
        try {
            $conn->send(json_encode($message, JSON_THROW_ON_ERROR));
        } catch (\Exception $e) {
            error_log("[WebSocket] Send error: " . $e->getMessage());
        }
    }

    /**
     * Broadcast to all clients
     */
    public function broadcast(array $message)
    {
        $msg = json_encode($message, JSON_THROW_ON_ERROR);

        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }

    /**
     * Broadcast to site subscribers
     */
    public function broadcastToSite(int $siteId, array $message)
    {
        $msg = json_encode($message, JSON_THROW_ON_ERROR);

        foreach ($this->subscriptions as $clientId => $subs) {
            foreach ($subs as $key => $sub) {
                if ($sub['site_id'] === $siteId) {
                    // Find client and send
                    foreach ($this->clients as $client) {
                        if ($client->resourceId === $clientId) {
                            $client->send($msg);
                            break;
                        }
                    }
                    break;
                }
            }
        }
    }

    /**
     * Broadcast to segment subscribers
     */
    public function broadcastToSegment(int $siteId, string $segment, array $message)
    {
        $msg = json_encode($message, JSON_THROW_ON_ERROR);
        $targetKey = "{$siteId}:{$segment}";

        foreach ($this->subscriptions as $clientId => $subs) {
            if (isset($subs[$targetKey])) {
                // Find client and send
                foreach ($this->clients as $client) {
                    if ($client->resourceId === $clientId) {
                        $client->send($msg);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Send heartbeat to keep connections alive
     */
    public function sendHeartbeat()
    {
        $now = time();

        if ($now - $this->lastHeartbeat >= $this->heartbeatInterval) {
            $heartbeat = [
                'type' => 'heartbeat',
                'timestamp' => $now,
                'clients' => $this->clients->count(),
            ];

            $this->broadcast($heartbeat);
            $this->lastHeartbeat = $now;

            error_log("[WebSocket] Heartbeat sent to " . $this->clients->count() . " clients");
        }
    }

    /**
     * Get server statistics
     */
    public function getStatistics(): array
    {
        $siteStats = [];

        foreach ($this->subscriptions as $subs) {
            foreach ($subs as $sub) {
                $siteId = $sub['site_id'];
                $siteStats[$siteId] = ($siteStats[$siteId] ?? 0) + 1;
            }
        }

        return [
            'total_clients' => $this->clients->count(),
            'total_subscriptions' => count($this->subscriptions),
            'sites' => $siteStats,
            'timestamp' => time(),
        ];
    }
}
