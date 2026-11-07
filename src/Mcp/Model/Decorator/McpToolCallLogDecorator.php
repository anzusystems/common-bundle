<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Decorator;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class McpToolCallLogDecorator extends AbstractMcpLogDecorator
{
    #[Serialize]
    public ?string $levelName {
        get => $this->readString($this->document, McpLogger::LEVEL_NAME_FIELD);
    }

    #[Serialize]
    public ?string $tool {
        get => $this->readString($this->document, McpLogger::TOOL_FIELD);
    }

    /**
     * @var array<string, mixed>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public array $params {
        get => $this->readArray($this->document, McpLogger::PARAMS_FIELD);
    }

    #[Serialize]
    public ?int $userId {
        get => $this->readInt($this->document, McpLogger::USER_ID_FIELD);
    }

    #[McpField(description: 'The name of the MCP client, null when it sent none.')]
    #[Serialize]
    public ?string $client {
        get => $this->readString($this->document, McpLogger::CLIENT_FIELD);
    }

    #[Serialize]
    public ?string $contextId {
        get => $this->readString($this->document, McpLogger::CONTEXT_ID_FIELD);
    }

    #[Serialize]
    public ?int $durationMs {
        get => $this->readInt($this->document, McpLogger::DURATION_MS_FIELD);
    }

    #[McpField(description: self::TRUNCATED_DESCRIPTION)]
    #[Serialize]
    public ?string $error {
        get => $this->readTruncated($this->document, McpLogger::ERROR_FIELD);
    }
}
