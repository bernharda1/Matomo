<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Domain;

final class FlowQuery
{
    private const ALLOWED_PERIODS = ['day', 'week', 'month', 'year', 'range'];

    public function __construct(
        private int $idSite,
        private string $period,
        private string $date,
        private ?string $segment = null,
        private int $maxDepth = 5,
        private int $limit = 20
    ) {
        if ($this->idSite <= 0) {
            throw new \DomainException('FlowQuery idSite must be > 0.');
        }

        if (!in_array($this->period, self::ALLOWED_PERIODS, true)) {
            throw new \DomainException('FlowQuery period is invalid.');
        }

        if ($this->date === '') {
            throw new \DomainException('FlowQuery date must not be empty.');
        }

        if ($this->maxDepth < 2 || $this->maxDepth > 12) {
            throw new \DomainException('FlowQuery maxDepth must be in range 2..12.');
        }

        if ($this->limit < 1 || $this->limit > 200) {
            throw new \DomainException('FlowQuery limit must be in range 1..200.');
        }
    }

    public function toArray(): array
    {
        return [
            'idSite' => $this->idSite,
            'period' => $this->period,
            'date' => $this->date,
            'segment' => $this->segment,
            'maxDepth' => $this->maxDepth,
            'limit' => $this->limit,
        ];
    }

    public function getIdSite(): int
    {
        return $this->idSite;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getSegment(): ?string
    {
        return $this->segment;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
