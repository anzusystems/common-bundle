<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SearchAppLogsRequest
{
    use McpLogSearchRequestTrait;

    #[McpField(description: 'Log level name, e.g. "ERROR", "WARNING", "INFO". Case-insensitive, exact match. Omit for all levels.')]
    #[Serialize]
    public ?string $level = null;

    #[McpField(description: 'Case-insensitive substring match on the log message.')]
    #[Serialize]
    public ?string $messageContains = null;
}
