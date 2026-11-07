<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final readonly class McpConfirmationMessage
{
    /**
     * @param array<string, int|string> $parameters
     */
    public function __construct(
        public string $key,
        public array $parameters = [],
    ) {
    }
}
