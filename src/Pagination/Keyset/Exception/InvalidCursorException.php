<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Pagination\Keyset\Exception;

use InvalidArgumentException;

final class InvalidCursorException extends InvalidArgumentException
{
    public const string MESSAGE = 'The cursor is not a nextCursor of this list with these filters: pass the nextCursor of the previous page unchanged and keep the other filters, or omit the cursor for the first page.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
