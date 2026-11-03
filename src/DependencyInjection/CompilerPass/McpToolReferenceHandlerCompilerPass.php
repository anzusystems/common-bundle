<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use LogicException;
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
    public const int PRIORITY = -10;
    public const string APP_REFERENCE_HANDLER_ID_FORMAT = 'mcp.server.%s.app.reference_handler';
    public const string APP_TOOL_TEMPLATES_PARAM = 'mcp.servers.app_tool_templates';

    private const string INNER_ARGUMENT = '$inner';
    private const string TOOL_SIGNATURES_ARGUMENT = '$toolSignatures';
    private const string ADD_TOOL_METHOD = 'addTool';
    private const int ADD_TOOL_HANDLER_ARGUMENT = 0;
    private const int APP_INNER_ARGUMENT = 0;
    private const string SET_CONTAINER_METHOD = 'setContainer';
    private const string SET_REFERENCE_HANDLER_METHOD = 'setReferenceHandler';
    private const string APP_TOOL_KEY_SEPARATOR = '::';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpToolReferenceHandler::class)) {
            return;
        }
        $serverName = $container->getParameter(McpCompilerPass::SERVER_NAME_PARAM);
        if (false === is_string($serverName)) {
            throw new LogicException(sprintf('The "%s" parameter must be the mcp server name.', McpCompilerPass::SERVER_NAME_PARAM));
        }
        $builderId = sprintf(McpCompilerPass::BUILDER_ID_FORMAT, $serverName);
        if (false === $container->hasDefinition($builderId)) {
            return;
        }
        $this->assertNoAppToolTemplates($container, $serverName);

        $handler = $container->getDefinition(McpToolReferenceHandler::class);
        $builder = $container->getDefinition($builderId);
        $handler->replaceArgument(self::TOOL_SIGNATURES_ARGUMENT, $this->resolveToolSignatures($builder));
        $appHandlerId = sprintf(self::APP_REFERENCE_HANDLER_ID_FORMAT, $serverName);
        if ($container->hasDefinition($appHandlerId)) {
            $appHandler = $container->getDefinition($appHandlerId);
            $handler->replaceArgument(self::INNER_ARGUMENT, $appHandler->getArgument(self::APP_INNER_ARGUMENT));
            $appHandler->replaceArgument(self::APP_INNER_ARGUMENT, new Reference(McpToolReferenceHandler::class));

            return;
        }

        if ($builder->hasMethodCall(self::SET_REFERENCE_HANDLER_METHOD)) {
            throw new LogicException(sprintf(
                'The mcp server "%s" already has a reference handler, so %s cannot wrap the SDK one.',
                $serverName,
                McpToolReferenceHandler::class,
            ));
        }
        $handler->replaceArgument(
            self::INNER_ARGUMENT,
            new Definition(ReferenceHandler::class, [$this->resolveHandlerLocator($builder)]),
        );
        $builder->addMethodCall(self::SET_REFERENCE_HANDLER_METHOD, [new Reference(McpToolReferenceHandler::class)]);
    }

    /**
     * @return array<class-string, Definition>
     */
    private function resolveToolSignatures(Definition $builder): array
    {
        $signatureResolver = new McpToolSignatureResolver();
        $toolSignatures = [];
        foreach ($builder->getMethodCalls() as [$method, $arguments]) {
            if (self::ADD_TOOL_METHOD !== $method) {
                continue;
            }
            [$class, $handlerMethod] = $arguments[self::ADD_TOOL_HANDLER_ARGUMENT];
            if (McpToolSignatureResolver::INVOKE_METHOD !== $handlerMethod || false === is_string($class) || false === class_exists($class)) {
                continue;
            }
            $signature = $signatureResolver->resolve($class);
            if (null === $signature) {
                continue;
            }
            $toolSignatures[$class] = new Definition(McpToolSignature::class, [
                $signature->inputParameterName,
                $signature->inputClass,
                $signature->outputClass,
            ]);
        }

        return $toolSignatures;
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
            $serviceId = explode(self::APP_TOOL_KEY_SEPARATOR, (string) $toolKey) |> array_first(...);
            $class = $container->getParameterBag()
                ->resolveValue($container->findDefinition($serviceId)->getClass() ?? $serviceId);
            if (false === is_string($class) || false === class_exists($class)) {
                continue;
            }
            if (empty(new ReflectionClass($class)->getAttributes(AsMcpTool::class))) {
                continue;
            }

            throw new LogicException(sprintf(
                'The MCP tool "%s" declares #[AsMcpTool] and the MCP App template of "%s": %s returns a CallToolResult, but the template needs an array, so drop one of them.',
                $class,
                $toolKey,
                McpToolReferenceHandler::class,
            ));
        }
    }
}
