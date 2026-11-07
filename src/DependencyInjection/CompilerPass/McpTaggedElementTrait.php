<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use ReflectionAttribute;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Reads the MCP elements of tagged services the way the McpBundle McpPass registers them with the server.
 */
trait McpTaggedElementTrait
{
    private const string ELEMENT_TAG_METHOD = 'method';
    private const string ELEMENT_METHOD_FORMAT = '%s::%s';

    /**
     * @return array<string, ReflectionMethod> keyed by "class::method"
     */
    private function resolveTaggedMethods(ContainerBuilder $container, string $tag): array
    {
        $methods = [];
        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            $class = $this->resolveElementClass($container, $serviceId);
            if (null === $class) {
                continue;
            }
            foreach ($tags as $tagAttributes) {
                $method = $tagAttributes[self::ELEMENT_TAG_METHOD] ?? McpToolSignatureResolver::INVOKE_METHOD;
                if (method_exists($class, $method)) {
                    $methods[sprintf(self::ELEMENT_METHOD_FORMAT, $class, $method)] = new ReflectionMethod($class, $method);
                }
            }
        }

        return $methods;
    }

    /**
     * @return class-string|null
     */
    private function resolveElementClass(ContainerBuilder $container, string $serviceId): ?string
    {
        $definition = $container->getDefinition($serviceId);
        if ($definition->isAbstract()) {
            return null;
        }
        $class = $container->getParameterBag()
            ->resolveValue($definition->getClass() ?? $serviceId);
        if (is_string($class) && class_exists($class)) {
            return $class;
        }

        return null;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attributeClass
     *
     * @return T|null
     */
    private function resolveElementAttribute(ReflectionMethod $reflection, string $attributeClass): ?object
    {
        $attributes = $reflection->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
        if (empty($attributes) && StringHelper::isSame(McpToolSignatureResolver::INVOKE_METHOD, $reflection->getName())) {
            $attributes = $reflection->getDeclaringClass()
                ->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);
        }

        return array_first($attributes)?->newInstance();
    }
}
