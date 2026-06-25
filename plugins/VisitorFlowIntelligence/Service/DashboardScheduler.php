<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Db;
use Piwik\Common;

/**
 * DashboardScheduler - Dashboard refresh scheduling
 *
 * Manages scheduled dashboard refreshes:
 * - Configure auto-refresh intervals
 * - Schedule refresh jobs
 * - Track last refresh times
 * - Execute scheduled refreshes
 */
class DashboardScheduler
{
    private const TABLE_SCHEDULES = 'visitor_flow_dashboard_schedules';

    /**
     * Create or update dashboard refresh schedule
     *
     * @param int $dashboardId Dashboard ID
     * @param string $frequency Frequency: 'hourly', 'daily', 'weekly', 'monthly'
     * @param string $time Time in HH:MM format (for daily/weekly/monthly)
     * @param int|null $dayOfWeek Day of week (0-6) for weekly schedules
     * @param int|null $dayOfMonth Day of month (1-31) for monthly schedules
     * @return bool Success
     */
    public function scheduleRefresh(
        int $dashboardId,
        string $frequency,
        string $time = '00:00',
        ?int $dayOfWeek = null,
        ?int $dayOfMonth = null
    ): bool {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);

        Db::query(
            "INSERT INTO `$table` (dashboard_id, frequency, scheduled_time, day_of_week, day_of_month, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE frequency = ?, scheduled_time = ?, day_of_week = ?, day_of_month = ?, is_active = 1, updated_at = NOW()",
            [
                $dashboardId, $frequency, $time, $dayOfWeek, $dayOfMonth,
                $frequency, $time, $dayOfWeek, $dayOfMonth
            ]
        );

        return true;
    }

    /**
     * Get dashboard schedule
     *
     * @param int $dashboardId Dashboard ID
     * @return array|null Schedule data
     */
    public function getSchedule(int $dashboardId): ?array
    {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);

        return Db::fetchRow(
            "SELECT * FROM `$table` WHERE dashboard_id = ? AND is_active = 1",
            [$dashboardId]
        );
    }

    /**
     * Get all active schedules due for execution
     *
     * @return array Schedules ready to run
     */
    public function getSchedulesDueForExecution(): array
    {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);
        $now = new \DateTime();

        $results = Db::fetchAll(
            "SELECT * FROM `$table`
             WHERE is_active = 1
             AND (last_run_at IS NULL OR DATE_ADD(last_run_at, INTERVAL frequency HOUR) <= NOW())",
            []
        );

        $dueSchedules = [];

        foreach ($results as $schedule) {
            if ($this->isScheduleDueToRun($schedule, $now)) {
                $dueSchedules[] = $schedule;
            }
        }

        return $dueSchedules;
    }

    /**
     * Check if a schedule is due to run based on frequency and time
     *
     * @param array $schedule Schedule data
     * @param \DateTime $now Current time
     * @return bool Is due
     */
    private function isScheduleDueToRun(array $schedule, \DateTime $now): bool
    {
        $frequency = $schedule['frequency'];
        $scheduledTime = $schedule['scheduled_time'];
        $lastRunAt = $schedule['last_run_at'] ? new \DateTime($schedule['last_run_at']) : null;

        [$scheduledHour, $scheduledMinute] = explode(':', $scheduledTime);

        switch ($frequency) {
            case 'hourly':
                return $lastRunAt === null || $now->getTimestamp() - $lastRunAt->getTimestamp() >= 3600;

            case 'daily':
                if ($lastRunAt === null) {
                    return true;
                }
                $nowTime = $now->format('Hi');
                $scheduledHourMin = $scheduledHour . $scheduledMinute;
                return ($nowTime >= $scheduledHourMin) && ($lastRunAt->format('Y-m-d') !== $now->format('Y-m-d'));

            case 'weekly':
                $dayOfWeek = $schedule['day_of_week'] ?? 0;
                if ((int)$now->format('w') !== $dayOfWeek) {
                    return false;
                }
                $nowTime = $now->format('Hi');
                $scheduledHourMin = $scheduledHour . $scheduledMinute;
                return ($nowTime >= $scheduledHourMin) && ($lastRunAt === null || $lastRunAt->format('W') !== $now->format('W'));

            case 'monthly':
                $dayOfMonth = $schedule['day_of_month'] ?? 1;
                if ((int)$now->format('d') !== $dayOfMonth) {
                    return false;
                }
                $nowTime = $now->format('Hi');
                $scheduledHourMin = $scheduledHour . $scheduledMinute;
                return ($nowTime >= $scheduledHourMin) && ($lastRunAt === null || $lastRunAt->format('Y-m') !== $now->format('Y-m'));

            default:
                return false;
        }
    }

    /**
     * Record schedule execution
     *
     * @param int $dashboardId Dashboard ID
     * @param bool $success Execution success
     * @param string $message Optional message
     * @return void
     */
    public function recordExecution(int $dashboardId, bool $success = true, string $message = ''): void
    {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);

        Db::query(
            "UPDATE `$table` SET last_run_at = NOW(), last_run_success = ?, last_run_message = ?, run_count = run_count + 1
             WHERE dashboard_id = ?",
            [$success ? 1 : 0, $message, $dashboardId]
        );
    }

    /**
     * Disable schedule
     *
     * @param int $dashboardId Dashboard ID
     * @return bool Success
     */
    public function disableSchedule(int $dashboardId): bool
    {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);

        Db::query(
            "UPDATE `$table` SET is_active = 0, updated_at = NOW() WHERE dashboard_id = ?",
            [$dashboardId]
        );

        return true;
    }

    /**
     * Get schedule statistics
     *
     * @param int $dashboardId Dashboard ID
     * @return array|null Statistics
     */
    public function getScheduleStats(int $dashboardId): ?array
    {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);

        return Db::fetchRow(
            "SELECT id, dashboard_id, frequency, scheduled_time, run_count, last_run_at, last_run_success, is_active
             FROM `$table`
             WHERE dashboard_id = ?",
            [$dashboardId]
        );
    }

    /**
     * Get failed schedules for retry
     *
     * @param int $limit Result limit
     * @return array Failed schedules
     */
    public function getFailedSchedules(int $limit = 50): array
    {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);

        return Db::fetchAll(
            "SELECT * FROM `$table`
             WHERE is_active = 1 AND last_run_success = 0
             ORDER BY last_run_at ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Create database table for schedules
     */
    public static function createTable(): void
    {
        $table = Common::prefixTable(self::TABLE_SCHEDULES);

        Db::query("
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `dashboard_id` INT UNIQUE NOT NULL,
                `frequency` VARCHAR(50) NOT NULL,
                `scheduled_time` VARCHAR(5) DEFAULT '00:00',
                `day_of_week` INT,
                `day_of_month` INT,
                `is_active` TINYINT(1) DEFAULT 1,
                `last_run_at` DATETIME,
                `last_run_success` TINYINT(1) DEFAULT 0,
                `last_run_message` TEXT,
                `run_count` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME,
                FOREIGN KEY (dashboard_id) REFERENCES " . Common::prefixTable('visitor_flow_dashboards') . "(`id`) ON DELETE CASCADE,
                INDEX idx_frequency (frequency),
                INDEX idx_is_active (is_active),
                INDEX idx_last_run_at (last_run_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
