<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck\Module;

use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception;

final class MongoModule implements ModuleInterface
{
    private const int MAX_OPERATION_TIME_MS = 3_000;

    /**
     * @param iterable<Collection> $collections
     */
    public function __construct(
        private readonly iterable $collections,
    ) {
    }

    public function getName(): string
    {
        return 'mongo';
    }

    public function isHealthy(): bool
    {
        try {
            foreach ($this->collections as $collection) {
                $collection->findOne([], ['maxTimeMS' => self::MAX_OPERATION_TIME_MS]);
            }

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
