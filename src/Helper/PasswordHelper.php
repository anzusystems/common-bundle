<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

final class PasswordHelper
{
    public static function passwordHash(string $password): string
    {
        return password_hash(password: $password, algo: PASSWORD_ARGON2ID, options: [
            'time_cost' => 2,
            'threads' => 2,
        ]);
    }
}
