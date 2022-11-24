<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck\Module;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_HEALTH_CHECK_MODULE)]
interface ModuleInterface
{
    public function getName(): string;

    public function isHealthy(): bool;
}
