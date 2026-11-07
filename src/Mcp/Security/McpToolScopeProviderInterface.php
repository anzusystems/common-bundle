<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

interface McpToolScopeProviderInterface
{
    public function provideToolScope(): McpToolScope;
}
