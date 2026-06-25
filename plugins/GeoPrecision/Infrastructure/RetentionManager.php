<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Infrastructure;

use Piwik\Common;
use Piwik\Db;
use Piwik\Log;

final class RetentionManager
{
    // Retention periods in days
    private const RAW_DATA_RETENTION_DAYS = 30;
    private const AGGREGATE_DATA_RETENTION_DAYS = 365;

    /**
     * Purge old data according to retention policy.
     *
     * @param bool $dryRun If true, only report what would be deleted without actually deleting
     *
     * @return array<string, int>
     */
    public function purgeOldData(bool $dryRun = true): array
    {
        $rawDeleted = 0;
        $aggregateDeleted = 0;

        if ($this->hasRawDataTable()) {
            $rawDeleted = $this->purgeRawGeoData($dryRun);
        }

        if ($this->hasAggregateDataTable()) {
            $aggregateDeleted = $this->purgeAggregateGeoData($dryRun);
        }

        $message = sprintf(
            'GeoPrecision %s: Raw geo data records to delete: %d, Aggregate records to delete: %d',
            $dryRun ? '[DRY-RUN]' : '[EXECUTED]',
            $rawDeleted,
            $aggregateDeleted
        );

        Log::info($message);

        return [
            'rawDeleted' => $rawDeleted,
            'aggregateDeleted' => $aggregateDeleted,
            'dryRun' => $dryRun,
        ];
    }

    /**
     * Purge raw geo data older than retention period.
     *
     * @param bool $dryRun
     *
     * @return int Number of records to be deleted
     */
    private function purgeRawGeoData(bool $dryRun): int
    {
        $table = Common::prefixTable('plugin_geoprecision_raw');
        $cutoffDate = date('Y-m-d H:i:s', strtotime(sprintf('-%d days', self::RAW_DATA_RETENTION_DAYS)));

        $query = sprintf(
            'SELECT COUNT(*) FROM %s WHERE server_time < ?',
            $table
        );
        $countToDelete = (int) Db::fetchOne($query, [$cutoffDate]);

        if ($countToDelete > 0 && !$dryRun) {
            $deleteQuery = sprintf(
                'DELETE FROM %s WHERE server_time < ?',
                $table
            );
            Db::query($deleteQuery, [$cutoffDate]);
        }

        Log::info(sprintf(
            'GeoPrecision raw data: %d records older than %s (%d days) to delete',
            $countToDelete,
            $cutoffDate,
            self::RAW_DATA_RETENTION_DAYS
        ));

        return $countToDelete;
    }

    /**
     * Purge aggregate geo data older than retention period.
     *
     * @param bool $dryRun
     *
     * @return int Number of records to be deleted
     */
    private function purgeAggregateGeoData(bool $dryRun): int
    {
        $table = Common::prefixTable('plugin_geoprecision_aggregate');
        $cutoffDate = date('Y-m-d', strtotime(sprintf('-%d days', self::AGGREGATE_DATA_RETENTION_DAYS)));

        $query = sprintf(
            'SELECT COUNT(*) FROM %s WHERE period_date < ?',
            $table
        );
        $countToDelete = (int) Db::fetchOne($query, [$cutoffDate]);

        if ($countToDelete > 0 && !$dryRun) {
            $deleteQuery = sprintf(
                'DELETE FROM %s WHERE period_date < ?',
                $table
            );
            Db::query($deleteQuery, [$cutoffDate]);
        }

        Log::info(sprintf(
            'GeoPrecision aggregate data: %d records older than %s (%d days) to delete',
            $countToDelete,
            $cutoffDate,
            self::AGGREGATE_DATA_RETENTION_DAYS
        ));

        return $countToDelete;
    }

    /**
     * Check if raw data table exists.
     *
     * @return bool
     */
    private function hasRawDataTable(): bool
    {
        $table = Common::prefixTable('plugin_geoprecision_raw');

        try {
            $result = Db::fetchOne(sprintf('SHOW TABLES LIKE ?', $table), []);

            return !empty($result);
        } catch (\Exception $e) {
            Log::warning(sprintf('Failed to check raw data table: %s', $e->getMessage()));

            return false;
        }
    }

    /**
     * Check if aggregate data table exists.
     *
     * @return bool
     */
    private function hasAggregateDataTable(): bool
    {
        $table = Common::prefixTable('plugin_geoprecision_aggregate');

        try {
            $result = Db::fetchOne(sprintf('SHOW TABLES LIKE ?', $table), []);

            return !empty($result);
        } catch (\Exception $e) {
            Log::warning(sprintf('Failed to check aggregate data table: %s', $e->getMessage()));

            return false;
        }
    }
}
