<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Factory\McpPreviewHashFactory;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The using class declares PREVIEW_TOOL, the name of the preview tool that issues the hash. A constant of this trait
 * must not read it: Symfony's container resource evaluates the constants of the trait itself and fails the compilation.
 * The hash stays out of the schema's `required`, so a client that skipped the preview learns which tool to call.
 */
trait McpPreviewHashTrait
{
    public const string MESSAGE_PREVIEW_HASH_START = 'Call ';
    public const string MESSAGE_PREVIEW_HASH_END = ' with the same arguments first, show the user its result and pass the previewHash it returned.';

    #[Assert\NotBlank(message: self::MESSAGE_PREVIEW_HASH_START . self::PREVIEW_TOOL . self::MESSAGE_PREVIEW_HASH_END)]
    #[Assert\Regex(
        pattern: McpPreviewHashFactory::PREVIEW_HASH_PATTERN,
        message: self::MESSAGE_PREVIEW_HASH_START . self::PREVIEW_TOOL . self::MESSAGE_PREVIEW_HASH_END,
    )]
    #[McpField(
        description: <<<'TXT'
            The previewHash that {{ preview_tool }} returned for these arguments.
            TXT
        ,
        descriptionValues: ['preview_tool' => self::PREVIEW_TOOL],
        requiredBySchema: false,
    )]
    #[Serialize]
    public string $previewHash = '';
}
