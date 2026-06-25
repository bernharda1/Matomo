<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Db;
use Piwik\Common;

/**
 * DashboardTemplateManager - Custom template creation and management
 *
 * Manages user-created dashboard templates:
 * - Save dashboards as templates
 * - Manage template metadata
 * - Share templates with other users
 * - Delete templates
 * - List available templates
 */
class DashboardTemplateManager
{
    private const TABLE_TEMPLATES = 'visitor_flow_dashboard_templates';

    /**
     * Create template from existing dashboard
     *
     * @param int $dashboardId Source dashboard
     * @param string $templateName Template name
     * @param string $description Template description
     * @param array $metadata Template metadata
     * @return int Template ID
     */
    public function createTemplateFromDashboard(
        int $dashboardId,
        string $templateName,
        string $description = '',
        array $metadata = []
    ): int {
        $dashboardService = new DashboardService();
        $dashboard = $dashboardService->getDashboard($dashboardId);

        if (!$dashboard) {
            throw new \Exception('Dashboard not found');
        }

        $table = Common::prefixTable(self::TABLE_TEMPLATES);
        $widgets = $dashboard['widgets'] ?? [];

        Db::query(
            "INSERT INTO `$table` (template_id, name, description, creator_id, widgets_config, metadata, is_public, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW())",
            [
                $this->generateTemplateId(),
                $templateName,
                $description,
                Piwik::getCurrentUserLogin(),
                json_encode($widgets),
                json_encode($metadata)
            ]
        );

        return (int)Db::lastInsertId();
    }

    /**
     * Get template by ID
     *
     * @param int|string $templateId Template ID
     * @return array|null Template data
     */
    public function getTemplate($templateId): ?array
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        return Db::fetchRow(
            "SELECT * FROM `$table` WHERE template_id = ? OR id = ?",
            [$templateId, $templateId]
        );
    }

    /**
     * Get user's custom templates
     *
     * @param int $userId User ID
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array Templates
     */
    public function getUserTemplates(int $userId, int $limit = 50, int $offset = 0): array
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        return Db::fetchAll(
            "SELECT id, template_id, name, description, widgets_config, metadata, is_public, created_at, downloads
             FROM `$table`
             WHERE creator_id = (SELECT login FROM " . Common::prefixTable('user') . " WHERE iduser = ?)
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Get public templates (for discovery)
     *
     * @param int $limit Result limit
     * @param int $offset Result offset
     * @return array Public templates
     */
    public function getPublicTemplates(int $limit = 50, int $offset = 0): array
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        return Db::fetchAll(
            "SELECT id, template_id, name, description, creator_id, widgets_config, metadata, created_at, downloads
             FROM `$table`
             WHERE is_public = 1
             ORDER BY downloads DESC, created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Update template
     *
     * @param int|string $templateId Template ID
     * @param array $updates Fields to update
     * @return bool Success
     */
    public function updateTemplate($templateId, array $updates): bool
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        $allowedFields = ['name', 'description', 'metadata', 'is_public'];
        $setClause = [];
        $values = [];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields)) {
                if (in_array($field, ['metadata'])) {
                    $setClause[] = "$field = ?";
                    $values[] = is_array($value) ? json_encode($value) : $value;
                } else {
                    $setClause[] = "$field = ?";
                    $values[] = $value;
                }
            }
        }

        if (empty($setClause)) {
            return false;
        }

        $setClause[] = 'updated_at = NOW()';
        $values[] = $templateId;

        Db::query(
            "UPDATE `$table` SET " . implode(', ', $setClause) . " WHERE template_id = ? OR id = ?",
            array_merge($values, [$templateId, $templateId])
        );

        return true;
    }

    /**
     * Delete template
     *
     * @param int|string $templateId Template ID
     * @return bool Success
     */
    public function deleteTemplate($templateId): bool
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        Db::query(
            "DELETE FROM `$table` WHERE template_id = ? OR id = ?",
            [$templateId, $templateId]
        );

        return true;
    }

    /**
     * Publish template (make public)
     *
     * @param int|string $templateId Template ID
     * @return bool Success
     */
    public function publishTemplate($templateId): bool
    {
        return $this->updateTemplate($templateId, ['is_public' => 1]);
    }

    /**
     * Unpublish template (make private)
     *
     * @param int|string $templateId Template ID
     * @return bool Success
     */
    public function unpublishTemplate($templateId): bool
    {
        return $this->updateTemplate($templateId, ['is_public' => 0]);
    }

    /**
     * Increment template download counter
     *
     * @param int|string $templateId Template ID
     * @return void
     */
    public function recordTemplateDownload($templateId): void
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        Db::query(
            "UPDATE `$table` SET downloads = downloads + 1 WHERE template_id = ? OR id = ?",
            [$templateId, $templateId]
        );
    }

    /**
     * Search templates
     *
     * @param string $query Search query
     * @param int $limit Result limit
     * @param bool $publicOnly Search public templates only
     * @return array Search results
     */
    public function searchTemplates(string $query, int $limit = 50, bool $publicOnly = true): array
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);
        $query = '%' . Common::sanitizeString($query) . '%';

        $where = "WHERE (name LIKE ? OR description LIKE ?)";
        $params = [$query, $query];

        if ($publicOnly) {
            $where .= " AND is_public = 1";
        }

        return Db::fetchAll(
            "SELECT * FROM `$table` $where ORDER BY downloads DESC LIMIT ?",
            array_merge($params, [$limit])
        );
    }

    /**
     * Get template statistics
     *
     * @param int|string $templateId Template ID
     * @return array|null Statistics
     */
    public function getTemplateStats($templateId): ?array
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        return Db::fetchRow(
            "SELECT id, template_id, name, creator_id, downloads, created_at, updated_at
             FROM `$table`
             WHERE template_id = ? OR id = ?",
            [$templateId, $templateId]
        );
    }

    /**
     * Generate unique template ID
     *
     * @return string Template ID
     */
    private function generateTemplateId(): string
    {
        return 'tpl_' . bin2hex(random_bytes(8)) . '_' . time();
    }

    /**
     * Create database table for templates
     */
    public static function createTable(): void
    {
        $table = Common::prefixTable(self::TABLE_TEMPLATES);

        Db::query("
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `template_id` VARCHAR(255) UNIQUE NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `creator_id` VARCHAR(255) NOT NULL,
                `widgets_config` JSON NOT NULL,
                `metadata` JSON,
                `is_public` TINYINT(1) DEFAULT 0,
                `downloads` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME,
                FOREIGN KEY (creator_id) REFERENCES " . Common::prefixTable('user') . "(`login`),
                INDEX idx_creator_id (creator_id),
                INDEX idx_is_public (is_public),
                INDEX idx_downloads (downloads),
                FULLTEXT INDEX ft_search (name, description)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
