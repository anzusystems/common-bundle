<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Resolver\McpHostResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class McpHostResolverTest extends TestCase
{
    private const string MCP_HOST = 'core-cms-mcp.localhost';
    private const string OTHER_MCP_HOST = 'core-cms-mcp2.localhost';

    /**
     * @dataProvider hostProvider
     */
    public function testOnlyAnAllowedHostIsTheMcpHost(string $host, bool $expectedMcpHost): void
    {
        $resolver = new McpHostResolver([self::OTHER_MCP_HOST . ' ', ' ' . self::MCP_HOST, ' ']);
        $request = Request::create(sprintf('http://%s/', $host));

        self::assertSame($expectedMcpHost, $resolver->isMcpHost($request));
    }

    public function testAllowedHostsAreTrimmedAndEmptyOnesLeftOut(): void
    {
        self::assertSame(
            [self::OTHER_MCP_HOST, self::MCP_HOST],
            new McpHostResolver([self::OTHER_MCP_HOST . ' ', ' ' . self::MCP_HOST, ' '])->allowedHosts,
        );
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function hostProvider(): array
    {
        return [
            'mcp host' => [self::MCP_HOST, true],
            'mcp host with a root dot' => [self::MCP_HOST . '.', true],
            'second mcp host' => [self::OTHER_MCP_HOST, true],
            'other host' => ['core-cms.localhost', false],
        ];
    }
}
