<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\Migrations;

/**
 * SB-014.4: Create VisitorFlow Archive Table
 * 
 * Stores aggregated (denormalized) path data by period for fast reporting
 * Complements Matomo's archive_numeric/archive_blob with domain-specific schema
 * 
 * Columns:
 * - idarchive: Foreign key to archive entry
 * - idsite: Site ID
 * - period: 'day', 'week', 'month', 'year'
 * - date_start: Period start date
 * - date_end: Period end date
 * - top_paths: JSON array of top 100 paths with metrics
 * - transitions_total: Total transition count for period
 * - dropoffs: JSON array of drop-off rates by depth
 * - archived_at: Timestamp when archived
 */
class Migration_1_0_1_CreateVisitorFlowAggregateTable extends Migration
{
    public function up(): void
    {
        $tableName = \Piwik\Common::prefixTable('period_visitorflow_aggregate');
        
        if ($this->tableExists($tableName)) {
            $this->log('Table already exists: ' . $tableName);
            return;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS {$tableName} (
                idarchive INT UNSIGNED NOT NULL,
                idsite INT UNSIGNED NOT NULL,
                period VARCHAR(10) NOT NULL COMMENT 'day|week|month|year',
                date_start DATE NOT NULL,
                date_end DATE NOT NULL,
                top_paths JSON COMMENT 'Top paths: [{path_hash, visits, avg_depth, share}]',
                transitions_total INT UNSIGNED DEFAULT 0,
                dropoffs JSON COMMENT 'Dropoff rates by depth: [{depth, count, rate}]',
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (idarchive, idsite, period),
                KEY idx_idsite_period (idsite, period),
                KEY idx_date_range (date_start, date_end)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='VisitorFlowIntelligence aggregated metrics by period'
        ";

        \Piwik\Db::exec($sql);
        
        $this->log("Created archive table: {$tableName}");
        
        // Add indexes for common queries
        $this->addIndexIfNotExists(
            $tableName,
            'idx_idarchive',
            ['idarchive']
        );
    }

    public function down(): void
    {
        $tableName = \Piwik\Common::prefixTable('period_visitorflow_aggregate');
        
        if (!$this->tableExists($tableName)) {
            $this->log('Table does not exist, skipping drop: ' . $tableName);
            return;
        }

        \Piwik\Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Dropped archive table: {$tableName}");
    }

    private function addIndexIfNotExists(string $tableName, string $indexName, array $columns): void
    {
        $sql = "
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_NAME = ? AND INDEX_NAME = ?
        ";
        $result = \Piwik\Db::fetchOne($sql, [
            str_replace(\Piwik\Common::prefixTable(''), '', $tableName),
            $indexName
        ]);

        if (!$result) {
            $columnList = implode(', ', $columns);
            \Piwik\Db::exec("ALTER TABLE {$tableName} ADD INDEX {$indexName} ({$columnList})");
            $this->log("Added index {$indexName} on {$tableName}");
        }
    }
}
