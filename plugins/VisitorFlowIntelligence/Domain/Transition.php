<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Domain;

final class Transition
{
    public function __construct(
        private string $sourceStepId,
        private string $targetStepId,
        private int $visits,
        private float $transitionRate = 0.0
    ) {
        if ($this->sourceStepId === '' || $this->targetStepId === '') {
            throw new \DomainException('Transition step ids must not be empty.');
        }

        if ($this->sourceStepId === $this->targetStepId) {
            throw new \DomainException('Transition source and target must differ.');
        }

        if ($this->visits < 0) {
            throw new \DomainException('Transition visits must be >= 0.');
        }

        if ($this->transitionRate < 0.0 || $this->transitionRate > 1.0) {
            throw new \DomainException('Transition rate must be between 0 and 1.');
        }
    }

    public function toArray(): array
    {
        return [
            'sourceStepId' => $this->sourceStepId,
            'targetStepId' => $this->targetStepId,
            'visits' => $this->visits,
            'transitionRate' => $this->transitionRate,
        ];
    }
}
