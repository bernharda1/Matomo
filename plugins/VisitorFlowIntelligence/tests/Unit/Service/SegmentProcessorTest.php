<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentProcessor;

/**
 * SB-017.1: Unit Tests for SegmentProcessor
 * 
 * Tests segment parsing, WHERE clause generation, and cache key creation
 */
class SegmentProcessorTest extends TestCase
{
    private SegmentProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new SegmentProcessor();
    }

    /**
     * Test empty segment handling
     */
    public function testEmptySegment(): void
    {
        $processor = new SegmentProcessor('');
        $this->assertTrue($processor->isEmpty());
        $this->assertEquals('none', $processor->getSegmentHash());
    }

    /**
     * Test single segment parsing: deviceType==mobile
     */
    public function testSingleSegmentDeviceType(): void
    {
        $processor = new SegmentProcessor('deviceType==mobile');
        
        $whereClause = $processor->getWhereClause('lv');
        
        $this->assertFalse($processor->isEmpty());
        $this->assertNotEmpty($whereClause['where']);
        $this->assertStringContainsString('config_device_type', $whereClause['where']);
        $this->assertContains('mobile', $whereClause['bind']);
    }

    /**
     * Test segment with country code
     */
    public function testSegmentCountry(): void
    {
        $processor = new SegmentProcessor('country==DE');
        
        $whereClause = $processor->getWhereClause('lv');
        
        $this->assertStringContainsString('location_country', $whereClause['where']);
        $this->assertContains('DE', $whereClause['bind']);
    }

    /**
     * Test numeric operator: visitDuration>300
     */
    public function testSegmentNumericOperator(): void
    {
        $processor = new SegmentProcessor('visitDuration>300');
        
        $whereClause = $processor->getWhereClause('lv');
        
        $this->assertStringContainsString('visit_total_time', $whereClause['where']);
        $this->assertStringContainsString('>', $whereClause['where']);
        $this->assertContains(300, $whereClause['bind']);
    }

    /**
     * Test NOT EQUAL operator: browserName!=Safari
     */
    public function testSegmentNotEqual(): void
    {
        $processor = new SegmentProcessor('browserName!=Safari');
        
        $whereClause = $processor->getWhereClause('lv');
        
        $this->assertStringContainsString('config_browser_name', $whereClause['where']);
        $this->assertStringContainsString('!=', $whereClause['where']);
        $this->assertContains('Safari', $whereClause['bind']);
    }

    /**
     * Test multiple segments: country==DE;deviceType==mobile
     */
    public function testMultipleSegments(): void
    {
        $processor = new SegmentProcessor('country==DE;deviceType==mobile');
        
        $whereClause = $processor->getWhereClause('lv');
        
        $this->assertStringContainsString('location_country', $whereClause['where']);
        $this->assertStringContainsString('config_device_type', $whereClause['where']);
        $this->assertContains('DE', $whereClause['bind']);
        $this->assertContains('mobile', $whereClause['bind']);
    }

    /**
     * Test segment hash consistency
     */
    public function testSegmentHashConsistency(): void
    {
        $processor1 = new SegmentProcessor('deviceType==mobile');
        $processor2 = new SegmentProcessor('deviceType==mobile');
        $processor3 = new SegmentProcessor('deviceType==desktop');
        
        $this->assertEquals($processor1->getSegmentHash(), $processor2->getSegmentHash());
        $this->assertNotEquals($processor1->getSegmentHash(), $processor3->getSegmentHash());
    }

    /**
     * Test unknown segment field is skipped
     */
    public function testUnknownSegmentFieldSkipped(): void
    {
        $processor = new SegmentProcessor('unknownField==value');
        
        $whereClause = $processor->getWhereClause('lv');
        
        // Unknown field should result in safe fallback
        $this->assertStringContainsString('1=1', $whereClause['where']);
    }

    /**
     * Test segment description generation
     */
    public function testSegmentDescription(): void
    {
        $processor = new SegmentProcessor('deviceType==mobile;country==DE');
        
        $description = $processor->getDescription();
        
        $this->assertStringContainsString('mobile', strtolower($description));
        $this->assertStringContainsString('DE', $description);
    }

    /**
     * Test table alias in WHERE clause
     */
    public function testTableAlias(): void
    {
        $processor = new SegmentProcessor('deviceType==mobile');
        
        $whereClauseDefault = $processor->getWhereClause('lv');
        $whereClauseCustom = $processor->getWhereClause('log_visit');
        
        $this->assertStringContainsString('lv.', $whereClauseDefault['where']);
        $this->assertStringContainsString('log_visit.', $whereClauseCustom['where']);
    }

    /**
     * Test greater than or equal operator
     */
    public function testGreaterThanOrEqual(): void
    {
        $processor = new SegmentProcessor('actions>=5');
        
        $whereClause = $processor->getWhereClause('lv');
        
        $this->assertStringContainsString('>=', $whereClause['where']);
        $this->assertContains(5, $whereClause['bind']);
    }

    /**
     * Test less than operator
     */
    public function testLessThan(): void
    {
        $processor = new SegmentProcessor('visitDuration<60');
        
        $whereClause = $processor->getWhereClause('lv');
        
        $this->assertStringContainsString('<', $whereClause['where']);
        $this->assertContains(60, $whereClause['bind']);
    }
}
