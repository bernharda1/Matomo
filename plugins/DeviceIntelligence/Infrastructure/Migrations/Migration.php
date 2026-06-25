<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * Base migration class for DeviceIntelligence plugin
 */
abstract class Migration
{
    protected string $version;
    protected string $description;

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    abstract public function up(): void;

    public function down(): void
    {
        throw new \Exception("Rollback not implemented for migration {$this->version}");
    }

    protected function getTablePrefix(): string
    {
        return Common::prefixTable('');
    }

    protected function tableExists(string $tableName): bool
    {
        $tables = Db::fetchAll("SHOW TABLES LIKE '{$tableName}'");
        return !empty($tables);
    }

    protected function columnExists(string $tableName, string $columnName): bool
    {
        $columns = Db::fetchAll("SHOW COLUMNS FROM {$tableName} LIKE '{$columnName}'");
        return !empty($columns);
    }

    protected function log(string $message): void
    {
        \Piwik\Log::info("[DeviceIntelligence Migration {$this->version}] {$message}");
    }
}
