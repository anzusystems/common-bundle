<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\Contracts\Model\Enum\LogLevel;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class SearchAppLogsRequest
{
    use McpLogSearchRequestTrait;

    #[McpField(description: 'Omit for all levels.')]
    #[Serialize]
    public ?LogLevel $level = null;

    #[McpField(description: 'Case-insensitive substring match on the log message.')]
    #[Serialize]
    public ?string $messageContains = null;
}
