<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Domain;

final class Step
{
    public function __construct(
        private string $id,
        private string $label,
        private ?string $normalizedUrl = null,
        private ?string $actionName = null
    ) {
        if ($this->id === '') {
            throw new \DomainException('Step id must not be empty.');
        }

        if ($this->label === '') {
            throw new \DomainException('Step label must not be empty.');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getNormalizedUrl(): ?string
    {
        return $this->normalizedUrl;
    }

    public function getActionName(): ?string
    {
        return $this->actionName;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'normalizedUrl' => $this->normalizedUrl,
            'actionName' => $this->actionName,
        ];
    }
}
