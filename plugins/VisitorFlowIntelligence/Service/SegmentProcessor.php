<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Segment;
use Piwik\SegmentExpression;

/**
 * SB-016: Segment Support for Visitor Flow Intelligence
 * 
 * Enables filtering of visitor paths by segments:
 * - Device type: desktop, mobile, tablet
 * - Browser: Chrome, Firefox, Safari, Edge
 * - Country: DE, AT, US, etc.
 * - OS: Windows, macOS, Linux, iOS, Android
 * - Referrer type: search engine, social, direct
 * - Visit duration: > 300 seconds, < 60 seconds
 * - Actions: >= 5, < 10
 * 
 * Example:
 *   deviceType==mobile;browserName==Chrome
 *   → All mobile Chrome visitors
 * 
 *   country==DE;visitDuration>300
 *   → German visitors with > 5 min sessions
 * 
 * Segment syntax:
 *   metric==value          Exact match
 *   metric!=value          Not equal
 *   metric>value           Greater than (numeric)
 *   metric<value           Less than (numeric)
 *   metric>=value          Greater or equal
 *   metric<=value          Less or equal
 *   segment1;segment2      AND logic
 *   segment1,segment2      OR logic
 */
class SegmentProcessor
{
    private Segment $segment;

    public function __construct(string $segmentString = '')
    {
        if (empty($segmentString)) {
            $this->segment = new Segment('', null);
        } else {
            $this->segment = new Segment($segmentString, null);
        }
    }

    /**
     * Check if segment is empty (no filtering applied)
     */
    public function isEmpty(): bool
    {
        return empty($this->segment->getSegmentString());
    }

    /**
     * Get SQL WHERE clause for segment filtering
     * 
     * Used in repository queries to filter raw data
     * 
     * @param string $tableAlias Alias for log_visit table (e.g., 'lv')
     * @return array ['where' => 'WHERE clause string', 'bind' => [values]]
     */
    public function getWhereClause(string $tableAlias = 'log_visit'): array
    {
        if ($this->isEmpty()) {
            return ['where' => '', 'bind' => []];
        }

        // Parse segment string into conditions
        // Example: "deviceType==mobile;browserName==Chrome"
        // Result: [
        //   ['field' => 'deviceType', 'operator' => '==', 'value' => 'mobile'],
        //   ['field' => 'browserName', 'operator' => '==', 'value' => 'Chrome']
        // ]

        $conditions = $this->parseSegment($this->segment->getSegmentString());
        $whereParts = [];
        $bindings = [];

        foreach ($conditions as $condition) {
            [$where, $bind] = $this->buildCondition($condition, $tableAlias);
            $whereParts[] = $where;
            $bindings = array_merge($bindings, $bind);
        }

        if (empty($whereParts)) {
            return ['where' => '', 'bind' => []];
        }

        // Join with AND (semicolon) or OR (comma)
        $whereClause = 'WHERE ' . implode(' AND ', $whereParts);

        return ['where' => $whereClause, 'bind' => $bindings];
    }

    /**
     * Parse segment string into array of conditions
     * 
     * Simple parser for common segment patterns
     * Full Matomo segment parser is more complex
     */
    private function parseSegment(string $segmentString): array
    {
        $conditions = [];

        // Split by semicolon (AND logic)
        $parts = explode(';', $segmentString);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            // Try to match operators
            $operators = ['!=', '>=', '<=', '==', '>', '<', '='];

            foreach ($operators as $op) {
                if (strpos($part, $op) !== false) {
                    [$field, $value] = explode($op, $part, 2);
                    $conditions[] = [
                        'field' => trim($field),
                        'operator' => $op,
                        'value' => trim($value),
                    ];
                    break;
                }
            }
        }

        return $conditions;
    }

    /**
     * Build SQL condition from segment condition
     */
    private function buildCondition(array $condition, string $tableAlias): array
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Map segment field names to database columns
        $fieldMap = [
            'deviceType' => 'config_device_type',
            'browserName' => 'config_browser_name',
            'browserVersion' => 'config_browser_version',
            'country' => 'location_country',
            'city' => 'location_city',
            'osName' => 'config_os',
            'osVersion' => 'config_os_version',
            'visitDuration' => 'visit_total_time',
            'actions' => 'visit_total_actions',
        ];

        if (!isset($fieldMap[$field])) {
            // Unknown field, skip this condition
            return ['1=1', []];
        }

        $dbColumn = $tableAlias . '.' . $fieldMap[$field];

        // Normalize operator
        $operator = match ($operator) {
            '=' => '==',
            default => $operator,
        };

        // Build SQL condition based on operator
        return match ($operator) {
            '==' => [
                "$dbColumn = ?",
                [$value],
            ],
            '!=' => [
                "$dbColumn != ?",
                [$value],
            ],
            '>' => [
                "$dbColumn > ?",
                [(int)$value],
            ],
            '<' => [
                "$dbColumn < ?",
                [(int)$value],
            ],
            '>=' => [
                "$dbColumn >= ?",
                [(int)$value],
            ],
            '<=' => [
                "$dbColumn <= ?",
                [(int)$value],
            ],
            default => ['1=1', []],
        };
    }

    /**
     * Get segment string (for caching key)
     */
    public function getSegmentHash(): string
    {
        if ($this->isEmpty()) {
            return 'none';
        }

        return md5($this->segment->getSegmentString());
    }

    /**
     * Get human-readable segment description
     * 
     * Example: "Mobile Chrome visitors from Germany"
     */
    public function getDescription(): string
    {
        if ($this->isEmpty()) {
            return 'All visitors';
        }

        $segments = [];
        $conditions = $this->parseSegment($this->segment->getSegmentString());

        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $value = $condition['value'];
            $operator = $condition['operator'];

            $description = match ($field) {
                'deviceType' => "Device: $value",
                'browserName' => "Browser: $value",
                'country' => "Country: $value",
                'osName' => "OS: $value",
                'visitDuration' => "Duration $operator $value sec",
                'actions' => "Actions $operator $value",
                default => "$field $operator $value",
            };

            $segments[] = $description;
        }

        return implode(', ', $segments);
    }
}
