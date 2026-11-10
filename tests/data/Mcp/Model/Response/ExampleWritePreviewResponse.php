<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response;

use AnzuSystems\CommonBundle\Mcp\Model\McpConfirmationMessage;
use AnzuSystems\CommonBundle\Mcp\Model\McpWritePreviewInterface;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class ExampleWritePreviewResponse implements McpWritePreviewInterface
{
    public const string MESSAGE_KEY = 'writeConfirmation.example';
    public const string TITLE_PARAMETER = '%title%';

    public McpConfirmationMessage $confirmationMessage {
        get => new McpConfirmationMessage(self::MESSAGE_KEY, [self::TITLE_PARAMETER => $this->title]);
    }

    #[Serialize]
    public ?string $previewHash = null;

    public function __construct(
        #[Serialize]
        public private(set) string $title,
        public private(set) bool $writable = true,
        public private(set) ?object $currentState = null,
    ) {
    }
}
