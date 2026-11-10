<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

final class EmailHelper
{
    /**
     * This check validates email the same way as Remp Mailer does.
     */
    public static function isValid(string $email): bool
    {
        return null !== filter_var(value: $email, filter: FILTER_VALIDATE_EMAIL, options: FILTER_FLAG_EMAIL_UNICODE | FILTER_NULL_ON_FAILURE);
    }
}
