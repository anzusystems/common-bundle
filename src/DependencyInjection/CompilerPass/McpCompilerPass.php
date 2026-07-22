<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpCompilerPass implements CompilerPassInterface
{
    public const string SESSION_CACHE_POOL_PARAM = 'anzu_systems_common.mcp.session_cache_pool';

    private const string MCP_SERVER_CONTROLLER_ID = 'mcp.server.controller';
    private const string MCP_SESSION_CACHE_ID = 'cache.mcp.sessions';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpController::class)) {
            return;
        }

        $container
            ->setAlias(self::MCP_SERVER_CONTROLLER_ID, McpController::class)
            ->setPublic(true);

        $sessionCacheDefinition = new Definition(Psr16Cache::class);
        $sessionCacheDefinition->setArgument('$pool', new Reference((string) $container->getParameter(self::SESSION_CACHE_POOL_PARAM)));
        $container->setDefinition(self::MCP_SESSION_CACHE_ID, $sessionCacheDefinition);
    }
}
