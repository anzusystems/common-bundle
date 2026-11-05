<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScopeProviderInterface;

final readonly class StaticMcpToolScopeProvider implements McpToolScopeProviderInterface
{
    public function __construct(
        private ?McpToolScope $toolScope,
    ) {
    }

    public function provideToolScope(): ?McpToolScope
    {
        return $this->toolScope;
    }
}
