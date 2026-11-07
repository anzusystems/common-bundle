<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Decorator;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class McpToolCallLogDecorator extends AbstractMcpLogDecorator
{
    #[Serialize]
    public ?string $levelName {
        get => $this->readString($this->document, 'levelName');
    }

    #[Serialize]
    public ?string $tool {
        get => $this->readString($this->document, 'tool');
    }

    /**
     * @var array<string, mixed>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public array $params {
        get => $this->readArray($this->document, 'params');
    }

    #[Serialize]
    public ?int $userId {
        get => $this->readInt($this->document, 'userId');
    }

    #[McpField(description: 'The name of the MCP client, null when it sent none.')]
    #[Serialize]
    public ?string $client {
        get => $this->readString($this->document, 'client');
    }

    #[Serialize]
    public ?string $contextId {
        get => $this->readString($this->document, 'contextId');
    }

    #[Serialize]
    public ?int $durationMs {
        get => $this->readInt($this->document, 'durationMs');
    }

    #[McpField(description: self::TRUNCATED_DESCRIPTION)]
    #[Serialize]
    public ?string $error {
        get => $this->readTruncated($this->document, 'error');
    }
}
