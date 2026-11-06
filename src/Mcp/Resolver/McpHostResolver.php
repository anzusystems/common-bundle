<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use Symfony\Component\HttpFoundation\Request;

final readonly class McpHostResolver
{
    public const string ROUTING_CONDITION_ALIAS = 'mcp_host';

    private const string HOST_ROOT_DOT = '.';

    /**
     * @var list<string>
     */
    public array $allowedHosts;

    /**
     * The value comes from an env variable at runtime, so a list like "a.example.sk, b.example.sk" arrives untrimmed.
     *
     * @param list<string> $allowedHosts
     */
    public function __construct(array $allowedHosts)
    {
        $this->allowedHosts = array_filter(array_map(trim(...), $allowedHosts), StringHelper::isNotEmpty(...)) |> array_values(...);
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
