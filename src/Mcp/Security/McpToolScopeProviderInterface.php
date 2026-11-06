<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

interface McpToolScopeProviderInterface
{
    /**
     * A request without a scope gets new McpToolScope(): read tools are not narrowed, write tools are denied.
     */
    public function provideToolScope(): McpToolScope;
}
