<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

final class PasswordHelper
{
    public static function passwordHash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'time_cost' => 2,
            'threads' => 2,
        ]);
    }
}
