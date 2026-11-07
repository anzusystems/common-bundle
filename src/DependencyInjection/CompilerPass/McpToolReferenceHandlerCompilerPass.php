<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use Mcp\Capability\Registry\ReferenceHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Runs after the mcp-bundle McpPass, which builds the handler locator of the server.
 */
final class McpToolReferenceHandlerCompilerPass implements CompilerPassInterface
{
    use McpServerNameTrait;

    public const int PRIORITY = -10;
    public const string APP_TOOL_TEMPLATES_PARAM = 'mcp.servers.app_tool_templates';

    private const string INNER_ARGUMENT = '$inner';
    private const string SET_CONTAINER_METHOD = 'setContainer';
    private const string SET_REFERENCE_HANDLER_METHOD = 'setReferenceHandler';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpToolReferenceHandler::class)) {
            return;
        }
        $serverName = $this->resolveServerName($container);
        $builderId = $this->resolveBuilderId($serverName);
        if (false === $container->hasDefinition($builderId)) {
            return;
        }
        $this->assertNoAppToolTemplates($container, $serverName);

        $builder = $container->getDefinition($builderId);
        if ($builder->hasMethodCall(self::SET_REFERENCE_HANDLER_METHOD)) {
            throw McpConfigurationException::createForeignReferenceHandler($serverName);
        }
        $container->getDefinition(McpToolReferenceHandler::class)->replaceArgument(
            self::INNER_ARGUMENT,
            new Definition(ReferenceHandler::class, [$this->resolveHandlerLocator($builder)]),
        );
        $builder->addMethodCall(self::SET_REFERENCE_HANDLER_METHOD, [new Reference(McpToolReferenceHandler::class)]);
    }

    private function resolveHandlerLocator(Definition $builder): mixed
    {
        foreach ($builder->getMethodCalls() as [$method, $arguments]) {
            if (self::SET_CONTAINER_METHOD === $method) {
                return array_first($arguments);
            }
        }

        return null;
    }

    private function assertNoAppToolTemplates(ContainerBuilder $container, string $serverName): void
    {
        if (false === $container->hasParameter(self::APP_TOOL_TEMPLATES_PARAM)) {
            return;
        }
        $toolTemplates = (array) $container->getParameter(self::APP_TOOL_TEMPLATES_PARAM);
        $toolKey = array_key_first((array) ($toolTemplates[$serverName] ?? []));
        if (null === $toolKey) {
            return;
        }

        throw McpConfigurationException::createAppToolTemplate((string) $toolKey);
    }
}
