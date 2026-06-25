<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

/**
 * DashboardExportImport - Dashboard export and import functionality
 *
 * Export dashboards to JSON and import from JSON:
 * - Export dashboard configuration
 * - Export with all widgets
 * - Import from JSON
 * - Validate before import
 * - Batch import/export
 */
class DashboardExportImport
{
    private $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    /**
     * Export dashboard to JSON
     *
     * @param int $dashboardId Dashboard ID
     * @param bool $prettyPrint Format JSON with indentation
     * @return string JSON export
     */
    public function exportDashboard(int $dashboardId, bool $prettyPrint = true): string
    {
        $dashboard = $this->dashboardService->getDashboard($dashboardId);

        if (!$dashboard) {
            throw new \Exception('Dashboard not found');
        }

        $export = [
            'version' => '1.0',
            'export_date' => date('c'),
            'dashboard' => $this->sanitizeForExport($dashboard)
        ];

        $flags = $prettyPrint ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0;
        return json_encode($export, $flags);
    }

    /**
     * Export multiple dashboards
     *
     * @param array $dashboardIds Dashboard IDs
     * @param bool $prettyPrint Format JSON
     * @return string JSON export
     */
    public function exportDashboards(array $dashboardIds, bool $prettyPrint = true): string
    {
        $dashboards = [];

        foreach ($dashboardIds as $dashboardId) {
            $dashboard = $this->dashboardService->getDashboard($dashboardId);
            if ($dashboard) {
                $dashboards[] = $this->sanitizeForExport($dashboard);
            }
        }

        $export = [
            'version' => '1.0',
            'export_date' => date('c'),
            'dashboards' => $dashboards,
            'total' => count($dashboards)
        ];

        $flags = $prettyPrint ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0;
        return json_encode($export, $flags);
    }

    /**
     * Import dashboard from JSON
     *
     * @param string $jsonData JSON export data
     * @param int $userId User ID for imported dashboard
     * @param string $nameSuffix Optional suffix to add to dashboard name
     * @return int Imported dashboard ID
     */
    public function importDashboard(string $jsonData, int $userId, string $nameSuffix = ' (Imported)'): int
    {
        $data = json_decode($jsonData, true);

        if (!$data || !isset($data['dashboard'])) {
            throw new \Exception('Invalid dashboard export format');
        }

        $dashboard = $data['dashboard'];
        $this->validateDashboardData($dashboard);

        // Create new dashboard
        $dashboardId = $this->dashboardService->createDashboard(
            $userId,
            $dashboard['name'] . $nameSuffix,
            $dashboard['description'] ?? '',
            $dashboard['config'] ?? []
        );

        // Import widgets
        if (isset($dashboard['widgets']) && is_array($dashboard['widgets'])) {
            foreach ($dashboard['widgets'] as $widget) {
                $this->dashboardService->addWidget(
                    $dashboardId,
                    $widget['type'] ?? 'key_metrics',
                    $widget['config'] ?? [],
                    $widget['position'] ?? 0
                );
            }
        }

        return $dashboardId;
    }

    /**
     * Import multiple dashboards from JSON
     *
     * @param string $jsonData JSON export data
     * @param int $userId User ID for imported dashboards
     * @param string $nameSuffix Optional name suffix
     * @return array Imported dashboard IDs
     */
    public function importDashboards(string $jsonData, int $userId, string $nameSuffix = ' (Imported)'): array
    {
        $data = json_decode($jsonData, true);

        if (!$data || !isset($data['dashboards'])) {
            throw new \Exception('Invalid dashboards export format');
        }

        $importedIds = [];

        foreach ($data['dashboards'] as $dashboard) {
            $this->validateDashboardData($dashboard);

            $dashboardId = $this->dashboardService->createDashboard(
                $userId,
                $dashboard['name'] . $nameSuffix,
                $dashboard['description'] ?? '',
                $dashboard['config'] ?? []
            );

            if (isset($dashboard['widgets'])) {
                foreach ($dashboard['widgets'] as $widget) {
                    $this->dashboardService->addWidget(
                        $dashboardId,
                        $widget['type'] ?? 'key_metrics',
                        $widget['config'] ?? [],
                        $widget['position'] ?? 0
                    );
                }
            }

            $importedIds[] = $dashboardId;
        }

        return $importedIds;
    }

    /**
     * Validate dashboard data before import
     *
     * @param array $dashboard Dashboard data
     * @return bool Valid
     * @throws \Exception
     */
    private function validateDashboardData(array $dashboard): bool
    {
        if (empty($dashboard['name'])) {
            throw new \Exception('Dashboard name is required');
        }

        if (!is_string($dashboard['name'])) {
            throw new \Exception('Dashboard name must be a string');
        }

        if (strlen($dashboard['name']) > 255) {
            throw new \Exception('Dashboard name too long (max 255 characters)');
        }

        if (isset($dashboard['widgets']) && !is_array($dashboard['widgets'])) {
            throw new \Exception('Widgets must be an array');
        }

        if (isset($dashboard['widgets'])) {
            foreach ($dashboard['widgets'] as $widget) {
                if (!isset($widget['type']) || !is_string($widget['type'])) {
                    throw new \Exception('Widget type is required and must be a string');
                }
            }
        }

        return true;
    }

    /**
     * Sanitize dashboard data for export (remove sensitive info)
     *
     * @param array $dashboard Dashboard data
     * @return array Sanitized data
     */
    private function sanitizeForExport(array $dashboard): array
    {
        $sanitized = [
            'name' => $dashboard['name'],
            'description' => $dashboard['description'] ?? '',
            'config' => $dashboard['config'] ?? [],
            'widgets' => []
        ];

        if (isset($dashboard['widgets']) && is_array($dashboard['widgets'])) {
            foreach ($dashboard['widgets'] as $widget) {
                $sanitized['widgets'][] = [
                    'type' => $widget['type'],
                    'width' => $widget['width'] ?? 4,
                    'height' => $widget['height'] ?? 3,
                    'position' => $widget['position'] ?? 0,
                    'config' => $widget['config'] ?? []
                ];
            }
        }

        return $sanitized;
    }

    /**
     * Generate export filename
     *
     * @param string $dashboardName Dashboard name
     * @return string Filename
     */
    public static function generateExportFilename(string $dashboardName): string
    {
        $safe = preg_replace('/[^a-z0-9]/i', '-', strtolower($dashboardName));
        return 'dashboard_' . $safe . '_' . date('Y-m-d-His') . '.json';
    }

    /**
     * Get export format version
     *
     * @return string Version number
     */
    public static function getExportFormatVersion(): string
    {
        return '1.0';
    }

    /**
     * Check if JSON is valid dashboard export
     *
     * @param string $jsonData JSON data
     * @return bool Valid
     */
    public static function isValidExport(string $jsonData): bool
    {
        try {
            $data = json_decode($jsonData, true);

            if (!$data || !isset($data['version'])) {
                return false;
            }

            return isset($data['dashboard']) || isset($data['dashboards']);
        } catch (\Exception $e) {
            return false;
        }
    }
}
