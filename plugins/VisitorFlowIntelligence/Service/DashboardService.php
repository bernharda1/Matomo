<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Db;
use Piwik\Common;

/**
 * SB-023.1: Advanced Dashboard Builder - Core Service
 * 
 * Manages custom analytics dashboard creation, persistence, and retrieval
 */
class DashboardService
{
    private const TABLE_NAME = 'visitor_flow_dashboards';
    private const TABLE_WIDGETS = 'visitor_flow_dashboard_widgets';

    /**
     * Create a new dashboard
     */
    public function createDashboard(int $userId, string $name, string $description = '', array $config = []): int
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'config' => json_encode($config),
            'is_public' => 0,
            'is_default' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'sort_order' => $this->getNextSortOrder($userId),
        ];

        Db::query("INSERT INTO $table SET " . $this->buildInsertString($data), array_values($data));
        
        return (int)Db::fetchOne("SELECT LAST_INSERT_ID()")[0];
    }

    /**
     * Get dashboard by ID
     */
    public function getDashboard(int $dashboardId): ?array
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        
        $dashboard = Db::fetchRow(
            "SELECT * FROM $table WHERE id = ?",
            [$dashboardId]
        );

        if ($dashboard) {
            $dashboard['config'] = json_decode($dashboard['config'] ?? '{}', true);
            $dashboard['widgets'] = $this->getDashboardWidgets($dashboardId);
        }

        return $dashboard;
    }

    /**
     * Get all dashboards for user
     */
    public function getUserDashboards(int $userId, int $limit = 100, int $offset = 0): array
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        
        $dashboards = Db::fetchAll(
            "SELECT * FROM $table WHERE user_id = ? ORDER BY sort_order ASC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );

        foreach ($dashboards as &$dashboard) {
            $dashboard['config'] = json_decode($dashboard['config'] ?? '{}', true);
            $dashboard['widget_count'] = $this->getWidgetCount($dashboard['id']);
        }

        return $dashboards;
    }

    /**
     * Update dashboard
     */
    public function updateDashboard(int $dashboardId, array $updates): bool
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        
        $allowed = ['name', 'description', 'config', 'is_public', 'is_default'];
        $updates = array_intersect_key($updates, array_flip($allowed));
        
        if (empty($updates)) {
            return false;
        }

        $updates['updated_at'] = date('Y-m-d H:i:s');
        
        if (isset($updates['config']) && is_array($updates['config'])) {
            $updates['config'] = json_encode($updates['config']);
        }

        $set = [];
        $values = [];
        foreach ($updates as $key => $value) {
            $set[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $dashboardId;

        Db::query(
            "UPDATE $table SET " . implode(', ', $set) . " WHERE id = ?",
            $values
        );

        return true;
    }

    /**
     * Delete dashboard
     */
    public function deleteDashboard(int $dashboardId): bool
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        $tableWidgets = Common::prefixTable(self::TABLE_WIDGETS);

        // Delete widgets first
        Db::query("DELETE FROM $tableWidgets WHERE dashboard_id = ?", [$dashboardId]);

        // Delete dashboard
        Db::query("DELETE FROM $table WHERE id = ?", [$dashboardId]);

        return true;
    }

    /**
     * Add widget to dashboard
     */
    public function addWidget(int $dashboardId, string $type, array $config, int $position = 0): int
    {
        $table = Common::prefixTable(self::TABLE_WIDGETS);
        
        $data = [
            'dashboard_id' => $dashboardId,
            'type' => $type,
            'config' => json_encode($config),
            'position' => $position,
            'width' => $config['width'] ?? 4,
            'height' => $config['height'] ?? 3,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        Db::query("INSERT INTO $table SET " . $this->buildInsertString($data), array_values($data));
        
        return (int)Db::fetchOne("SELECT LAST_INSERT_ID()")[0];
    }

    /**
     * Update widget configuration
     */
    public function updateWidget(int $widgetId, array $config): bool
    {
        $table = Common::prefixTable(self::TABLE_WIDGETS);
        
        $allowed = ['type', 'config', 'width', 'height', 'position'];
        $updates = array_intersect_key($config, array_flip($allowed));

        if (isset($updates['config']) && is_array($updates['config'])) {
            $updates['config'] = json_encode($updates['config']);
        }

        $set = [];
        $values = [];
        foreach ($updates as $key => $value) {
            $set[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $widgetId;

        Db::query(
            "UPDATE $table SET " . implode(', ', $set) . " WHERE id = ?",
            $values
        );

        return true;
    }

    /**
     * Remove widget from dashboard
     */
    public function removeWidget(int $widgetId): bool
    {
        $table = Common::prefixTable(self::TABLE_WIDGETS);
        Db::query("DELETE FROM $table WHERE id = ?", [$widgetId]);
        return true;
    }

    /**
     * Get dashboard widgets
     */
    public function getDashboardWidgets(int $dashboardId): array
    {
        $table = Common::prefixTable(self::TABLE_WIDGETS);
        
        $widgets = Db::fetchAll(
            "SELECT * FROM $table WHERE dashboard_id = ? ORDER BY position ASC",
            [$dashboardId]
        );

        foreach ($widgets as &$widget) {
            $widget['config'] = json_decode($widget['config'] ?? '{}', true);
        }

        return $widgets;
    }

    /**
     * Get widget count
     */
    public function getWidgetCount(int $dashboardId): int
    {
        $table = Common::prefixTable(self::TABLE_WIDGETS);
        
        $result = Db::fetchOne(
            "SELECT COUNT(*) as count FROM $table WHERE dashboard_id = ?",
            [$dashboardId]
        );

        return (int)($result['count'] ?? 0);
    }

    /**
     * Duplicate dashboard
     */
    public function duplicateDashboard(int $dashboardId, int $userId, string $nameSuffix = ' (Copy)'): int
    {
        $original = $this->getDashboard($dashboardId);
        
        if (!$original) {
            throw new \Exception("Dashboard not found: $dashboardId");
        }

        $newDashboard = $this->createDashboard(
            $userId,
            $original['name'] . $nameSuffix,
            $original['description'],
            $original['config']
        );

        // Copy widgets
        foreach ($original['widgets'] as $widget) {
            $this->addWidget(
                $newDashboard,
                $widget['type'],
                $widget['config'],
                $widget['position']
            );
        }

        return $newDashboard;
    }

    /**
     * Share dashboard with user
     */
    public function shareDashboard(int $dashboardId, int $targetUserId, bool $canEdit = false): bool
    {
        // Create shared copy
        $dashboard = $this->getDashboard($dashboardId);
        
        if (!$dashboard) {
            return false;
        }

        $newDashboard = $this->createDashboard(
            $targetUserId,
            $dashboard['name'] . ' (Shared)',
            $dashboard['description'],
            array_merge($dashboard['config'], ['shared_from' => $dashboardId, 'can_edit' => $canEdit])
        );

        // Copy widgets
        foreach ($dashboard['widgets'] as $widget) {
            $this->addWidget(
                $newDashboard,
                $widget['type'],
                $widget['config'],
                $widget['position']
            );
        }

        return true;
    }

    /**
     * Search dashboards
     */
    public function searchDashboards(string $query, int $userId, int $limit = 20): array
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        
        $query = "%$query%";
        
        $dashboards = Db::fetchAll(
            "SELECT * FROM $table WHERE user_id = ? AND (name LIKE ? OR description LIKE ?) LIMIT ?",
            [$userId, $query, $query, $limit]
        );

        foreach ($dashboards as &$dashboard) {
            $dashboard['config'] = json_decode($dashboard['config'] ?? '{}', true);
        }

        return $dashboards;
    }

    /**
     * Get dashboard templates (pre-built dashboards)
     */
    public function getTemplates(int $limit = 10): array
    {
        return [
            [
                'id' => 'template_overview',
                'name' => 'Segment Overview',
                'description' => 'High-level segment metrics and trends',
                'widgets' => [
                    ['type' => 'key_metrics', 'width' => 4, 'height' => 2],
                    ['type' => 'trends_chart', 'width' => 8, 'height' => 3],
                    ['type' => 'traffic_sources', 'width' => 6, 'height' => 3],
                    ['type' => 'device_breakdown', 'width' => 6, 'height' => 3],
                ],
            ],
            [
                'id' => 'template_performance',
                'name' => 'Performance Analysis',
                'description' => 'Detailed performance and conversion metrics',
                'widgets' => [
                    ['type' => 'conversion_metrics', 'width' => 6, 'height' => 3],
                    ['type' => 'top_pages', 'width' => 6, 'height' => 3],
                    ['type' => 'bounce_analysis', 'width' => 6, 'height' => 3],
                    ['type' => 'flow_visualization', 'width' => 6, 'height' => 4],
                ],
            ],
            [
                'id' => 'template_realtime',
                'name' => 'Real-time Monitor',
                'description' => 'Live visitor and event monitoring',
                'widgets' => [
                    ['type' => 'live_visitors', 'width' => 4, 'height' => 2],
                    ['type' => 'live_events', 'width' => 8, 'height' => 4],
                    ['type' => 'visitor_flow', 'width' => 12, 'height' => 4],
                ],
            ],
            [
                'id' => 'template_anomaly',
                'name' => 'Anomaly Detection',
                'description' => 'Identify unusual patterns and trends',
                'widgets' => [
                    ['type' => 'anomaly_alerts', 'width' => 12, 'height' => 3],
                    ['type' => 'forecast_chart', 'width' => 6, 'height' => 3],
                    ['type' => 'trend_analysis', 'width' => 6, 'height' => 3],
                ],
            ],
        ];
    }

    /**
     * Create dashboard from template
     */
    public function createFromTemplate(int $userId, string $templateId, string $dashboardName): int
    {
        $templates = $this->getTemplates();
        $template = null;

        foreach ($templates as $t) {
            if ($t['id'] === $templateId) {
                $template = $t;
                break;
            }
        }

        if (!$template) {
            throw new \Exception("Template not found: $templateId");
        }

        $dashboard = $this->createDashboard(
            $userId,
            $dashboardName,
            $template['description'],
            ['template_id' => $templateId]
        );

        foreach ($template['widgets'] as $widget) {
            $this->addWidget(
                $dashboard,
                $widget['type'],
                $widget,
                count($this->getDashboardWidgets($dashboard))
            );
        }

        return $dashboard;
    }

    /**
     * Get next sort order for user's dashboards
     */
    private function getNextSortOrder(int $userId): int
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        
        $result = Db::fetchOne(
            "SELECT MAX(sort_order) as max_order FROM $table WHERE user_id = ?",
            [$userId]
        );

        return ((int)($result['max_order'] ?? 0)) + 1;
    }

    /**
     * Build INSERT statement
     */
    private function buildInsertString(array $data): string
    {
        $keys = array_keys($data);
        $placeholders = array_fill(0, count($keys), '?');
        return implode(', ', array_map(fn($k) => "$k = ?", $keys));
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(int $userId): array
    {
        $table = Common::prefixTable(self::TABLE_NAME);
        
        $stats = Db::fetchRow(
            "SELECT 
                COUNT(*) as total_dashboards,
                COUNT(CASE WHEN is_default = 1 THEN 1 END) as default_dashboards,
                COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_dashboards
            FROM $table 
            WHERE user_id = ?",
            [$userId]
        );

        return [
            'total_dashboards' => (int)($stats['total_dashboards'] ?? 0),
            'default_dashboards' => (int)($stats['default_dashboards'] ?? 0),
            'public_dashboards' => (int)($stats['public_dashboards'] ?? 0),
            'total_widgets' => $this->getTotalWidgets($userId),
        ];
    }

    /**
     * Get total widgets for user
     */
    private function getTotalWidgets(int $userId): int
    {
        $dashboardTable = Common::prefixTable(self::TABLE_NAME);
        $widgetTable = Common::prefixTable(self::TABLE_WIDGETS);
        
        $result = Db::fetchOne(
            "SELECT COUNT(*) as count FROM $widgetTable w
             INNER JOIN $dashboardTable d ON w.dashboard_id = d.id
             WHERE d.user_id = ?",
            [$userId]
        );

        return (int)($result['count'] ?? 0);
    }
}
