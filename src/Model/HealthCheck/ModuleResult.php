<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\HealthCheck;

use AnzuSystems\CommonBundle\HealthCheck\Module\ModuleInterface;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ModuleResult
{
    #[Serialize]
    private string $name;

    #[Serialize]
    private bool $healthy;

    #[Serialize]
    private string $time;

    public function __construct(
    ) {
        $this->setName('');
        $this->setHealthy(false);
    }

    public static function getInstance(ModuleInterface $module): self
    {
        return (new self())
            ->setName($module->getName())
            ->setHealthy($module->isHealthy())
        ;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

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
}
