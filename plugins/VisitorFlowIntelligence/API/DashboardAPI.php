<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\API;

use Piwik\Plugins\VisitorFlowIntelligence\Service\DashboardService;
use Piwik\API\ResponseBuilder;
use Piwik\Piwik;
use Piwik\Common;

/**
 * SB-023.1: Dashboard Builder API
 * 
 * REST API endpoints for dashboard management
 */
class DashboardAPI
{
    private DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Create new dashboard
     * GET /api/dashboard/create
     * POST /api/dashboard/create
     */
    public function createDashboard(string $name, string $description = '', string $config = ''): array
    {
        Piwik::checkUserIsNotAnonymous();
        
        $userId = (int)Piwik::getCurrentUserLogin();
        $configArray = $config ? json_decode($config, true) : [];

        $dashboardId = $this->dashboardService->createDashboard(
            $userId,
            $name,
            $description,
            $configArray
        );

        return [
            'success' => true,
            'dashboard_id' => $dashboardId,
            'message' => 'Dashboard created successfully',
        ];
    }

    /**
     * Get dashboard
     * GET /api/dashboard/get
     */
    public function getDashboard(int $dashboardId): array
    {
        Piwik::checkUserIsNotAnonymous();

        $dashboard = $this->dashboardService->getDashboard($dashboardId);

        if (!$dashboard) {
            return [
                'error' => 'Dashboard not found',
                'dashboard_id' => $dashboardId,
            ];
        }

        return [
            'success' => true,
            'dashboard' => $dashboard,
        ];
    }

    /**
     * Get user's dashboards
     * GET /api/dashboard/list
     */
    public function listDashboards(int $limit = 100, int $offset = 0): array
    {
        Piwik::checkUserIsNotAnonymous();
        
        $userId = (int)Piwik::getCurrentUserLogin();

        $dashboards = $this->dashboardService->getUserDashboards($userId, $limit, $offset);

        return [
            'success' => true,
            'dashboards' => $dashboards,
            'total' => count($dashboards),
        ];
    }

    /**
     * Update dashboard
     * POST /api/dashboard/update
     */
    public function updateDashboard(int $dashboardId, string $name = null, string $description = null, string $config = null): array
    {
        Piwik::checkUserIsNotAnonymous();

        $updates = [];
        
        if ($name !== null) {
            $updates['name'] = $name;
        }
        if ($description !== null) {
            $updates['description'] = $description;
        }
        if ($config !== null) {
            $updates['config'] = json_decode($config, true);
        }

        $success = $this->dashboardService->updateDashboard($dashboardId, $updates);

        return [
            'success' => $success,
            'dashboard_id' => $dashboardId,
            'message' => $success ? 'Dashboard updated' : 'Update failed',
        ];
    }

    /**
     * Delete dashboard
     * POST /api/dashboard/delete
     */
    public function deleteDashboard(int $dashboardId): array
    {
        Piwik::checkUserIsNotAnonymous();

        $success = $this->dashboardService->deleteDashboard($dashboardId);

        return [
            'success' => $success,
            'dashboard_id' => $dashboardId,
            'message' => $success ? 'Dashboard deleted' : 'Delete failed',
        ];
    }

    /**
     * Add widget to dashboard
     * POST /api/dashboard/widget/add
     */
    public function addWidget(int $dashboardId, string $type, string $config = ''): array
    {
        Piwik::checkUserIsNotAnonymous();

        $configArray = $config ? json_decode($config, true) : [];

        $widgetId = $this->dashboardService->addWidget($dashboardId, $type, $configArray);

        return [
            'success' => true,
            'widget_id' => $widgetId,
            'message' => 'Widget added successfully',
        ];
    }

    /**
     * Update widget
     * POST /api/dashboard/widget/update
     */
    public function updateWidget(int $widgetId, string $type = null, string $config = null, int $width = null, int $height = null): array
    {
        Piwik::checkUserIsNotAnonymous();

        $updates = [];
        
        if ($type !== null) {
            $updates['type'] = $type;
        }
        if ($config !== null) {
            $updates['config'] = json_decode($config, true);
        }
        if ($width !== null) {
            $updates['width'] = $width;
        }
        if ($height !== null) {
            $updates['height'] = $height;
        }

        $success = $this->dashboardService->updateWidget($widgetId, $updates);

        return [
            'success' => $success,
            'widget_id' => $widgetId,
            'message' => $success ? 'Widget updated' : 'Update failed',
        ];
    }

    /**
     * Remove widget
     * POST /api/dashboard/widget/remove
     */
    public function removeWidget(int $widgetId): array
    {
        Piwik::checkUserIsNotAnonymous();

        $success = $this->dashboardService->removeWidget($widgetId);

        return [
            'success' => $success,
            'widget_id' => $widgetId,
            'message' => $success ? 'Widget removed' : 'Remove failed',
        ];
    }

    /**
     * Duplicate dashboard
     * POST /api/dashboard/duplicate
     */
    public function duplicateDashboard(int $dashboardId, string $nameSuffix = ' (Copy)'): array
    {
        Piwik::checkUserIsNotAnonymous();
        
        $userId = (int)Piwik::getCurrentUserLogin();

        $newDashboardId = $this->dashboardService->duplicateDashboard($dashboardId, $userId, $nameSuffix);

        return [
            'success' => true,
            'original_dashboard_id' => $dashboardId,
            'new_dashboard_id' => $newDashboardId,
            'message' => 'Dashboard duplicated successfully',
        ];
    }

    /**
     * Share dashboard with user
     * POST /api/dashboard/share
     */
    public function shareDashboard(int $dashboardId, int $targetUserId, bool $canEdit = false): array
    {
        Piwik::checkUserIsNotAnonymous();

        $success = $this->dashboardService->shareDashboard($dashboardId, $targetUserId, $canEdit);

        return [
            'success' => $success,
            'dashboard_id' => $dashboardId,
            'target_user_id' => $targetUserId,
            'message' => $success ? 'Dashboard shared successfully' : 'Share failed',
        ];
    }

    /**
     * Search dashboards
     * GET /api/dashboard/search
     */
    public function searchDashboards(string $query, int $limit = 20): array
    {
        Piwik::checkUserIsNotAnonymous();
        
        $userId = (int)Piwik::getCurrentUserLogin();

        $dashboards = $this->dashboardService->searchDashboards($query, $userId, $limit);

        return [
            'success' => true,
            'query' => $query,
            'dashboards' => $dashboards,
            'total' => count($dashboards),
        ];
    }

    /**
     * Get dashboard templates
     * GET /api/dashboard/templates
     */
    public function getTemplates(): array
    {
        Piwik::checkUserIsNotAnonymous();

        $templates = $this->dashboardService->getTemplates();

        return [
            'success' => true,
            'templates' => $templates,
            'total' => count($templates),
        ];
    }

    /**
     * Create dashboard from template
     * POST /api/dashboard/create-from-template
     */
    public function createFromTemplate(string $templateId, string $dashboardName): array
    {
        Piwik::checkUserIsNotAnonymous();
        
        $userId = (int)Piwik::getCurrentUserLogin();

        $dashboardId = $this->dashboardService->createFromTemplate($userId, $templateId, $dashboardName);

        return [
            'success' => true,
            'template_id' => $templateId,
            'dashboard_id' => $dashboardId,
            'dashboard_name' => $dashboardName,
            'message' => 'Dashboard created from template',
        ];
    }

    /**
     * Get dashboard statistics
     * GET /api/dashboard/stats
     */
    public function getDashboardStats(): array
    {
        Piwik::checkUserIsNotAnonymous();
        
        $userId = (int)Piwik::getCurrentUserLogin();

        $stats = $this->dashboardService->getDashboardStats($userId);

        return [
            'success' => true,
            'stats' => $stats,
        ];
    }
}
