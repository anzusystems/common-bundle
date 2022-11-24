<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck\Module;

use Redis;

final class RedisModule implements ModuleInterface
{
    public function __construct(
        private readonly Redis $appRedis,
    ) {
    }

    public function getName(): string
    {
        return 'redis';
    }

    public function isHealthy(): bool
    {
        return (bool) $this->appRedis->set('health_check', '1', 1);
    }
}
