<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * SB-013.1: Create plugin_visitorflow_raw table
 * 
 * Stores raw visitor flow data for aggregation into paths/transitions/dropoffs
 * Schema: idvisit, path_hash, depth, steps_json, server_time
 */
class Migration_1_0_0_CreateVisitorFlowRawTable extends Migration
{
    protected string $version = '1.0.0';
    protected string $description = 'Create plugin_visitorflow_raw table for raw path data storage';

    public function up(): void
    {
        $tableName = Common::prefixTable('plugin_visitorflow_raw');

        if ($this->tableExists($tableName)) {
            $this->log("Table {$tableName} already exists, skipping creation");
            return;
        }

        $sql = "
            CREATE TABLE {$tableName} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                idsite INT UNSIGNED NOT NULL,
                idvisit BIGINT UNSIGNED NOT NULL,
                idvisitor BINARY(8),
                path_hash VARCHAR(32) COMMENT 'MD5 hash of path sequence for deduplication',
                depth TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Number of steps in path',
                steps_json MEDIUMTEXT COMMENT 'JSON array of steps: [{id, label, url, actionName}]',
                transition_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of transitions in path',
                visit_duration INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total visit duration in seconds',
                server_time DATETIME NOT NULL COMMENT 'When this path was recorded',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_idsite_server_time (idsite, server_time),
                KEY idx_idvisit (idvisit),
                KEY idx_path_hash (path_hash),
                KEY idx_server_time (server_time),
                CONSTRAINT fk_visitorflow_raw_log_visit 
                    FOREIGN KEY (idvisit) REFERENCES " . Common::prefixTable('log_visit') . "(idvisit)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Raw visitor flow paths for aggregation (SB-013.1)'
        ";

        Db::exec($sql);
        $this->log("Successfully created table {$tableName}");

        // Create partition for monthly data retention
        $this->createMonthlyPartition($tableName);

        // Verify table creation
        if (!$this->tableExists($tableName)) {
            throw new \Exception("Failed to create table {$tableName}");
        }
    }

    public function down(): void
    {
        $tableName = Common::prefixTable('plugin_visitorflow_raw');

        if (!$this->tableExists($tableName)) {
            $this->log("Table {$tableName} does not exist, skipping drop");
            return;
        }

        Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Successfully dropped table {$tableName}");
    }

    /**
     * Create monthly partition for retention management
     * 
     * Enables efficient purging of old data (30-day retention)
     */
    private function createMonthlyPartition(string $tableName): void
    {
        try {
            // Check if table supports partitioning
            $result = Db::fetchOne("SELECT TABLE_CATALOG FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_NAME = ? AND PARTITION_METHOD IS NOT NULL", 
                [substr($tableName, strlen(Common::prefixTable('')))]
            );

            // For now, we'll handle partitioning in a future migration (SB-013.4)
            // to keep this migration focused on table creation
            $this->log("Partitioning strategy deferred to SB-013.4");
        } catch (\Exception $e) {
            $this->log("Partition creation skipped: " . $e->getMessage());
        }
    }
}
