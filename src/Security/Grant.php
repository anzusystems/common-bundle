<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Security;

final class Grant
{
    public const DENY = 0;
    public const ALLOW_OWNER = 1;
    public const ALLOW = 2;

    public const AVAILABLE_GRANTS = [
        self::DENY,
        self::ALLOW_OWNER,
        self::ALLOW,
    ];
}
