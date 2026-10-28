<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use LogicException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Discovery\ElementMetadataResolver;
use ReflectionAttribute;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class McpCompilerPass implements CompilerPassInterface
{
    public const string SERVER_NAME_PARAM = 'anzu_systems_common.mcp.server_name';
    public const string SESSION_CACHE_ID = 'anzu_systems_common.mcp.session_cache';
    public const string TOOL_TAG = 'mcp.tool';

    private const string TOOL_TAG_METHOD = 'method';
    private const string INVOKE_METHOD = '__invoke';
    private const string TOOL_METHOD_FORMAT = '%s::%s';
    private const string TOOL_PERMISSIONS_ARGUMENT = '$toolPermissions';
    private const string READ_ONLY_TOOL_NAMES_ARGUMENT = '$readOnlyToolNames';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpController::class)) {
            return;
        }

        $serverName = $container->getParameter(self::SERVER_NAME_PARAM);
        if (false === is_string($serverName)) {
            throw new LogicException(sprintf('The "%s" parameter must be the mcp server name.', self::SERVER_NAME_PARAM));
        }

        $container
            ->setAlias(sprintf('mcp.server.%s.controller', $serverName), McpController::class)
            ->setPublic(true);

        $toolsByName = $this->resolveToolsByName($container);
        $this->assertToolNamesAreUnique($toolsByName);
        $accessCheckerDefinition = $container->getDefinition(McpToolAccessChecker::class);
        $this->assertToolPermissionsMatchTools(
            (array) $accessCheckerDefinition->getArgument(self::TOOL_PERMISSIONS_ARGUMENT),
            $toolsByName,
        );
        $accessCheckerDefinition->replaceArgument(
            self::READ_ONLY_TOOL_NAMES_ARGUMENT,
            $this->resolveReadOnlyToolNames($toolsByName),
        );
    }

    /**
     * @return array<string, array<string, McpTool>>
     */
    private function resolveToolsByName(ContainerBuilder $container): array
    {
        $toolsByName = [];
        foreach ($this->resolveToolMethods($container) as $toolMethod => $reflection) {
            $tool = $this->resolveToolAttribute($reflection);
            if (null === $tool) {
                continue;
            }
            $toolsByName[ElementMetadataResolver::resolveName($reflection, $tool->name)][$toolMethod] = $tool;
        }

        return $toolsByName;
    }

    /**
     * @return array<string, ReflectionMethod>
     */
    private function resolveToolMethods(ContainerBuilder $container): array
    {
        $toolMethods = [];
        foreach ($container->findTaggedServiceIds(self::TOOL_TAG) as $serviceId => $tags) {
            $class = $this->resolveToolClass($container, $serviceId);
            if (null === $class) {
                continue;
            }
            foreach ($tags as $tagAttributes) {
                $method = $tagAttributes[self::TOOL_TAG_METHOD] ?? self::INVOKE_METHOD;
                if (method_exists($class, $method)) {
                    $toolMethods[sprintf(self::TOOL_METHOD_FORMAT, $class, $method)] = new ReflectionMethod($class, $method);
                }
            }
        }

        return $toolMethods;
    }

    /**
     * @return class-string|null
     */
    private function resolveToolClass(ContainerBuilder $container, string $serviceId): ?string
    {
        $definition = $container->getDefinition($serviceId);
        if ($definition->isAbstract()) {
            return null;
        }
        $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $serviceId);
        if (is_string($class) && class_exists($class)) {
            return $class;
        }

        return null;
    }

    private function resolveToolAttribute(ReflectionMethod $reflection): ?McpTool
    {
        $attributes = $reflection->getAttributes(McpTool::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes && self::INVOKE_METHOD === $reflection->getName()) {
            $attributes = $reflection->getDeclaringClass()->getAttributes(McpTool::class, ReflectionAttribute::IS_INSTANCEOF);
        }
        if ([] === $attributes) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     */
    private function assertToolNamesAreUnique(array $toolsByName): void
    {
        foreach ($toolsByName as $toolName => $tools) {
            if (count($tools) > 1) {
                throw new LogicException(sprintf(
                    'The MCP tool name "%s" is registered by more than one tool (%s), every tool name must be unique.',
                    $toolName,
                    implode(', ', array_keys($tools)),
                ));
            }
        }
    }

    /**
     * @param array<string, string> $toolPermissions
     * @param array<string, array<string, McpTool>> $toolsByName
     */
    private function assertToolPermissionsMatchTools(array $toolPermissions, array $toolsByName): void
    {
        $unknownToolNames = array_keys(array_diff_key($toolPermissions, $toolsByName));
        if ([] === $unknownToolNames) {
            return;
        }

        throw new LogicException(sprintf(
            'The "mcp.tool_permissions" option maps unknown MCP tools: "%s". Every key must be the name of a registered MCP tool, so check for a typo or make sure the tool is registered as a service.',
            implode('", "', $unknownToolNames),
        ));
    }

    /**
     * @param array<string, array<string, McpTool>> $toolsByName
     *
     * @return list<string>
     */
    private function resolveReadOnlyToolNames(array $toolsByName): array
    {
        return array_keys(array_filter(
            $toolsByName,
            static fn (array $tools): bool => array_any(
                $tools,
                static fn (McpTool $tool): bool => true === $tool->annotations?->readOnlyHint,
            ),
        ));
    }
}
