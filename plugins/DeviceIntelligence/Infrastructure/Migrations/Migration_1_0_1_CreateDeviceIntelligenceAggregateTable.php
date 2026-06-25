<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence\Infrastructure\Migrations;

/**
 * SB-014.4: Create DeviceIntelligence Archive Table
 * 
 * Stores aggregated device quality metrics by period
 * Includes unknown rates, top devices, and Client Hints adoption metrics
 */
class Migration_1_0_1_CreateDeviceIntelligenceAggregateTable extends Migration
{
    public function up(): void
    {
        $tableName = \Piwik\Common::prefixTable('period_deviceintelligence_aggregate');
        
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
                unknown_rates JSON COMMENT 'By device_type: [{type, device_unknown, brand_unknown, os_unknown, browser_unknown}]',
                top_devices JSON COMMENT 'Top 20 brands: [{brand, model, count, share}]',
                client_hints_adoption DECIMAL(5, 2) COMMENT 'Percentage with client_hints_present=TRUE',
                top_brands JSON COMMENT 'Top 20 brands by visits: [{brand, count, share}]',
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (idarchive, idsite, period),
                KEY idx_idsite_period (idsite, period),
                KEY idx_date_range (date_start, date_end)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='DeviceIntelligence aggregated metrics by period'
        ";

        \Piwik\Db::exec($sql);
        $this->log("Created archive table: {$tableName}");
    }

    public function down(): void
    {
        $tableName = \Piwik\Common::prefixTable('period_deviceintelligence_aggregate');
        
        if (!$this->tableExists($tableName)) {
            $this->log('Table does not exist, skipping drop: ' . $tableName);
            return;
        }

        \Piwik\Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Dropped archive table: {$tableName}");
    }
}
