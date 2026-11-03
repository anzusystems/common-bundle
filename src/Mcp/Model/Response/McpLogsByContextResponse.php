<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class McpLogsByContextResponse
{
    private const string HINT_CROSS_SERVICE = 'The same contextId correlates logs across the services of this '
        . 'platform; look it up in the logs of the other services to follow the request end to end.';

    /**
     * @var list<array<string, mixed>>
     */
    #[McpField(description: 'The API requests, newest first.')]
    #[Serialize(handler: RawArrayHandler::class)]
    public array $auditLogs {
        get => $this->result->auditLogs;
    }

    /**
     * @var list<array<string, mixed>>
     */
    #[McpField(description: 'Application records produced while handling them, newest first.')]
    #[Serialize(handler: RawArrayHandler::class)]
    public array $appLogs {
        get => $this->result->appLogs;
    }

    /**
     * @var list<array<string, mixed>>
     */
    #[McpField(description: 'MCP tool calls with this contextId, newest first.')]
    #[Serialize(handler: RawArrayHandler::class)]
    public array $mcpToolCalls {
        get => $this->result->mcpToolCalls;
    }

    #[Serialize]
    public string $hint {
        get => self::HINT_CROSS_SERVICE;
    }

    public function __construct(
        private readonly McpLogsByContextResult $result,
    ) {
    }
}
