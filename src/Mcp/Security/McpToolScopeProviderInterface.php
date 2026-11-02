<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

interface McpToolScopeProviderInterface
{
    /**
     * @return list<string>|null null when the current request does not narrow the tools
     */
    public function provideAllowedToolNames(): ?array;
}
