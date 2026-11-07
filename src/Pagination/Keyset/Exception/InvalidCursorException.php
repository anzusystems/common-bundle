<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset\Exception;

use InvalidArgumentException;

final class InvalidCursorException extends InvalidArgumentException
{
    public const string MESSAGE = 'The cursor is not a nextCursor of this list: pass the nextCursor of the previous page unchanged, or omit the cursor for the first page.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
