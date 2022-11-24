<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck\Module;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class MysqlModule implements ModuleInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $tableName,
    ) {
    }

    public function getName(): string
    {
        return 'mysql';
    }

    public function isHealthy(): bool
    {
        try {
            return (bool) $this->connection
                ->fetchOne(sprintf('SELECT 1 FROM %s LIMIT 1', $this->tableName));
        } catch (Exception) {
            return false;
        }
    }
}
