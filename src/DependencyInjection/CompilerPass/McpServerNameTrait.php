<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

trait McpServerNameTrait
{
    private function resolveServerName(ContainerBuilder $container): string
    {
        $serverName = $container->getParameter(McpCompilerPass::SERVER_NAME_PARAM);
        if (false === is_string($serverName)) {
            throw McpConfigurationException::createInvalidServerName(McpCompilerPass::SERVER_NAME_PARAM);
        }

        return $serverName;
    }

    private function resolveBuilderId(string $serverName): string
    {
        return sprintf(McpCompilerPass::BUILDER_ID_FORMAT, $serverName);
    }
}
