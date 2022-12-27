<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Security;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class PermissionConfig
{
    public const PRM_ROLES = 'roles';
    public const PRM_DEFAULT_GRANTS = 'default_grants';
    public const PRM_CONFIG = 'config';
    public const PRM_TRANSLATION = 'translation';

    public function __construct(
        private readonly array $config = [],
    ) {
    }

    #[Serialize]
    public function getRoles(): array
    {
        return $this->config[self::PRM_ROLES];
    }

    #[Serialize]
    public function getDefaultGrants(): array
    {
        return $this->config[self::PRM_DEFAULT_GRANTS];
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getConfig(): array
    {
        return $this->config[self::PRM_CONFIG];
    }

    #[Serialize(strategy: Serialize::KEYS_VALUES)]
    public function getTranslation(): array
    {
        return $this->config[self::PRM_TRANSLATION];
    }
}
