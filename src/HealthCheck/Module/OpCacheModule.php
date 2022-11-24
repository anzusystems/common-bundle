<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck\Module;

final class OpCacheModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'opcache';
    }

    public function isHealthy(): bool
    {
        return opcache_get_status(false)['opcache_enabled'] ?? false;
    }
}
