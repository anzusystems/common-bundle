<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

final class EmailHelper
{
    /**
     * This check validates email the same way as Remp Mailer does.
     *
     * @psalm-suppress RedundantCondition
     */
    public static function isValid(string $email): bool
    {
        return null !== filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE | FILTER_NULL_ON_FAILURE);
    }
}
