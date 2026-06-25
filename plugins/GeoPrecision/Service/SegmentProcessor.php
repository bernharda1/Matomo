<?php

declare(strict_types=1);

namespace Piwik\Plugins\GeoPrecision\Service;

/**
 * SB-016: Segment Support for GeoPrecision
 * 
 * Enables filtering of geographic data by segments:
 * - Device type, browser, country, OS, visit duration, actions
 */
class SegmentProcessor
{
    private string $segmentString;

    public function __construct(string $segmentString = '')
    {
        $this->segmentString = $segmentString;
    }

    public function isEmpty(): bool
    {
        return empty($this->segmentString);
    }

    public function getWhereClause(string $tableAlias = 'log_visit'): array
    {
        if ($this->isEmpty()) {
            return ['where' => '', 'bind' => []];
        }

        // Similar implementation to VisitorFlowIntelligence
        $conditions = $this->parseSegment($this->segmentString);
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

        return ['where' => 'WHERE ' . implode(' AND ', $whereParts), 'bind' => $bindings];
    }

    public function getSegmentHash(): string
    {
        return empty($this->segmentString) ? 'none' : md5($this->segmentString);
    }

    private function parseSegment(string $segmentString): array
    {
        $conditions = [];
        $parts = explode(';', $segmentString);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

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

    private function buildCondition(array $condition, string $tableAlias): array
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        $fieldMap = [
            'deviceType' => 'config_device_type',
            'browserName' => 'config_browser_name',
            'country' => 'location_country',
            'osName' => 'config_os',
            'visitDuration' => 'visit_total_time',
            'actions' => 'visit_total_actions',
        ];

        if (!isset($fieldMap[$field])) {
            return ['1=1', []];
        }

        $dbColumn = $tableAlias . '.' . $fieldMap[$field];
        $operator = $operator === '=' ? '==' : $operator;

        return match ($operator) {
            '==' => ["$dbColumn = ?", [$value]],
            '!=' => ["$dbColumn != ?", [$value]],
            '>' => ["$dbColumn > ?", [(int)$value]],
            '<' => ["$dbColumn < ?", [(int)$value]],
            '>=' => ["$dbColumn >= ?", [(int)$value]],
            '<=' => ["$dbColumn <= ?", [(int)$value]],
            default => ['1=1', []],
        };
    }
}
