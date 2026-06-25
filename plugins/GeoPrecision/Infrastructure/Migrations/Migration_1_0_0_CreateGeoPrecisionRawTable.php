<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * SB-013.2: Create plugin_geoprecision_raw table
 * 
 * Stores raw geographic precision data with confidence scoring
 * Schema: idvisit, country, region, city, lat, lon, confidence_score, server_time
 */
class Migration_1_0_0_CreateGeoPrecisionRawTable extends Migration
{
    protected string $version = '1.0.0';
    protected string $description = 'Create plugin_geoprecision_raw table for geographic precision data';

    public function up(): void
    {
        $tableName = Common::prefixTable('plugin_geoprecision_raw');

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
                country_code CHAR(2) COMMENT 'ISO 3166-1 alpha-2 country code',
                country_name VARCHAR(100),
                region_code VARCHAR(10) COMMENT 'ISO 3166-2 region code',
                region_name VARCHAR(100),
                city_name VARCHAR(100),
                latitude DECIMAL(9, 6) COMMENT 'Geographic latitude (-90 to 90)',
                longitude DECIMAL(9, 6) COMMENT 'Geographic longitude (-180 to 180)',
                confidence_score TINYINT UNSIGNED NOT NULL COMMENT '0-100 confidence level',
                precision_level ENUM('unknown', 'country', 'region', 'city', 'approx', 'exact') NOT NULL,
                confidence_level ENUM('low', 'medium', 'high') NOT NULL,
                source_type VARCHAR(50) COMMENT 'ip, override, consent_precise, etc.',
                has_consent_precise BOOLEAN DEFAULT FALSE,
                server_time DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_idsite_server_time (idsite, server_time),
                KEY idx_country (country_code, server_time),
                KEY idx_idvisit (idvisit),
                KEY idx_confidence_score (confidence_score),
                KEY idx_server_time (server_time),
                CONSTRAINT fk_geoprecision_raw_log_visit
                    FOREIGN KEY (idvisit) REFERENCES " . Common::prefixTable('log_visit') . "(idvisit)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Raw geographic precision data for confidence analysis (SB-013.2)'
        ";

        Db::exec($sql);
        $this->log("Successfully created table {$tableName}");

        if (!$this->tableExists($tableName)) {
            throw new \Exception("Failed to create table {$tableName}");
        }
    }

    public function down(): void
    {
        $tableName = Common::prefixTable('plugin_geoprecision_raw');

        if (!$this->tableExists($tableName)) {
            $this->log("Table {$tableName} does not exist, skipping drop");
            return;
        }

        Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Successfully dropped table {$tableName}");
    }
}
