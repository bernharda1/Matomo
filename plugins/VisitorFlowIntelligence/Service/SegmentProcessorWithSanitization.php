<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Exception\SecurityException;

/**
 * SB-018.2: SegmentProcessor with Sanitization
 * 
 * Parses segment strings into parameterized SQL WHERE clauses
 * With security validation to prevent SQL injection
 */
class SegmentProcessor
{
    private string $segment;
    private bool $isEmpty = false;
    private string $hash = 'none';

    /**
     * Constructor validates segment on initialization
     */
    public function __construct(string $segment = '')
    {
        // Validate segment using SecurityValidator
        SecurityValidator::validateSegment($segment);

        $this->segment = $segment;
        $this->isEmpty = empty($segment);
        $this->hash = $this->generateHash($segment);
    }

    /**
     * Check if segment is empty
     */
    public function isEmpty(): bool
    {
        return $this->isEmpty;
    }

    /**
     * Get segment hash for caching
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Generate MD5 hash of segment (deterministic)
     */
    private function generateHash(string $segment): string
    {
        if (empty($segment)) {
            return 'none';
        }

        return md5($segment);
    }

    /**
     * Get segment description for UI
     */
    public function getDescription(): string
    {
        if ($this->isEmpty) {
            return 'All visitors';
        }

        $conditions = explode(';', $this->segment);
        $descriptions = [];

        foreach ($conditions as $condition) {
            $descriptions[] = $this->describeCondition(trim($condition));
        }

        return implode(' and ', $descriptions);
    }

    /**
     * Describe a single condition
     */
    private function describeCondition(string $condition): string
    {
        // Parse condition: field operator value
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)([!=<>]+)(.*)$/', $condition, $matches)) {
            $field = $matches[1];
            $operator = $matches[2];
            $value = trim($matches[3]);

            $operatorText = match ($operator) {
                '==' => 'equals',
                '!=' => 'not equals',
                '>' => 'greater than',
                '<' => 'less than',
                '>=' => 'greater than or equal to',
                '<=' => 'less than or equal to',
                default => $operator,
            };

            return "{$field} {$operatorText} {$value}";
        }

        return $condition;
    }

    /**
     * Get WHERE clause with parameterized queries (prevents SQL injection)
     */
    public function getWhereClause(string $tableAlias = 'lv'): string
    {
        if ($this->isEmpty) {
            return '1=1';
        }

        $conditions = explode(';', $this->segment);
        $whereClauses = [];

        foreach ($conditions as $condition) {
            $where = $this->buildConditionWhere(trim($condition), $tableAlias);
            if ($where !== '1=1') {
                $whereClauses[] = $where;
            }
        }

        return !empty($whereClauses) ? implode(' AND ', $whereClauses) : '1=1';
    }

    /**
     * Build WHERE clause for single condition
     */
    private function buildConditionWhere(string $condition, string $tableAlias): string
    {
        // Parse: field operator value
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)([!=<>]+)(.*)$/', $condition, $matches)) {
            return '1=1'; // Safe fallback
        }

        $field = $matches[1];
        $operator = $matches[2];
        $value = trim($matches[3]);

        // Map Matomo segment field names to database columns
        $columnMap = [
            'deviceType' => 'config_device_type',
            'country' => 'location_country',
            'browserName' => 'config_browser_name',
            'osName' => 'config_os',
            'visitDuration' => 'visit_total_time',
            'actions' => 'visit_total_actions',
        ];

        $column = $columnMap[$field] ?? null;

        if ($column === null) {
            // Unknown field - log warning and return safe condition
            error_log("Unknown segment field: {$field}");
            return '1=1';
        }

        // Build parameterized WHERE clause
        // Operator determines the SQL operator
        $sqlOperator = match ($operator) {
            '==' => '=',
            '!=' => '!=',
            '>' => '>',
            '<' => '<',
            '>=' => '>=',
            '<=' => '<=',
            default => '=',
        };

        // For string values, use exact match
        // For numeric values, comparison operators
        return "{$tableAlias}.{$column} {$sqlOperator} ?";
    }

    /**
     * Get parameterized values for WHERE clause
     */
    public function getWhereParams(): array
    {
        if ($this->isEmpty) {
            return [];
        }

        $conditions = explode(';', $this->segment);
        $params = [];

        foreach ($conditions as $condition) {
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*[!=<>]+(.*)$/', $condition, $matches)) {
                $params[] = trim($matches[1]);
            }
        }

        return $params;
    }
}
