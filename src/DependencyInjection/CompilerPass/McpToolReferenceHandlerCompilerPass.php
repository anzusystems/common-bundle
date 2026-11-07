<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use Mcp\Capability\Registry\ReferenceHandler;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Runs after the mcp-bundle McpPass, which builds the handler locator of the server and, for MCP App tool templates,
 * its own reference handler around the SDK one; McpToolReferenceHandler goes between those two.
 */
final class McpToolReferenceHandlerCompilerPass implements CompilerPassInterface
{
    use McpServerNameTrait;

    public const int PRIORITY = -10;
    public const string APP_REFERENCE_HANDLER_ID_FORMAT = 'mcp.server.%s.app.reference_handler';
    public const string APP_TOOL_TEMPLATES_PARAM = 'mcp.servers.app_tool_templates';

    private const string INNER_ARGUMENT = '$inner';
    private const int APP_INNER_ARGUMENT = 0;
    private const string SET_CONTAINER_METHOD = 'setContainer';
    private const string SET_REFERENCE_HANDLER_METHOD = 'setReferenceHandler';
    private const string APP_TOOL_KEY_SEPARATOR = '::';

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

        $handler = $container->getDefinition(McpToolReferenceHandler::class);
        $builder = $container->getDefinition($builderId);
        $appHandlerId = sprintf(self::APP_REFERENCE_HANDLER_ID_FORMAT, $serverName);
        if ($container->hasDefinition($appHandlerId)) {
            $appHandler = $container->getDefinition($appHandlerId);
            $handler->replaceArgument(self::INNER_ARGUMENT, $appHandler->getArgument(self::APP_INNER_ARGUMENT));
            $appHandler->replaceArgument(self::APP_INNER_ARGUMENT, new Reference(McpToolReferenceHandler::class));

            return;
        }

        if ($builder->hasMethodCall(self::SET_REFERENCE_HANDLER_METHOD)) {
            throw McpConfigurationException::createForeignReferenceHandler($serverName);
        }
        $handler->replaceArgument(
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
        foreach (array_keys((array) ($toolTemplates[$serverName] ?? [])) as $toolKey) {
            $serviceId = explode(self::APP_TOOL_KEY_SEPARATOR, (string) $toolKey)[0];
            $class = $container->getParameterBag()
                ->resolveValue($container->findDefinition($serviceId)->getClass() ?? $serviceId);
            if (false === is_string($class) || false === class_exists($class)) {
                continue;
            }
            if (empty(new ReflectionClass($class)->getAttributes(AsMcpTool::class))) {
                continue;
            }

            throw McpConfigurationException::createAppTemplateOnAsMcpTool($class, $toolKey);
        }
    }
}
