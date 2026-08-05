<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class McpCompilerPassTest extends TestCase
{
    private const string SESSION_POOL = 'some_redis.cache';

    public function testDoesNothingWhenMcpIsDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->register('mcp.server.controller', stdClass::class);

        new McpCompilerPass()
            ->process($container);

        self::assertFalse($container->hasAlias('mcp.server.controller'));
        self::assertSame(stdClass::class, $container->getDefinition('mcp.server.controller')->getClass());
    }

    public function testOverridesMcpBundleControllerAndSessionCache(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SESSION_CACHE_POOL_PARAM, self::SESSION_POOL);
        $container->register(McpController::class);
        $container->register('mcp.server.controller', stdClass::class);
        $container->register('cache.mcp.sessions', stdClass::class);

        new McpCompilerPass()
            ->process($container);

        self::assertTrue($container->hasAlias('mcp.server.controller'));
        self::assertSame(McpController::class, (string) $container->getAlias('mcp.server.controller'));
        self::assertTrue($container->getAlias('mcp.server.controller')->isPublic());

        $sessionCacheDefinition = $container->getDefinition('cache.mcp.sessions');
        self::assertSame(Psr16Cache::class, $sessionCacheDefinition->getClass());
        /** @var Reference $pool */
        $pool = $sessionCacheDefinition->getArgument('$pool');
        self::assertSame(self::SESSION_POOL, (string) $pool);
    }
}
