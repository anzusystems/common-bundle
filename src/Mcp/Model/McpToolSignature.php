<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final readonly class McpToolSignature
{
    /**
     * @param class-string|null $inputClass
     * @param class-string|null $outputClass null when the tool returns its own CallToolResult
     */
    public function __construct(
        public ?string $inputParameterName,
        public ?string $inputClass,
        public ?string $outputClass,
    ) {
    }
}
