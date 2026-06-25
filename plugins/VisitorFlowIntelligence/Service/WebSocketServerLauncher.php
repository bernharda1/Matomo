<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Ratchet\App;

/**
 * SB-020.2: WebSocketServerLauncher
 * 
 * Starts and manages the WebSocket server
 * Usage: bin/launcher.php start|stop|status
 */
class WebSocketServerLauncher
{
    private string $host;
    private int $port;
    private string $pidFile;

    /**
     * Constructor
     */
    public function __construct(
        string $host = '0.0.0.0',
        int $port = 8080,
        string $pidFile = '/tmp/visitorflow-websocket.pid'
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->pidFile = $pidFile;
    }

    /**
     * Start WebSocket server
     */
    public function start(): void
    {
        if ($this->isRunning()) {
            echo "WebSocket server is already running (PID: " . $this->getPid() . ")\n";
            return;
        }

        echo "Starting WebSocket server on {$this->host}:{$this->port}...\n";

        try {
            // Create Ratchet app
            $app = new App($this->host, $this->port);

            // Add WebSocket server route
            $app->route('/realtime', new WebSocketServer(), ['*']);

            // Store PID
            $pid = getmypid();
            file_put_contents($this->pidFile, $pid);

            echo "WebSocket server started successfully (PID: {$pid})\n";
            echo "Listening on ws://{$this->host}:{$this->port}/realtime\n";

            // Start the server
            $app->run();
        } catch (\Exception $e) {
            echo "Error starting server: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Stop WebSocket server
     */
    public function stop(): void
    {
        if (!$this->isRunning()) {
            echo "WebSocket server is not running\n";
            return;
        }

        $pid = $this->getPid();
        echo "Stopping WebSocket server (PID: {$pid})...\n";

        if (posix_kill($pid, SIGTERM)) {
            echo "Server stopped successfully\n";
            @unlink($this->pidFile);
        } else {
            echo "Failed to stop server\n";
            exit(1);
        }
    }

    /**
     * Get server status
     */
    public function status(): void
    {
        if ($this->isRunning()) {
            $pid = $this->getPid();
            echo "WebSocket server is running (PID: {$pid})\n";
            echo "Address: ws://{$this->host}:{$this->port}/realtime\n";
        } else {
            echo "WebSocket server is not running\n";
        }
    }

    /**
     * Check if server is running
     */
    private function isRunning(): bool
    {
        if (!file_exists($this->pidFile)) {
            return false;
        }

        $pid = (int)file_get_contents($this->pidFile);
        return posix_getpgid($pid) !== false;
    }

    /**
     * Get PID from file
     */
    private function getPid(): ?int
    {
        if (!file_exists($this->pidFile)) {
            return null;
        }

        return (int)file_get_contents($this->pidFile);
    }

    /**
     * Restart server
     */
    public function restart(): void
    {
        $this->stop();
        sleep(2);
        $this->start();
    }
}

// CLI interface
if (php_sapi_name() === 'cli') {
    $command = $GLOBALS['argv'][1] ?? 'status';
    $launcher = new WebSocketServerLauncher();

    switch ($command) {
        case 'start':
            $launcher->start();
            break;
        case 'stop':
            $launcher->stop();
            break;
        case 'restart':
            $launcher->restart();
            break;
        case 'status':
        default:
            $launcher->status();
            break;
    }
}
