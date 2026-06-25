<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Infrastructure\Migrations;

/**
 * SB-014.4: Create GeoPrecision Archive Table
 * 
 * Stores aggregated geographic precision data by period
 * Denormalized schema for fast country/region/city reporting
 */
class Migration_1_0_1_CreateGeoPrecisionAggregateTable extends Migration
{
    public function up(): void
    {
        $tableName = \Piwik\Common::prefixTable('period_geoprecision_aggregate');
        
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
                precision_distribution JSON COMMENT 'Confidence levels: [{level, count, avg_score, share}]',
                geographic_coverage JSON COMMENT 'Top 50 countries: [{country, city_rate, region_rate, known_rate}]',
                precision_breakdown JSON COMMENT 'Precision types: [{precision, count, rate}]',
                avg_confidence_score DECIMAL(5, 2),
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (idarchive, idsite, period),
                KEY idx_idsite_period (idsite, period),
                KEY idx_date_range (date_start, date_end)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='GeoPrecision aggregated metrics by period'
        ";

        \Piwik\Db::exec($sql);
        $this->log("Created archive table: {$tableName}");
    }

    public function down(): void
    {
        $tableName = \Piwik\Common::prefixTable('period_geoprecision_aggregate');
        
        if (!$this->tableExists($tableName)) {
            $this->log('Table does not exist, skipping drop: ' . $tableName);
            return;
        }

        \Piwik\Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Dropped archive table: {$tableName}");
    }
}
