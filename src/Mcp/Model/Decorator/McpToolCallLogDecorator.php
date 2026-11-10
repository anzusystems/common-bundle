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
        get => $this->readString($this->document, McpLogger::FIELD_LEVEL_NAME);
    }

    #[Serialize]
    public ?string $tool {
        get => $this->readString($this->document, McpLogger::FIELD_TOOL);
    }

    /**
     * @var array<string, mixed>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public array $params {
        get => $this->readArray($this->document, McpLogger::FIELD_PARAMS);
    }

    #[Serialize]
    public ?int $userId {
        get => $this->readInt($this->document, McpLogger::FIELD_USER_ID);
    }

    #[McpField(description: 'The name of the MCP client, null when it sent none.')]
    #[Serialize]
    public ?string $client {
        get => $this->readString($this->document, McpLogger::FIELD_CLIENT);
    }

    #[Serialize]
    public ?string $contextId {
        get => $this->readString($this->document, McpLogger::FIELD_CONTEXT_ID);
    }

    #[Serialize]
    public ?int $durationMs {
        get => $this->readInt($this->document, McpLogger::FIELD_DURATION_MS);
    }

    #[McpField(description: self::TRUNCATED_DESCRIPTION)]
    #[Serialize]
    public ?string $error {
        get => $this->readTruncated($this->document, McpLogger::FIELD_ERROR);
    }
}
