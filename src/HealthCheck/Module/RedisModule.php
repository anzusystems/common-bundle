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
        return (bool) $this->appRedis->setex(key: 'health_check', expire: 1, value: '1');
    }
}
