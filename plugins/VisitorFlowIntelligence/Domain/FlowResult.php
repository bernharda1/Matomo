<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Domain;

final class FlowResult
{
    /**
     * @param array<int, array<string, mixed>> $paths
     * @param array<int, array<string, mixed>> $transitions
     * @param array<int, array<string, mixed>> $dropoffs
     */
    public function __construct(
        private int $idSite,
        private string $period,
        private string $date,
        private string $generatedAt,
        private int $totalVisits,
        private array $paths,
        private array $transitions,
        private array $dropoffs,
        private ?string $segment = null
    ) {
        if ($this->idSite <= 0) {
            throw new \DomainException('FlowResult idSite must be > 0.');
        }

        if ($this->period === '' || $this->date === '' || $this->generatedAt === '') {
            throw new \DomainException('FlowResult period/date/generatedAt must not be empty.');
        }

        if ($this->totalVisits < 0) {
            throw new \DomainException('FlowResult totalVisits must be >= 0.');
        }
    }

    public function toArray(): array
    {
        return [
            'idSite' => $this->idSite,
            'period' => $this->period,
            'date' => $this->date,
            'segment' => $this->segment,
            'generatedAt' => $this->generatedAt,
            'totalVisits' => $this->totalVisits,
            'paths' => $this->paths,
            'transitions' => $this->transitions,
            'dropoffs' => $this->dropoffs,
        ];
    }
}
