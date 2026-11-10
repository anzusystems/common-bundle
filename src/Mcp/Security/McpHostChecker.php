<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use Symfony\Component\HttpFoundation\Request;

final readonly class McpHostChecker
{
    private const string HOST_ROOT_DOT = '.';

    /**
     * @var list<string>
     */
    public array $allowedHosts;

    /**
     * The value comes from an env variable at runtime, so a list like "a.example.sk, B.example.sk" arrives untrimmed
     * and in any case, while Request::getHost() is always lower case.
     *
     * @param list<string> $allowedHosts
     */
    public function __construct(array $allowedHosts)
    {
        $this->allowedHosts = array_map(static fn (string $host): string => trim($host) |> strtolower(...), $allowedHosts)
            |> (static fn (array $hosts): array => array_filter($hosts, StringHelper::isNotEmpty(...)))
            |> array_values(...);
    }

    public function isMcpHost(Request $request): bool
    {
        return in_array(
            needle: rtrim($request->getHost(), self::HOST_ROOT_DOT),
            haystack: $this->allowedHosts,
            strict: true,
        );
    }
}
