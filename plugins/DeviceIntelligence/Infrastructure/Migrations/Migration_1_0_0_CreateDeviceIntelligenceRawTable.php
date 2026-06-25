<?php

declare(strict_types=1);

namespace Piwik\Plugins\DeviceIntelligence\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * SB-013.3: Create plugin_deviceintelligence_raw table
 * 
 * Stores raw device data with Client Hints and quality metrics
 * Schema: idvisit, device_type, brand, model, osName, osVersion, browserName, browserVersion, server_time
 */
class Migration_1_0_0_CreateDeviceIntelligenceRawTable extends Migration
{
    protected string $version = '1.0.0';
    protected string $description = 'Create plugin_deviceintelligence_raw table for device intelligence data';

    public function up(): void
    {
        $tableName = Common::prefixTable('plugin_deviceintelligence_raw');

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
                device_type VARCHAR(50) COMMENT 'desktop, mobile, tablet, unknown',
                brand VARCHAR(100) COMMENT 'Device brand (Apple, Samsung, etc.)',
                model VARCHAR(100) COMMENT 'Device model',
                os_name VARCHAR(100) COMMENT 'Operating system name',
                os_version VARCHAR(50) COMMENT 'OS version',
                browser_name VARCHAR(100) COMMENT 'Browser name',
                browser_version VARCHAR(50) COMMENT 'Browser version',
                user_agent VARCHAR(500) COMMENT 'User-Agent header',
                client_hints_raw JSON COMMENT 'Raw User-Agent Client Hints JSON',
                client_hints_present BOOLEAN DEFAULT FALSE,
                resolution VARCHAR(20) COMMENT 'Screen resolution (e.g., 1920x1080)',
                unknown_rate_pct TINYINT UNSIGNED DEFAULT 0 COMMENT 'Estimated unknown-rate % for this device',
                server_time DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_idsite_server_time (idsite, server_time),
                KEY idx_device_type (device_type, server_time),
                KEY idx_brand (brand, server_time),
                KEY idx_idvisit (idvisit),
                KEY idx_server_time (server_time),
                CONSTRAINT fk_deviceintelligence_raw_log_visit
                    FOREIGN KEY (idvisit) REFERENCES " . Common::prefixTable('log_visit') . "(idvisit)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Raw device intelligence data with Client Hints (SB-013.3)'
        ";

        Db::exec($sql);
        $this->log("Successfully created table {$tableName}");

        if (!$this->tableExists($tableName)) {
            throw new \Exception("Failed to create table {$tableName}");
        }
    }

    public function down(): void
    {
        $tableName = Common::prefixTable('plugin_deviceintelligence_raw');

        if (!$this->tableExists($tableName)) {
            $this->log("Table {$tableName} does not exist, skipping drop");
            return;
        }

        Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Successfully dropped table {$tableName}");
    }
}
