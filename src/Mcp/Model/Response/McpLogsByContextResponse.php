<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final readonly class McpLogsByContextResponse
{
    private const string HINT_CROSS_SERVICE = 'The same contextId correlates logs across the services of this '
        . 'platform; look it up in the logs of the other services to follow the request end to end.';

    public function __construct(
        private McpLogsByContextResult $result,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public function getAuditLogs(): array
    {
        return $this->result->auditLogs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public function getAppLogs(): array
    {
        return $this->result->appLogs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public function getMcpToolCalls(): array
    {
        return $this->result->mcpToolCalls;
    }

    #[Serialize]
    public function getHint(): string
    {
        return self::HINT_CROSS_SERVICE;
    }
}
