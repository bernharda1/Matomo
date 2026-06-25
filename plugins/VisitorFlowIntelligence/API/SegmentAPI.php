<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\API;

use Piwik\Plugins\VisitorFlowIntelligence\Service\SegmentBuilder;
use Piwik\Plugins\VisitorFlowIntelligence\Infrastructure\SegmentRepository;

/**
 * SB-021.2: SegmentAPI
 * 
 * Public API for segment management endpoints
 */
class SegmentAPI
{
    private SegmentBuilder $builder;
    private SegmentRepository $repository;

    /**
     * Constructor
     */
    public function __construct(
        SegmentBuilder $builder,
        SegmentRepository $repository
    ) {
        $this->builder = $builder;
        $this->repository = $repository;
    }

    /**
     * Create new segment
     */
    public function createSegment(
        int $idSite,
        string $name,
        string $description,
        array $rules,
        string $operator = 'AND',
        bool $isPublic = false
    ): array {
        return $this->builder->createSegment(
            name: $name,
            description: $description,
            rules: $rules,
            operator: $operator,
            isPublic: $isPublic
        );
    }

    /**
     * Update segment
     */
    public function updateSegment(
        int $idSite,
        int $segmentId,
        ?string $name = null,
        ?string $description = null,
        ?array $rules = null,
        ?string $operator = null,
        ?bool $isPublic = null
    ): array {
        return $this->builder->updateSegment(
            segmentId: $segmentId,
            name: $name,
            description: $description,
            rules: $rules,
            operator: $operator,
            isPublic: $isPublic
        );
    }

    /**
     * Delete segment
     */
    public function deleteSegment(int $idSite, int $segmentId): array
    {
        return $this->builder->deleteSegment($segmentId);
    }

    /**
     * Get all segments
     */
    public function getSegments(int $idSite, bool $onlyPublic = false): array
    {
        return $this->builder->getSegments($onlyPublic);
    }

    /**
     * Get segment details
     */
    public function getSegment(int $idSite, int $segmentId): ?array
    {
        return $this->builder->getSegment($segmentId);
    }

    /**
     * Get preset segments
     */
    public function getPresetSegments(int $idSite): array
    {
        return $this->builder->getPresets();
    }

    /**
     * Share segment
     */
    public function shareSegment(
        int $idSite,
        int $segmentId,
        int $userId,
        string $permission = 'read'
    ): array {
        return $this->builder->shareSegment($segmentId, $userId, $permission);
    }

    /**
     * Get segment usage analytics
     */
    public function getSegmentUsage(int $idSite, int $segmentId): array
    {
        return $this->builder->getSegmentUsage($segmentId);
    }
}
