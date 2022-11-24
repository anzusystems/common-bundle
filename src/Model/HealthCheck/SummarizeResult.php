<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\HealthCheck;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SummarizeResult
{
    #[Serialize]
    private bool $healthy = true;

    #[Serialize]
    private string $time = '0s';

    #[Serialize(serializedName: 'lead_time')]
    private string $leadTime;

    /**
     * @var array<string, ModuleResult>
     */
    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    private array $moduleResults = [];

    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    public function setHealthy(bool $healthy): self
    {
        $this->healthy = $healthy;

        return $this;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function setTime(string $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getLeadTime(): string
    {
        return $this->leadTime;
    }

    public function setLeadTime(string $leadTime): self
    {
        $this->leadTime = $leadTime;

        return $this;
    }

    public function getModuleResults(): array
    {
        return $this->moduleResults;
    }

    public function addModuleResult(ModuleResult $module): self
    {
        $this->moduleResults[$module->getName()] = $module;

        return $this;
    }
}
