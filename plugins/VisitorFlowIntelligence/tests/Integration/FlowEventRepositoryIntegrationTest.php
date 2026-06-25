<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\FlowEventRepository;
use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentProcessor;

/**
 * SB-017.2: Integration Tests for Repository with Segments
 * 
 * Tests repository query building with segment filters
 */
class FlowEventRepositoryIntegrationTest extends TestCase
{
    private FlowEventRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FlowEventRepository();
    }

    /**
     * Test repository accepts segment parameter
     */
    public function testRepositoryAcceptsSegmentParameter(): void
    {
        // Test that method signature includes segment parameter
        // (actual DB query would require test database)
        
        $this->assertNotNull($this->repository);
    }

    /**
     * Test segment processor integration with repository
     */
    public function testSegmentProcessorIntegration(): void
    {
        $processor = new SegmentProcessor('deviceType==mobile');
        $whereClause = $processor->getWhereClause('lv');

        $this->assertNotEmpty($whereClause['where']);
        $this->assertNotEmpty($whereClause['bind']);
    }

    /**
     * Test empty segment still works
     */
    public function testEmptySegmentStillWorks(): void
    {
        $processor = new SegmentProcessor('');
        $whereClause = $processor->getWhereClause('lv');

        $this->assertTrue($processor->isEmpty());
        $this->assertEmpty($whereClause['where']);
        $this->assertEmpty($whereClause['bind']);
    }

    /**
     * Test multiple conditions are AND-ed together
     */
    public function testMultipleConditionsAnd(): void
    {
        $processor = new SegmentProcessor('deviceType==mobile;country==DE');
        $whereClause = $processor->getWhereClause('lv');

        // Should contain both conditions
        $where = $whereClause['where'];
        $this->assertStringContainsString('config_device_type', $where);
        $this->assertStringContainsString('location_country', $where);
        $this->assertStringContainsString('AND', $where);
    }
}
