<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Security;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class PermissionConfig
{
    public function __construct(
        private readonly array $config = [],
    ) {
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getConfig(): array
    {
        return $this->config;
    }
}
