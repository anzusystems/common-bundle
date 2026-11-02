<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogSearchResult;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final readonly class McpAppLogSearchResponse
{
    use McpLogSearchResponseTrait;

    private const string HINT_CONTEXT_ID = 'Pass a record\'s contextId to get_logs_by_context to see the request and '
        . 'MCP tool calls behind it; the same contextId correlates logs across the services of this platform.';

    public function __construct(McpLogSearchResult $result)
    {
        $this->result = $result;
        $this->hint = self::HINT_CONTEXT_ID;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpField(description: 'Newest first: records with a level, a message and the contextId of the request that produced them. Long messages are truncated.')]
    #[Serialize(handler: RawArrayHandler::class)]
    public function getAppLogs(): array
    {
        return $this->result->logs;
    }
}
