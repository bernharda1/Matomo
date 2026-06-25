<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\SegmentRepository;

/**
 * SB-021.1: SegmentBuilder
 * 
 * Enables users to create custom visitor segments with visual builder UI
 * Supports complex rules with AND/OR logic, presets, and sharing
 */
class SegmentBuilder
{
    private SegmentRepository $repository;
    private int $siteId;
    private ?int $userId;

    /**
     * Constructor
     */
    public function __construct(
        SegmentRepository $repository,
        int $siteId,
        ?int $userId = null
    ) {
        SecurityValidator::validateSiteId($siteId);
        $this->repository = $repository;
        $this->siteId = $siteId;
        $this->userId = $userId;
    }

    /**
     * Create new segment from rules
     */
    public function createSegment(
        string $name,
        string $description,
        array $rules,
        string $operator = 'AND',
        bool $isPublic = false
    ): array {
        // Validate input
        if (empty($name) || strlen($name) > 255) {
            throw new \Exception("Segment name must be 1-255 characters");
        }

        if (strlen($description) > 1000) {
            throw new \Exception("Description must be 1000 characters or less");
        }

        if (empty($rules)) {
            throw new \Exception("At least one rule is required");
        }

        // Validate rules
        foreach ($rules as $rule) {
            $this->validateRule($rule);
        }

        // Validate operator
        if (!in_array($operator, ['AND', 'OR'])) {
            throw new \Exception("Operator must be AND or OR");
        }

        // Build segment query
        $query = $this->buildSegmentQuery($rules, $operator);

        // Save segment
        $segmentId = $this->repository->save(
            siteId: $this->siteId,
            userId: $this->userId,
            name: $name,
            description: $description,
            query: $query,
            rules: $rules,
            operator: $operator,
            isPublic: $isPublic
        );

        return [
            'segment_id' => $segmentId,
            'name' => $name,
            'query' => $query,
            'rule_count' => count($rules),
            'created_at' => time(),
        ];
    }

    /**
     * Update existing segment
     */
    public function updateSegment(
        int $segmentId,
        ?string $name = null,
        ?string $description = null,
        ?array $rules = null,
        ?string $operator = null,
        ?bool $isPublic = null
    ): array {
        $segment = $this->repository->getById($segmentId, $this->siteId);
        if (!$segment) {
            throw new \Exception("Segment not found");
        }

        // Validate updates
        if ($name && strlen($name) > 255) {
            throw new \Exception("Segment name must be 1-255 characters");
        }

        if ($description && strlen($description) > 1000) {
            throw new \Exception("Description must be 1000 characters or less");
        }

        if ($rules) {
            foreach ($rules as $rule) {
                $this->validateRule($rule);
            }
            $operator = $operator ?? 'AND';
            $query = $this->buildSegmentQuery($rules, $operator);
        }

        $updated = $this->repository->update(
            segmentId: $segmentId,
            name: $name,
            description: $description,
            rules: $rules,
            operator: $operator,
            query: $query ?? null,
            isPublic: $isPublic
        );

        return [
            'segment_id' => $segmentId,
            'updated' => $updated,
            'updated_at' => time(),
        ];
    }

    /**
     * Delete segment
     */
    public function deleteSegment(int $segmentId): array
    {
        $segment = $this->repository->getById($segmentId, $this->siteId);
        if (!$segment) {
            throw new \Exception("Segment not found");
        }

        $deleted = $this->repository->delete($segmentId);

        return [
            'segment_id' => $segmentId,
            'deleted' => $deleted,
            'deleted_at' => time(),
        ];
    }

    /**
     * Get all segments for site
     */
    public function getSegments(?bool $onlyPublic = false): array
    {
        return $this->repository->getAll(
            siteId: $this->siteId,
            userId: $onlyPublic ? null : $this->userId,
            onlyPublic: $onlyPublic
        );
    }

    /**
     * Get segment by ID
     */
    public function getSegment(int $segmentId): ?array
    {
        return $this->repository->getById($segmentId, $this->siteId);
    }

    /**
     * Get preset segments
     */
    public function getPresets(): array
    {
        return $this->repository->getPresets($this->siteId);
    }

    /**
     * Share segment with user
     */
    public function shareSegment(int $segmentId, int $targetUserId, string $permission = 'read'): array
    {
        $segment = $this->repository->getById($segmentId, $this->siteId);
        if (!$segment) {
            throw new \Exception("Segment not found");
        }

        if (!in_array($permission, ['read', 'write', 'admin'])) {
            throw new \Exception("Invalid permission level");
        }

        $this->repository->share(
            segmentId: $segmentId,
            userId: $targetUserId,
            permission: $permission
        );

        return [
            'segment_id' => $segmentId,
            'shared_with' => $targetUserId,
            'permission' => $permission,
        ];
    }

    /**
     * Get segment usage analytics
     */
    public function getSegmentUsage(int $segmentId): array
    {
        $segment = $this->repository->getById($segmentId, $this->siteId);
        if (!$segment) {
            throw new \Exception("Segment not found");
        }

        return [
            'segment_id' => $segmentId,
            'uses' => $this->repository->countUsages($segmentId),
            'last_used' => $this->repository->getLastUsed($segmentId),
            'shared_with' => $this->repository->countShares($segmentId),
        ];
    }

    /**
     * Validate individual rule
     */
    private function validateRule(array $rule): void
    {
        if (!isset($rule['field'])) {
            throw new \Exception("Rule must have a 'field'");
        }

        if (!isset($rule['operator'])) {
            throw new \Exception("Rule must have an 'operator'");
        }

        if (!isset($rule['value'])) {
            throw new \Exception("Rule must have a 'value'");
        }

        $validFields = [
            'deviceType', 'country', 'browserName', 'osName',
            'referrerType', 'searchKeyword', 'customVariable',
            'visitorId', 'visitorType', 'visitDuration',
            'actionCount', 'goalConversions'
        ];

        if (!in_array($rule['field'], $validFields)) {
            throw new \Exception("Invalid field: {$rule['field']}");
        }

        $validOperators = ['==', '!=', 'contains', 'not_contains', '>', '<', '>=', '<=', 'in', 'not_in'];
        if (!in_array($rule['operator'], $validOperators)) {
            throw new \Exception("Invalid operator: {$rule['operator']}");
        }
    }

    /**
     * Build segment query from rules
     */
    private function buildSegmentQuery(array $rules, string $operator): string
    {
        $query_parts = [];

        foreach ($rules as $rule) {
            $field = $rule['field'];
            $op = $rule['operator'];
            $value = $rule['value'];

            // Map field to segment DSL
            $query_parts[] = $this->buildRuleQuery($field, $op, $value);
        }

        return implode(";{$operator};", $query_parts);
    }

    /**
     * Build individual rule query
     */
    private function buildRuleQuery(string $field, string $operator, $value): string
    {
        // Simple mapping - can be extended
        $fieldMap = [
            'deviceType' => 'deviceType',
            'country' => 'countryCode',
            'browserName' => 'browserName',
        ];

        $mappedField = $fieldMap[$field] ?? $field;

        // Build rule
        return "{$mappedField}{$operator}{$value}";
    }
}
