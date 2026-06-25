<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests;

use Piwik\Plugins\VisitorFlowIntelligence\API\DashboardAPI;
use Piwik\Plugins\VisitorFlowIntelligence\Service\DashboardService;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Db;
use Piwik\Common;

/**
 * @group VisitorFlowIntelligence
 * @group DashboardAPI
 * @group Integration
 */
class DashboardAPIIntegrationTest extends IntegrationTestCase
{
    /**
     * @var DashboardAPI
     */
    private $dashboardAPI;

    /**
     * @var DashboardService
     */
    private $dashboardService;

    /**
     * @var int
     */
    private $testUserId = 1;

    /**
     * @var int
     */
    private $testDashboardId;

    public function setUp(): void
    {
        parent::setUp();
        $this->dashboardService = new DashboardService();
        $this->dashboardAPI = new DashboardAPI();
        $this->createTestDatabase();
        $this->createTestDashboard();
    }

    /**
     * Create test database tables
     */
    private function createTestDatabase(): void
    {
        $prefix = Common::prefixTable('visitor_flow_dashboards');
        $widgetPrefix = Common::prefixTable('visitor_flow_dashboard_widgets');

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
     * Create test dashboard
     */
    private function createTestDashboard(): void
    {
        $this->testDashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Test Dashboard',
            'For API testing',
            []
        );
    }

    /**
     * Test: Create dashboard via API
     */
    public function testCreateDashboardAPI()
    {
        $response = $this->dashboardAPI->create(
            'API Test Dashboard',
            'Created via API',
            json_encode([])
        );

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('dashboard_id', $response);
        $this->assertGreaterThan(0, $response['dashboard_id']);
    }

    /**
     * Test: Get dashboard via API
     */
    public function testGetDashboardAPI()
    {
        $response = $this->dashboardAPI->get($this->testDashboardId);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('dashboard', $response);
        $this->assertEquals($this->testDashboardId, $response['dashboard']['id']);
        $this->assertEquals('Test Dashboard', $response['dashboard']['name']);
    }

    /**
     * Test: List dashboards via API
     */
    public function testListDashboardsAPI()
    {
        // Create additional dashboards
        for ($i = 0; $i < 3; $i++) {
            $this->dashboardService->createDashboard(
                $this->testUserId,
                "Dashboard $i",
                '',
                []
            );
        }

        $response = $this->dashboardAPI->getList(10, 0);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('dashboards', $response);
        $this->assertArrayHasKey('total', $response);
        $this->assertGreaterThanOrEqual(4, $response['total']);
    }

    /**
     * Test: List dashboards with pagination
     */
    public function testListDashboardsAPIWithPagination()
    {
        // Create 10 dashboards
        for ($i = 0; $i < 10; $i++) {
            $this->dashboardService->createDashboard(
                $this->testUserId,
                "Dashboard $i",
                '',
                []
            );
        }

        $page1 = $this->dashboardAPI->getList(5, 0);
        $page2 = $this->dashboardAPI->getList(5, 5);

        $this->assertCount(5, $page1['dashboards']);
        $this->assertCount(5, $page2['dashboards']);
        $this->assertGreater(0, $page1['total']);
    }

    /**
     * Test: Update dashboard via API
     */
    public function testUpdateDashboardAPI()
    {
        $response = $this->dashboardAPI->update(
            $this->testDashboardId,
            'Updated Name',
            'Updated Description',
            json_encode(['theme' => 'dark'])
        );

        $this->assertTrue($response['success']);
        $this->assertEquals($this->testDashboardId, $response['dashboard_id']);

        $dashboard = $this->dashboardService->getDashboard($this->testDashboardId);
        $this->assertEquals('Updated Name', $dashboard['name']);
        $this->assertEquals('Updated Description', $dashboard['description']);
    }

    /**
     * Test: Delete dashboard via API
     */
    public function testDeleteDashboardAPI()
    {
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Deletable Dashboard',
            '',
            []
        );

        $response = $this->dashboardAPI->delete($dashboardId);

        $this->assertTrue($response['success']);
        $this->assertEquals($dashboardId, $response['dashboard_id']);

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertNull($dashboard);
    }

    /**
     * Test: Add widget via API
     */
    public function testAddWidgetAPI()
    {
        $response = $this->dashboardAPI->addWidget(
            $this->testDashboardId,
            'key_metrics',
            json_encode(['segment_id' => 1])
        );

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('widget_id', $response);
        $this->assertGreaterThan(0, $response['widget_id']);

        $dashboard = $this->dashboardService->getDashboard($this->testDashboardId);
        $this->assertCount(1, $dashboard['widgets']);
    }

    /**
     * Test: Update widget via API
     */
    public function testUpdateWidgetAPI()
    {
        $widgetId = $this->dashboardService->addWidget(
            $this->testDashboardId,
            'key_metrics',
            [],
            0
        );

        $response = $this->dashboardAPI->updateWidget(
            $widgetId,
            'trends_chart',
            json_encode(['metric' => 'visits']),
            8,
            4
        );

        $this->assertTrue($response['success']);
        $this->assertEquals($widgetId, $response['widget_id']);

        $dashboard = $this->dashboardService->getDashboard($this->testDashboardId);
        $widget = $dashboard['widgets'][0];
        $this->assertEquals('trends_chart', $widget['type']);
        $this->assertEquals(8, $widget['width']);
    }

    /**
     * Test: Remove widget via API
     */
    public function testRemoveWidgetAPI()
    {
        $widgetId = $this->dashboardService->addWidget(
            $this->testDashboardId,
            'key_metrics',
            [],
            0
        );

        $response = $this->dashboardAPI->removeWidget($widgetId);

        $this->assertTrue($response['success']);
        $this->assertEquals($widgetId, $response['widget_id']);

        $dashboard = $this->dashboardService->getDashboard($this->testDashboardId);
        $this->assertCount(0, $dashboard['widgets']);
    }

    /**
     * Test: Duplicate dashboard via API
     */
    public function testDuplicateDashboardAPI()
    {
        $this->dashboardService->addWidget($this->testDashboardId, 'key_metrics', [], 0);

        $response = $this->dashboardAPI->duplicate(
            $this->testDashboardId,
            ' (Copy)'
        );

        $this->assertTrue($response['success']);
        $this->assertEquals($this->testDashboardId, $response['original_dashboard_id']);
        $this->assertArrayHasKey('new_dashboard_id', $response);

        $newDashboard = $this->dashboardService->getDashboard($response['new_dashboard_id']);
        $this->assertStringContainsString('Copy', $newDashboard['name']);
        $this->assertCount(1, $newDashboard['widgets']);
    }

    /**
     * Test: Share dashboard via API
     */
    public function testShareDashboardAPI()
    {
        $response = $this->dashboardAPI->share(
            $this->testDashboardId,
            2,
            true
        );

        $this->assertTrue($response['success']);
        $this->assertEquals($this->testDashboardId, $response['dashboard_id']);
        $this->assertEquals(2, $response['target_user_id']);
    }

    /**
     * Test: Search dashboards via API
     */
    public function testSearchDashboardsAPI()
    {
        $this->dashboardService->createDashboard(
            $this->testUserId,
            'Sales Dashboard',
            'Monthly sales data',
            []
        );

        $response = $this->dashboardAPI->search('Sales', 10);

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('dashboards', $response);
        $this->assertArrayHasKey('total', $response);
        $this->assertEquals('Sales', $response['query']);
        $this->assertGreaterThan(0, $response['total']);
    }

    /**
     * Test: Get templates via API
     */
    public function testGetTemplatesAPI()
    {
        $response = $this->dashboardAPI->getTemplates();

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('templates', $response);
        $this->assertArrayHasKey('total', $response);
        $this->assertGreaterThan(0, $response['total']);
        $this->assertArrayHasKey('id', $response['templates'][0]);
        $this->assertArrayHasKey('name', $response['templates'][0]);
    }

    /**
     * Test: Create dashboard from template via API
     */
    public function testCreateFromTemplateAPI()
    {
        $templates = $this->dashboardService->getTemplates(1);
        $templateId = $templates[0]['id'];

        $response = $this->dashboardAPI->createFromTemplate(
            $templateId,
            'Dashboard from Template'
        );

        $this->assertTrue($response['success']);
        $this->assertEquals($templateId, $response['template_id']);
        $this->assertArrayHasKey('dashboard_id', $response);

        $dashboard = $this->dashboardService->getDashboard($response['dashboard_id']);
        $this->assertEquals('Dashboard from Template', $dashboard['name']);
        $this->assertGreaterThan(0, count($dashboard['widgets']));
    }

    /**
     * Test: Get dashboard statistics via API
     */
    public function testGetStatsAPI()
    {
        // Create multiple dashboards with widgets
        for ($i = 0; $i < 3; $i++) {
            $dashboardId = $this->dashboardService->createDashboard(
                $this->testUserId,
                "Dashboard $i",
                '',
                []
            );
            $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], 0);
        }

        $response = $this->dashboardAPI->getStats();

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('stats', $response);
        $this->assertArrayHasKey('total_dashboards', $response['stats']);
        $this->assertArrayHasKey('total_widgets', $response['stats']);
        $this->assertGreaterThan(0, $response['stats']['total_dashboards']);
    }

    /**
     * Test: API response format consistency
     */
    public function testAPIResponseFormat()
    {
        $response = $this->dashboardAPI->get($this->testDashboardId);

        // All responses should have 'success' field
        $this->assertArrayHasKey('success', $response);
        $this->assertIsBool($response['success']);

        // Error responses should have 'error' or 'message'
        if (!$response['success']) {
            $this->assertTrue(
                isset($response['error']) || isset($response['message']),
                'Failed responses should have error or message'
            );
        }
    }

    /**
     * Test: API handles invalid dashboard ID
     */
    public function testAPIInvalidDashboardId()
    {
        $response = $this->dashboardAPI->get(99999);

        $this->assertFalse($response['success']);
    }

    /**
     * Test: API handles invalid widget ID
     */
    public function testAPIInvalidWidgetId()
    {
        $response = $this->dashboardAPI->removeWidget(99999);

        $this->assertFalse($response['success']);
    }

    /**
     * Test: Multiple API calls don't interfere
     */
    public function testMultipleAPICalls()
    {
        $response1 = $this->dashboardAPI->getList(10, 0);
        $response2 = $this->dashboardAPI->getTemplates();
        $response3 = $this->dashboardAPI->search('test', 10);

        $this->assertTrue($response1['success']);
        $this->assertTrue($response2['success']);
        $this->assertTrue($response3['success']);
    }

    /**
     * Test: Widget creation via API with full config
     */
    public function testWidgetAPIWithFullConfig()
    {
        $config = [
            'segment_id' => 1,
            'metric' => 'visits',
            'date_range' => 'last_30_days',
            'filters' => ['country' => 'DE']
        ];

        $response = $this->dashboardAPI->addWidget(
            $this->testDashboardId,
            'trends_chart',
            json_encode($config)
        );

        $this->assertTrue($response['success']);

        $dashboard = $this->dashboardService->getDashboard($this->testDashboardId);
        $widget = $dashboard['widgets'][0];
        $this->assertEquals('trends_chart', $widget['type']);
        $this->assertEquals($config['segment_id'], $widget['config']['segment_id']);
    }

    /**
     * Test: Dashboard update preserves existing data
     */
    public function testDashboardUpdatePreservesData()
    {
        // Create dashboard with config
        $dashboardId = $this->dashboardService->createDashboard(
            $this->testUserId,
            'Original',
            'Original Description',
            ['theme' => 'dark', 'auto_refresh' => true]
        );

        // Add widget
        $this->dashboardService->addWidget($dashboardId, 'key_metrics', [], 0);

        // Update via API
        $this->dashboardAPI->update(
            $dashboardId,
            'Updated Name',
            'Updated Description',
            json_encode(['theme' => 'light'])
        );

        $dashboard = $this->dashboardService->getDashboard($dashboardId);
        $this->assertEquals('Updated Name', $dashboard['name']);
        $this->assertCount(1, $dashboard['widgets']);
    }

    /**
     * Test: Concurrent widget operations
     */
    public function testConcurrentWidgetOperations()
    {
        $dashboard = $this->dashboardService->getDashboard($this->testDashboardId);
        $initialCount = count($dashboard['widgets']);

        // Add multiple widgets via API
        for ($i = 0; $i < 5; $i++) {
            $this->dashboardAPI->addWidget(
                $this->testDashboardId,
                'key_metrics',
                json_encode(['index' => $i])
            );
        }

        $dashboard = $this->dashboardService->getDashboard($this->testDashboardId);
        $this->assertEquals($initialCount + 5, count($dashboard['widgets']));
    }
}
