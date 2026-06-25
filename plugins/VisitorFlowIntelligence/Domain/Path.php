<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Domain;

final class Path
{
    /**
     * @param array<int, string> $steps
     */
    public function __construct(
        private array $steps,
        private int $visits,
        private float $share = 0.0,
        private ?int $dropoffAtStep = null
    ) {
        if ($this->steps === []) {
            throw new \DomainException('Path steps must not be empty.');
        }

        if ($this->visits < 0) {
            throw new \DomainException('Path visits must be >= 0.');
        }

        if ($this->share < 0.0 || $this->share > 1.0) {
            throw new \DomainException('Path share must be between 0 and 1.');
        }

        if ($this->dropoffAtStep !== null && ($this->dropoffAtStep < 1 || $this->dropoffAtStep > count($this->steps))) {
            throw new \DomainException('Path dropoffAtStep must be null or in path step range.');
        }
    }

    public function toArray(): array
    {
        return [
            'steps' => $this->steps,
            'visits' => $this->visits,
            'share' => $this->share,
            'depth' => count($this->steps),
            'dropoffAtStep' => $this->dropoffAtStep,
        ];
    }
}
