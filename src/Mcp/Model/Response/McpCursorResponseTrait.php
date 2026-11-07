<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

trait McpCursorResponseTrait
{
    #[McpField(description: 'Pass it as cursor to get the next page; null on the last page.')]
    #[Serialize]
    public ?string $nextCursor {
        get => $this->cursorPage->nextCursor;
    }
}
