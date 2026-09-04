<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class McpCompilerPass implements CompilerPassInterface
{
    public const string SERVER_NAME_PARAM = 'anzu_systems_common.mcp.server_name';
    public const string SESSION_CACHE_ID = 'anzu_systems_common.mcp.session_cache';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpController::class)) {
            return;
        }

        $serverName = $container->getParameter(self::SERVER_NAME_PARAM);
        $container
            ->setAlias(sprintf('mcp.server.%s.controller', $serverName), McpController::class)
            ->setPublic(true);
    }
}
