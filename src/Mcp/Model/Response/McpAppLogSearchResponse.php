<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAppLogDecorator;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Doctrine\Common\Collections\Collection;

final class McpAppLogSearchResponse
{
    /**
     * @use McpLogSearchResponseTrait<McpAppLogDecorator>
     */
    use McpLogSearchResponseTrait;

    private const string HINT_CONTEXT_ID = 'Pass a record\'s contextId to get_logs_by_context to see the request and '
        . 'MCP tool calls behind it; the same contextId correlates logs across the services of this platform.';

    /**
     * @var Collection<int, McpAppLogDecorator>
     */
    #[McpField(description: 'Newest first: records with a level, a message and the contextId of the request that produced them.')]
    #[Serialize(type: McpAppLogDecorator::class)]
    public Collection $appLogs {
        get => $this->result->logs;
    }
}
