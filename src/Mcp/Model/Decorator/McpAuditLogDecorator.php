<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Decorator;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\RawArrayHandler;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class McpAuditLogDecorator extends AbstractMcpLogDecorator
{
    #[Serialize]
    public ?string $method {
        get => $this->readString($this->context, 'method');
    }

    #[Serialize]
    public ?string $path {
        get => $this->readString($this->context, 'path');
    }

    #[Serialize]
    public ?string $resourceName {
        get => $this->readString($this->context, 'resourceName');
    }

    /**
     * @var array<array-key, mixed>
     */
    #[Serialize(handler: RawArrayHandler::class)]
    public array $resourceIds {
        get => $this->readArray($this->context, 'resourceIds');
    }

    #[Serialize]
    public ?int $httpStatus {
        get => $this->readInt($this->context, 'httpStatus');
    }

    #[Serialize]
    public ?string $error {
        get => $this->readTruncated($this->context, 'error');
    }

    #[Serialize]
    public ?string $exception {
        get => $this->readTruncated($this->context, 'exception');
    }

    #[Serialize]
    public ?string $contextId {
        get => $this->readString($this->context, 'contextId');
    }

    #[McpField(description: 'The user who made the request.')]
    #[Serialize]
    public ?int $userId {
        get => $this->readInt($this->context, 'userId');
    }

    #[McpField(description: 'The request body. Long values are truncated.')]
    #[Serialize]
    public ?string $content {
        get => $this->readTruncated($this->context, 'content');
    }

    #[McpField(description: 'Long values are truncated.')]
    #[Serialize]
    public ?string $response {
        get => $this->readTruncated($this->context, 'response');
    }
}
