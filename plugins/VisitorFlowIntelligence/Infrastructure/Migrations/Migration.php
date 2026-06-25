<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * Base migration class for VisitorFlowIntelligence plugin
 * 
 * Manages database schema changes with version control and rollback capability
 */
abstract class Migration
{
    /**
     * @var string Migration version identifier (e.g., '1.0.0')
     */
    protected string $version;

    /**
     * @var string Human-readable migration description
     */
    protected string $description;

    /**
     * Get migration version
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get migration description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Execute migration upgrade
     * 
     * @throws \Exception on failure
     */
    abstract public function up(): void;

    /**
     * Rollback migration (optional)
     * 
     * @throws \Exception on failure
     */
    public function down(): void
    {
        // Override in subclass if rollback needed
        throw new \Exception("Rollback not implemented for migration {$this->version}");
    }

    /**
     * Get table prefix
     */
    protected function getTablePrefix(): string
    {
        return Common::prefixTable('');
    }

    /**
     * Check if table exists
     */
    protected function tableExists(string $tableName): bool
    {
        $tables = Db::fetchAll("SHOW TABLES LIKE '{$tableName}'");
        return !empty($tables);
    }

    /**
     * Check if column exists in table
     */
    protected function columnExists(string $tableName, string $columnName): bool
    {
        $columns = Db::fetchAll("SHOW COLUMNS FROM {$tableName} LIKE '{$columnName}'");
        return !empty($columns);
    }

    /**
     * Log migration action
     */
    protected function log(string $message): void
    {
        \Piwik\Log::info("[VisitorFlowIntelligence Migration {$this->version}] {$message}");
    }
}
