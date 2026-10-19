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

        $container
            ->getDefinition(McpToolAccessChecker::class)
            ->replaceArgument('$readOnlyToolNames', $this->resolveReadOnlyToolNames($container));
    }

    /**
     * @return list<string>
     */
    private function resolveReadOnlyToolNames(ContainerBuilder $container): array
    {
        $readOnlyToolNames = [];
        foreach ($container->findTaggedServiceIds(self::TOOL_TAG) as $serviceId => $tags) {
            $class = $this->resolveToolClass($container, $serviceId);
            if (null === $class) {
                continue;
            }
            foreach ($tags as $tagAttributes) {
                $toolName = $this->resolveReadOnlyToolName($class, $tagAttributes[self::TOOL_TAG_METHOD] ?? self::INVOKE_METHOD);
                if (is_string($toolName)) {
                    $readOnlyToolNames[] = $toolName;
                }
            }
        }

        return array_values(array_unique($readOnlyToolNames));
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

    /**
     * @param class-string $class
     */
    private function resolveReadOnlyToolName(string $class, string $method): ?string
    {
        if (false === method_exists($class, $method)) {
            return null;
        }
        $reflection = new ReflectionMethod($class, $method);
        $attribute = $this->resolveToolAttribute($reflection);
        if (null === $attribute) {
            return null;
        }
        if (true === $attribute->annotations?->readOnlyHint) {
            return ElementMetadataResolver::resolveName($reflection, $attribute->name);
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
}
