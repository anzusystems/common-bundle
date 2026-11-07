<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait McpCursorRequestTrait
{
    #[McpField(description: 'Pass the nextCursor of the previous response to get the next page; omit it for the first page.')]
    #[Serialize]
    public ?string $cursor = null;
}
