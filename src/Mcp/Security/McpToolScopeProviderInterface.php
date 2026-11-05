<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

interface McpToolScopeProviderInterface
{
    /**
     * @return McpToolScope|null null when the request has no scope: read tools are not narrowed, write tools are denied
     */
    public function provideToolScope(): ?McpToolScope;
}
