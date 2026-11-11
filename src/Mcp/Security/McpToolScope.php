<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

final readonly class McpToolScope
{
    /**
     * @param list<string>|null $readToolNames null keeps every read tool except those declared with explicitGrant; an explicitGrant tool is in the scope only through explicitToolNames
     * @param list<string> $writeToolNames
     * @param list<string> $explicitToolNames
     */
    public function __construct(
        public ?array $readToolNames = null,
        public array $writeToolNames = [],
        public array $explicitToolNames = [],
    ) {
    }
}
