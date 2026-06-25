<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests;

use Piwik\Plugins\VisitorFlowIntelligence\Service\DashboardService;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Db;
use Piwik\Common;

/**
 * @group VisitorFlowIntelligence
 * @group DashboardService
 * @group Integration
 */
class DashboardServiceIntegrationTest extends IntegrationTestCase
{
    /**
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * @var int
     */
    private $testUserId = 1;

    public function setUp(): void
    {
        parent::setUp();
        $this->dashboardService = new DashboardService();
        $this->createTestDatabase();
    }

    /**
     * Create test database tables
     */
    private function createTestDatabase(): void
    {
        $prefix = Common::prefixTable('visitor_flow_dashboards');
        $widgetPrefix = Common::prefixTable('visitor_flow_dashboard_widgets');

        // Create dashboards table
        Db::query("
            CREATE TABLE IF NOT EXISTS `$prefix` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT,
                `config` JSON,
                `is_public` TINYINT(1) DEFAULT 0,
                `is_default` TINYINT(1) DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `sort_order` INT DEFAULT 0,
                FOREIGN KEY (`user_id`) REFERENCES " . Common::prefixTable('user') . "(`iduser`),
                INDEX idx_user_id (`user_id`),
                INDEX idx_sort_order (`user_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Create widgets table
        Db::query("
            CREATE TABLE IF NOT EXISTS `$widgetPrefix` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `dashboard_id` INT NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `config` JSON,
                `position` INT DEFAULT 0,
                `width` INT DEFAULT 4,
                `height` INT DEFAULT 3,
                `created_at` DATETIME NOT NULL,
                FOREIGN KEY (`dashboard_id`) REFERENCES `$prefix`(`id`) ON DELETE CASCADE,
                INDEX idx_dashboard_id (`dashboard_id`),
                INDEX idx_position (`dashboard_id`, `position`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Test: Create new dashboard
     */
    public function testCreateDashboard()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Test Dashboard',
            'Test description',
            []
        );

        $this->assertIsInt($dashboardId);
        $this->assertGreaterThan(0, $dashboardId);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertNotNull($dashboard);
        $this->assertEquals('Test Dashboard', $dashboard['name']);
        $this->assertEquals('Test description', $dashboard['description']);
        $this->assertEquals($this->testUserId, $dashboard['user_id']);
    }

    /**
     * Test: Create dashboard with JSON config
     */
    public function testCreateDashboardWithConfig()
    {
        $config = [
            'theme' => 'dark',
            'auto_refresh' => true,
            'refresh_interval' => 30
        ];

        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Config Dashboard',
            'With custom config',
            $config
        );

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertIsArray($dashboard['config']);
        $this->assertEquals('dark', $dashboard['config']['theme']);
    }

    /**
     * Test: Get dashboard by ID
     */
    public function testGetDashboard()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Retrievable Dashboard',
            'For retrieval test',
            []
        );

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertNotNull($dashboard);
        $this->assertEquals($dashboardId, $dashboard['id']);
    }

    /**
     * Test: Get non-existent dashboard returns null
     */
    public function testGetNonExistentDashboard()
    {
        $dashboard = $this->dashboardService->getDashboard(99999);
        $this->assertNull($dashboard);
    }

    /**
     * Test: Get user dashboards with pagination
     */
    public function testGetUserDashboards()
    {
        // Create multiple dashboards
        for ($i = 0; $i < 5; $i++) {
            $this->dashboardService->createDashboard(
                $this->testUserId,
                "Dashboard $i",
                "Description $i",
                []
            );
        }

        $dashboards = $this->dashboardService->getUserDashboards($this->testUserId, 10, 0);
        $this->assertIsArray($dashboards);
        $this->assertEquals(5, count($dashboards));
    }

    /**
     * Test: Get user dashboards with limit
     */
    public function testGetUserDashboardsWithLimit()
    {
        // Create 10 dashboards
        for ($i = 0; $i < 10; $i++) {
            $this->dashboardService->createDashboard(
                $this->testUserId,
                "Dashboard $i",
                "Description $i",
                []
            );
        }

        $dashboards = $this->dashboardService->getUserDashboards($this->testUserId, 5, 0);
        $this->assertEquals(5, count($dashboards));

        $dashboards = $this->dashboardService->getUserDashboards($this->testUserId, 5, 5);
        $this->assertEquals(5, count($dashboards));
    }

    /**
     * Test: Update dashboard
     */
    public function testUpdateDashboard()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Original Name',
            'Original Description',
            []
        );

        $updates = [
            'name' => 'Updated Name',
            'description' => 'Updated Description'
        ];

        $result = $this->dashboardService->updateDashboard($dashboardId, $updates);
        $this->assertTrue($result);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertEquals('Updated Name', $dashboard['name']);
        $this->assertEquals('Updated Description', $dashboard['description']);
    }

    /**
     * Test: Update dashboard config
     */
    public function testUpdateDashboardConfig()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Config Test',
            '',
            ['theme' => 'light']
        );

        $updates = [
            'config' => ['theme' => 'dark', 'auto_refresh' => true]
        ];

        $this->dashboardService->updateDashboard($dashboardId, $updates);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertEquals('dark', $dashboard['config']['theme']);
        $this->assertTrue($dashboard['config']['auto_refresh']);
    }

    /**
     * Test: Delete dashboard
     */
    public function testDeleteDashboard()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Deletable Dashboard',
            '',
            []
        );

        $result = $this->dashboardService->deleteDashboard($dashboardId);
        $this->assertTrue($result);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertNull($dashboard);
    }

    /**
     * Test: Add widget to dashboard
     */
    public function testAddWidget()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Widget Test Dashboard',
            '',
            []
        );

        $widgetId = $this->dashboardService->addWidget(
            $dashboardId,
            'key_metrics',
            ['segment_id' => 1],
            0
        );

        $this->assertIsInt($widgetId);
        $this->assertGreaterThan(0, $widgetId);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertCount(1, $dashboard['widgets']);
        $this->assertEquals('key_metrics', $dashboard['widgets'][0]['type']);
    }

    /**
     * Test: Add multiple widgets
     */
    public function testAddMultipleWidgets()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Multi Widget Dashboard',
            '',
            []
        );

        $widgetTypes = ['key_metrics', 'trends_chart', 'traffic_sources', 'device_breakdown'];
        $widgetIds = [];

        foreach ($widgetTypes as $index => $type) {
            $widgetId = $this->dashboardService->addWidget(
                $dashboardId,
                $type,
                [],
                $index
            );
            $widgetIds[] = $widgetId;
        }

        $this->assertEquals(4, count($widgetIds));

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertCount(4, $dashboard['widgets']);
    }

    /**
     * Test: Update widget
     */
    public function testUpdateWidget()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Update Widget Dashboard',
            '',
            []
        );

        $widgetId = $this->dashboardService->addWidget(
            $dashboardId,
            'key_metrics',
            ['segment_id' => 1],
            0
        );

        $updates = [
            'type' => 'trends_chart',
            'config' => ['segment_id' => 2, 'metric' => 'visits'],
            'width' => 8,
            'height' => 4
        ];

        $result = $this->dashboardService->updateWidget($widgetId, $updates);
        $this->assertTrue($result);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $widget = $dashboard['widgets'][0];
        $this->assertEquals('trends_chart', $widget['type']);
        $this->assertEquals(8, $widget['width']);
        $this->assertEquals(4, $widget['height']);
    }

    /**
     * Test: Remove widget
     */
    public function testRemoveWidget()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Remove Widget Dashboard',
            '',
            []
        );

        $widgetId = $this->dashboardService->addWidget(
            $dashboardId,
            'key_metrics',
            [],
            0
        );

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertCount(1, $dashboard['widgets']);

        $result = $this->dashboardService->removeWidget($widgetId);
        $this->assertTrue($result);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertCount(0, $dashboard['widgets']);
    }

    /**
     * Test: Get dashboard widgets
     */
    public function testGetDashboardWidgets()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Widgets List Dashboard',
            '',
            []
        );

        for ($i = 0; $i < 3; $i++) {
            $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], $i);
        }

        $widgets = $this->dashboardService->getDashboardWidgets($dashboardId);
        $this->assertCount(3, $widgets);
    }

    /**
     * Test: Duplicate dashboard
     */
    public function testDuplicateDashboard()
    {
        $originalId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Original Dashboard',
            'To be duplicated',
            []
        );

        $this->dashboardService->addWidget($originalId, 'key_metrics', [], 0);
        $this->dashboardService->addWidget($originalId, 'trends_chart', [], 1);

        $newId = $this->dashboardService->duplicateDashboard(
            $originalId,
            $this->testUserId,
            ' (Copy)'
        );

        $this->assertIsInt($newId);
        $this->assertNotEquals($originalId, $newId);

        $newDashboard = $this->dashboardService->getDashboard($newId);
        $this->assertStringContainsString('Copy', $newDashboard['name']);
        $this->assertCount(2, $newDashboard['widgets']);
    }

    /**
     * Test: Duplicate dashboard to different user
     */
    public function testDuplicateDashboardToOtherUser()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Share Dashboard',
            '',
            []
        );

        $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], 0);

        $otherUserId = 2;

        $newDashboardId = $this->dashboardService->duplicateDashboard(
            $dashboardId,
            $otherUserId,
            ' (Shared)'
        );

        $newDashboard = $this->dashboardService->getDashboard($newDashboardId);
        $this->assertEquals($otherUserId, $newDashboard['user_id']);
        $this->assertCount(1, $newDashboard['widgets']);
    }

    /**
     * Test: Share dashboard
     */
    public function testShareDashboard()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Shareable Dashboard',
            '',
            []
        );

        $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], 0);

        $targetUserId = 2;
        $result = $this->dashboardService->shareDashboard($dashboardId, $targetUserId, true);
        $this->assertTrue($result);

        // Verify shared dashboard exists for target user
        $userDashboards = $this->dashboardService->getUserDashboards($targetUserId, 10, 0);
        $this->assertGreaterThan(0, count($userDashboards));
    }

    /**
     * Test: Search dashboards
     */
    public function testSearchDashboards()
    {
        $this->dashboardService->createDashboard(
            $this->testUserId,
            'Sales Dashboard',
            'Monthly sales performance',
            []
        );

        $this->dashboardService->createDashboard(
            $this->testUserId,
            'Performance Dashboard',
            'Website performance metrics',
            []
        );

        $results = $this->dashboardService->searchDashboards('Sales', $this->testUserId, 10);
        $this->assertGreaterThan(0, count($results));
        $this->assertEquals('Sales Dashboard', $results[0]['name']);
    }

    /**
     * Test: Search in description
     */
    public function testSearchInDescription()
    {
        $this->dashboardService->createDashboard(
            $this->testUserId,
            'Analytics',
            'Contains conversion data',
            []
        );

        $results = $this->dashboardService->searchDashboards('conversion', $this->testUserId, 10);
        $this->assertGreaterThan(0, count($results));
    }

    /**
     * Test: Get templates
     */
    public function testGetTemplates()
    {
        $templates = $this->dashboardService->getTemplates(10);

        $this->assertIsArray($templates);
        $this->assertGreaterThan(0, count($templates));
        $this->assertArrayHasKey('id', $templates[0]);
        $this->assertArrayHasKey('name', $templates[0]);
        $this->assertArrayHasKey('widgets', $templates[0]);
    }

    /**
     * Test: Get templates with limit
     */
    public function testGetTemplatesWithLimit()
    {
        $templates = $this->dashboardService->getTemplates(2);
        $this->assertLessThanOrEqual(2, count($templates));
    }

    /**
     * Test: Create dashboard from template
     */
    public function testCreateFromTemplate()
    {
        $templates = $this->dashboardService->getTemplates(1);
        $templateId = $templates[0]['id'];

        $dashboardId = $this->dashboardService->createFromTemplate(
            $this->testUserId,
            $templateId,
            'My Custom Dashboard'
        );

        $this->assertIsInt($dashboardId);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertEquals('My Custom Dashboard', $dashboard['name']);
        $this->assertGreaterThan(0, count($dashboard['widgets']));
    }

    /**
     * Test: Create dashboard from each template
     */
    public function testCreateFromEachTemplate()
    {
        $templates = $this->dashboardService->getTemplates(10);

        foreach ($templates as $template) {
            $dashboardId = $this->dashboardService->createFromTemplate(
                $this->testUserId,
                $template['id'],
                'Dashboard from ' . $template['name']
            );

            $this->assertIsInt($dashboardId);

            $dashboard = $this->dashboardService->getDashboard($dashboardId);
            $this->assertGreaterThan(0, count($dashboard['widgets']));
        }
    }

    /**
     * Test: Get dashboard statistics
     */
    public function testGetDashboardStats()
    {
        // Create dashboards
        for ($i = 0; $i < 3; $i++) {
            $this->dashboardService->createDashboard(
                $this->testUserId,
                "Dashboard $i",
                '',
                []
            );
        }

        $stats = $this->dashboardService->getDashboardStats($this->testUserId);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_dashboards', $stats);
        $this->assertArrayHasKey('total_widgets', $stats);
        $this->assertGreaterThan(0, $stats['total_dashboards']);
    }

    /**
     * Test: Widget cascade delete
     */
    public function testWidgetCascadeDelete()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Cascade Test',
            '',
            []
        );

        $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], 0);
        $this->dashboardService->addWidget($dashboardId, 'trends_chart', [], 1);

        // Delete dashboard
        $this->dashboardService->deleteDashboard($dashboardId);

        // Verify all widgets are deleted
        $widgets = $this->dashboardService->getDashboardWidgets($dashboardId);
        $this->assertCount(0, $widgets);
    }

    /**
     * Test: User isolation (dashboards not visible to other users)
     */
    public function testUserIsolation()
    {
        $user1Id = 1;
        $user2Id = 2;

        $dashboardId = $this->dashboardService->createDashboard(
            $user1Id,
            'Private Dashboard',
            '',
            []
        );

        $user2Dashboards = $this->dashboardService->getUserDashboards($user2Id, 10, 0);
        $dashboardIds = array_column($user2Dashboards, 'id');

        $this->assertNotContains($dashboardId, $dashboardIds);
    }

    /**
     * Test: Dashboard timestamps
     */
    public function testDashboardTimestamps()
    {
        $before = time();
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Timestamp Test',
            '',
            []
        );
        $after = time();

        $dashboard = $this->dashboardService->getDashboard($dashboardId);

        $createdAt = strtotime($dashboard['created_at']);
        $updatedAt = strtotime($dashboard['updated_at']);

        $this->assertGreaterThanOrEqual($before, $createdAt);
        $this->assertLessThanOrEqual($after, $createdAt);
        $this->assertEquals($createdAt, $updatedAt);
    }

    /**
     * Test: Widget position ordering
     */
    public function testWidgetPositionOrdering()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Position Test',
            '',
            []
        );

        $widgetIds = [];
        for ($i = 0; $i < 4; $i++) {
            $id = $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], $i);
            $widgetIds[] = $id;
        }

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $widgets = $dashboard['widgets'];

        for ($i = 0; $i < count($widgets); $i++) {
            $this->assertEquals($i, $widgets[$i]['position']);
        }
    }

    /**
     * Test: Large dashboard with many widgets
     */
    public function testLargeDashboard()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Large Dashboard',
            'Performance test with many widgets',
            []
        );

        $widgetCount = 20;
        for ($i = 0; $i < $widgetCount; $i++) {
            $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], $i);
        }

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertCount($widgetCount, $dashboard['widgets']);
    }

    /**
     * Test: Dashboard with complex widget config
     */
    public function testComplexWidgetConfig()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Complex Config Dashboard',
            '',
            []
        );

        $complexConfig = [
            'segment_id' => 1,
            'metric_id' => 'visits',
            'date_range' => 'last_30_days',
            'comparison' => 'previous_period',
            'filters' => [
                ['field' => 'country', 'value' => 'DE'],
                ['field' => 'device', 'value' => 'mobile']
            ],
            'grouping' => 'day',
            'chart_type' => 'line',
            'show_goal' => true,
            'threshold' => 1000
        ];

        $widgetId = $this->dashboardService->addWidget(
            $dashboardId,
            'trends_chart',
            $complexConfig,
            0
        );

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $widget = $dashboard['widgets'][0];
        $this->assertEquals($complexConfig['segment_id'], $widget['config']['segment_id']);
        $this->assertEquals($complexConfig['filters'][0]['field'], $widget['config']['filters'][0]['field']);
    }
}
