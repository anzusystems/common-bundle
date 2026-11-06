<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

final readonly class McpToolResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public bool $isError,
        public ?string $error,
        public array $data,
    ) {
    }
}
