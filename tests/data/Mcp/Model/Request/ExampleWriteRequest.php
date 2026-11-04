<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request;

use AnzuSystems\CommonBundle\Mcp\Model\McpPreviewHashAwareInterface;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

final class ExampleWriteRequest extends ExampleWritePreviewRequest implements McpPreviewHashAwareInterface
{
    public const string PREVIEW_HASH_PATTERN = '/^[0-9a-f]{64}$/D';
    public const string PREVIEW_HASH_MESSAGE = 'Call the preview first.';

    #[Assert\NotBlank(message: self::PREVIEW_HASH_MESSAGE)]
    #[Assert\Regex(self::PREVIEW_HASH_PATTERN, message: self::PREVIEW_HASH_MESSAGE)]
    #[Serialize]
    public string $previewHash = '';
}
