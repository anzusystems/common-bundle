<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class GetLogsByContextRequest
{
    #[McpField(
        description: <<<'TXT'
            The contextId (UUID) of one request, taken from an audit log, application log or MCP tool call record.
            Only logs of the last {{ logWindowMaxDays }} days are searched.
            TXT
        ,
        descriptionValues: ['logWindowMaxDays' => McpDateWindowResolver::LOG_WINDOW_MAX_DAYS],
    )]
    #[Serialize]
    public string $contextId;
}
