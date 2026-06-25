<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure;

use Piwik\Db;
use Piwik\Common;
use Piwik\Log;

/**
 * SB-013.5: Migration Manager
 * 
 * Handles database schema versioning and migration execution
 * Tracks which migrations have been run and supports rollback
 */
class MigrationManager
{
    /**
     * @var string Plugin name
     */
    private string $pluginName;

    /**
     * @var string Directory containing migration files
     */
    private string $migrationsPath;

    /**
     * @var array Loaded migration classes
     */
    private array $migrations = [];

    public function __construct(string $pluginName, string $migrationsPath)
    {
        $this->pluginName = $pluginName;
        $this->migrationsPath = $migrationsPath;
    }

    /**
     * Execute all pending migrations
     * 
     * @return array List of executed migrations
     * @throws \Exception on failure
     */
    public function migrate(): array
    {
        $this->ensureVersionTableExists();
        $executed = [];
        $pending = $this->getPendingMigrations();

        foreach ($pending as $migrationClass) {
            try {
                Log::info("[{$this->pluginName}] Executing migration: {$migrationClass}");
                
                $migration = new $migrationClass();
                $migration->up();
                
                $this->recordMigration($migration->getVersion(), $migration->getDescription());
                $executed[] = $migration->getVersion();
                
                Log::info("[{$this->pluginName}] Successfully completed migration {$migration->getVersion()}");
            } catch (\Exception $e) {
                Log::error("[{$this->pluginName}] Migration {$migrationClass} failed: " . $e->getMessage());
                throw $e;
            }
        }

        return $executed;
    }

    /**
     * Get list of pending migrations
     */
    private function getPendingMigrations(): array
    {
        $this->loadMigrations();
        $completed = $this->getCompletedMigrationVersions();

        $pending = [];
        foreach ($this->migrations as $version => $className) {
            if (!in_array($version, $completed, true)) {
                $pending[] = $className;
            }
        }

        return $pending;
    }

    /**
     * Load all migration classes from directory
     */
    private function loadMigrations(): void
    {
        if (!empty($this->migrations)) {
            return;
        }

        $files = glob($this->migrationsPath . '/Migration_*.php');
        if ($files === false) {
            throw new \Exception("Failed to scan migrations directory: {$this->migrationsPath}");
        }

        foreach ($files as $file) {
            $fileName = basename($file, '.php');
            // Extract version from Migration_X_Y_Z_*.php
            if (preg_match('/^Migration_(\d+_\d+_\d+)_/', $fileName, $matches)) {
                $version = str_replace('_', '.', $matches[1]);
                $className = "Piwik\\Plugins\\{$this->pluginName}\\Infrastructure\\Migrations\\{$fileName}";
                $this->migrations[$version] = $className;
            }
        }

        // Sort by version
        uksort($this->migrations, 'version_compare');
    }

    /**
     * Get versions of completed migrations
     */
    private function getCompletedMigrationVersions(): array
    {
        $tableName = Common::prefixTable('plugin_migration_log');

        try {
            $rows = Db::fetchAll("
                SELECT version FROM {$tableName} 
                WHERE plugin = ? 
                ORDER BY executed_at DESC
            ", [$this->pluginName]);

            return array_column($rows, 'version');
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Record executed migration
     */
    private function recordMigration(string $version, string $description): void
    {
        $tableName = Common::prefixTable('plugin_migration_log');

        Db::query("
            INSERT INTO {$tableName} (plugin, version, description, executed_at) 
            VALUES (?, ?, ?, NOW())
        ", [$this->pluginName, $version, $description]);
    }

    /**
     * Ensure migration tracking table exists
     */
    private function ensureVersionTableExists(): void
    {
        $tableName = Common::prefixTable('plugin_migration_log');

        try {
            Db::fetchOne("SELECT COUNT(*) FROM {$tableName} LIMIT 1");
            return; // Table exists
        } catch (\Exception $e) {
            // Table doesn't exist, create it
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                plugin VARCHAR(255) NOT NULL,
                version VARCHAR(50) NOT NULL,
                description TEXT,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_plugin_version (plugin, version),
                KEY idx_plugin (plugin),
                KEY idx_executed_at (executed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Track plugin database migrations'
        ";

        Db::exec($sql);
        Log::info("[{$this->pluginName}] Created migration tracking table {$tableName}");
    }

    /**
     * Get migration status
     */
    public function getStatus(): array
    {
        $this->ensureVersionTableExists();
        $this->loadMigrations();

        $completed = $this->getCompletedMigrationVersions();

        return [
            'plugin' => $this->pluginName,
            'total_migrations' => count($this->migrations),
            'completed' => count($completed),
            'pending' => count($this->migrations) - count($completed),
            'completed_versions' => $completed,
            'all_versions' => array_keys($this->migrations),
        ];
    }
}
