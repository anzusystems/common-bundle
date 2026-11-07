<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait McpCursorRequestTrait
{
    public const string CURSOR_DESCRIPTION = 'Pass the nextCursor of the previous response with the other arguments unchanged to get the next page; omit it for the first page.';

    #[McpField(description: self::CURSOR_DESCRIPTION)]
    #[Serialize]
    public ?string $cursor = null;
}
