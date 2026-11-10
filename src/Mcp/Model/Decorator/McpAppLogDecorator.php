<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Decorator;

use AnzuSystems\CommonBundle\Log\Repository\JournalLogRepository;
use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class McpAppLogDecorator extends AbstractMcpLogDecorator
{
    #[Serialize]
    public ?string $levelName {
        get => $this->readString($this->document, JournalLogRepository::FIELD_LEVEL_NAME);
    }

    #[McpField(description: self::TRUNCATED_DESCRIPTION)]
    #[Serialize]
    public ?string $message {
        get => $this->readTruncated($this->document, 'message');
    }

    #[Serialize]
    public ?string $contextId {
        get => $this->readString($this->context, 'contextId');
    }

    #[Serialize]
    public ?int $userId {
        get => $this->readInt($this->context, 'userId');
    }

    #[Serialize]
    public ?string $path {
        get => $this->readString($this->context, 'path');
    }
}
