<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Domain;

final class Dropoff
{
    public function __construct(
        private string $stepId,
        private int $dropoffCount,
        private float $dropoffRate = 0.0
    ) {
        if ($this->stepId === '') {
            throw new \DomainException('Dropoff step id must not be empty.');
        }

        if ($this->dropoffCount < 0) {
            throw new \DomainException('Dropoff count must be >= 0.');
        }

        if ($this->dropoffRate < 0.0 || $this->dropoffRate > 1.0) {
            throw new \DomainException('Dropoff rate must be between 0 and 1.');
        }
    }

    public function toArray(): array
    {
        return [
            'stepId' => $this->stepId,
            'dropoffCount' => $this->dropoffCount,
            'dropoffRate' => $this->dropoffRate,
        ];
    }
}
