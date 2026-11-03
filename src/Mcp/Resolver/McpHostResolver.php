<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use Symfony\Component\HttpFoundation\Request;

final readonly class McpHostResolver
{
    public const string ROUTING_CONDITION_ALIAS = 'mcp_host';

    private const string HOST_ROOT_DOT = '.';

    /**
     * @param list<string> $allowedHosts
     */
    public function __construct(
        private array $allowedHosts,
    ) {
    }

    public function isMcpHost(Request $request): bool
    {
        return in_array(
            needle: rtrim($request->getHost(), self::HOST_ROOT_DOT),
            haystack: $this->allowedHosts,
            strict: true,
        );
    }

    public function isNotMcpHost(Request $request): bool
    {
        return false === $this->isMcpHost($request);
    }
}
