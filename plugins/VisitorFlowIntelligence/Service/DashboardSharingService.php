<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Db;
use Piwik\Common;

/**
 * DashboardSharingService - Advanced dashboard sharing with permission levels
 *
 * Manages dashboard sharing with granular permission control:
 * - View-only permissions
 * - Edit permissions
 * - Admin permissions (can share with others)
 * - Share expiration dates
 * - Revoke sharing
 */
class DashboardSharingService
{
    private const TABLE_SHARES = 'visitor_flow_dashboard_shares';

    /**
     * Share dashboard with user(s) with specific permissions
     *
     * @param int $dashboardId Dashboard to share
     * @param int|array $userIds User ID(s) to share with
     * @param string $permission Permission level: 'view', 'edit', 'admin'
     * @param int|null $expiresAt Optional expiration timestamp
     * @return array Share records created
     */
    public function shareDashboard(
        int $dashboardId,
        $userIds,
        string $permission = 'view',
        ?int $expiresAt = null
    ): array {
        $userIds = is_array($userIds) ? $userIds : [$userIds];
        $shares = [];

        foreach ($userIds as $userId) {
            $table = Common::prefixTable(self::TABLE_SHARES);

            Db::query(
                "INSERT INTO `$table` (dashboard_id, owner_id, recipient_id, permission, expires_at, created_at, is_active)
                 VALUES (?, ?, ?, ?, ?, NOW(), 1)
                 ON DUPLICATE KEY UPDATE permission = ?, is_active = 1, updated_at = NOW()",
                [$dashboardId, Piwik::getCurrentUserLogin(), $userId, $permission, $expiresAt, $permission]
            );

            $shares[] = [
                'dashboard_id' => $dashboardId,
                'recipient_id' => $userId,
                'permission' => $permission,
                'expires_at' => $expiresAt
            ];
        }

        return $shares;
    }

    /**
     * Get dashboard shares by dashboard ID
     *
     * @param int $dashboardId Dashboard ID
     * @return array List of shares
     */
    public function getDashboardShares(int $dashboardId): array
    {
        $table = Common::prefixTable(self::TABLE_SHARES);

        return Db::fetchAll(
            "SELECT * FROM `$table` WHERE dashboard_id = ? AND is_active = 1 ORDER BY created_at DESC",
            [$dashboardId]
        );
    }

    /**
     * Get shared dashboards for user
     *
     * @param int $userId User ID
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array Shared dashboards
     */
    public function getSharedDashboards(int $userId, int $limit = 50, int $offset = 0): array
    {
        $dashboardTable = Common::prefixTable('visitor_flow_dashboards');
        $shareTable = Common::prefixTable(self::TABLE_SHARES);

        return Db::fetchAll(
            "SELECT d.*, s.permission, s.owner_id, s.expires_at
             FROM `$dashboardTable` d
             INNER JOIN `$shareTable` s ON d.id = s.dashboard_id
             WHERE s.recipient_id = ? AND s.is_active = 1
             AND (s.expires_at IS NULL OR s.expires_at > NOW())
             ORDER BY s.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Get user's permission level for a dashboard
     *
     * @param int $dashboardId Dashboard ID
     * @param int $userId User ID
     * @return string|null Permission level or null if not shared
     */
    public function getUserPermission(int $dashboardId, int $userId): ?string
    {
        $table = Common::prefixTable(self::TABLE_SHARES);

        $result = Db::fetchRow(
            "SELECT permission FROM `$table` 
             WHERE dashboard_id = ? AND recipient_id = ? AND is_active = 1
             AND (expires_at IS NULL OR expires_at > NOW())",
            [$dashboardId, $userId]
        );

        return $result['permission'] ?? null;
    }

    /**
     * Update share permission
     *
     * @param int $dashboardId Dashboard ID
     * @param int $userId Recipient user ID
     * @param string $permission New permission level
     * @return bool Success status
     */
    public function updateSharePermission(int $dashboardId, int $userId, string $permission): bool
    {
        $table = Common::prefixTable(self::TABLE_SHARES);

        Db::query(
            "UPDATE `$table` SET permission = ?, updated_at = NOW() 
             WHERE dashboard_id = ? AND recipient_id = ? AND is_active = 1",
            [$permission, $dashboardId, $userId]
        );

        return true;
    }

    /**
     * Revoke dashboard share
     *
     * @param int $dashboardId Dashboard ID
     * @param int $userId Recipient user ID
     * @return bool Success status
     */
    public function revokeDashboardShare(int $dashboardId, int $userId): bool
    {
        $table = Common::prefixTable(self::TABLE_SHARES);

        Db::query(
            "UPDATE `$table` SET is_active = 0, updated_at = NOW()
             WHERE dashboard_id = ? AND recipient_id = ? AND is_active = 1",
            [$dashboardId, $userId]
        );

        return true;
    }

    /**
     * Revoke all shares for a dashboard
     *
     * @param int $dashboardId Dashboard ID
     * @return int Number of shares revoked
     */
    public function revokeAllShares(int $dashboardId): int
    {
        $table = Common::prefixTable(self::TABLE_SHARES);

        Db::query(
            "UPDATE `$table` SET is_active = 0, updated_at = NOW()
             WHERE dashboard_id = ? AND is_active = 1",
            [$dashboardId]
        );

        return Db::rowCount();
    }

    /**
     * Check if user can edit dashboard (via share or ownership)
     *
     * @param int $dashboardId Dashboard ID
     * @param int $userId User ID
     * @return bool Can edit
     */
    public function canUserEdit(int $dashboardId, int $userId): bool
    {
        $dashboardTable = Common::prefixTable('visitor_flow_dashboards');

        // Check if owner
        $dashboard = Db::fetchRow(
            "SELECT id FROM `$dashboardTable` WHERE id = ? AND user_id = ?",
            [$dashboardId, $userId]
        );

        if ($dashboard) {
            return true;
        }

        // Check if has edit permission
        $permission = $this->getUserPermission($dashboardId, $userId);
        return in_array($permission, ['edit', 'admin']);
    }

    /**
     * Create database table for shares
     */
    public static function createTable(): void
    {
        $table = Common::prefixTable(self::TABLE_SHARES);

        Db::query("
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `dashboard_id` INT NOT NULL,
                `owner_id` VARCHAR(255) NOT NULL,
                `recipient_id` INT NOT NULL,
                `permission` VARCHAR(50) NOT NULL DEFAULT 'view',
                `expires_at` DATETIME,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                UNIQUE KEY uk_dashboard_recipient (dashboard_id, recipient_id),
                FOREIGN KEY (dashboard_id) REFERENCES " . Common::prefixTable('visitor_flow_dashboards') . "(`id`) ON DELETE CASCADE,
                INDEX idx_recipient_id (recipient_id),
                INDEX idx_owner_id (owner_id),
                INDEX idx_expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
