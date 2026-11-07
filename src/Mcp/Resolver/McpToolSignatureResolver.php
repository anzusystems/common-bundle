<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

final readonly class McpToolSignatureResolver
{
    public const string INVOKE_METHOD = '__invoke';

    /**
     * @param class-string $class
     */
    public function resolve(string $class): ?McpToolSignature
    {
        $reflection = new ReflectionClass($class);
        if (empty($reflection->getAttributes(AsMcpTool::class))) {
            return null;
        }
        if (false === $reflection->hasMethod(self::INVOKE_METHOD)) {
            throw McpConfigurationException::createToolWithoutInvoke($class, self::INVOKE_METHOD);
        }
        $method = $reflection->getMethod(self::INVOKE_METHOD);
        $inputParameter = $this->resolveInputParameter($method);

        return new McpToolSignature(
            inputParameterName: $inputParameter?->getName(),
            inputClass: $inputParameter instanceof ReflectionParameter ? $this->resolveClass($inputParameter->getType()) : null,
            outputClass: $this->resolveOutputClass($method),
        );
    }

    private function resolveInputParameter(ReflectionMethod $method): ?ReflectionParameter
    {
        $parameters = $method->getParameters();
        $lastParameter = array_last($parameters);
        if ($lastParameter instanceof ReflectionParameter && $this->isRequestContext($lastParameter)) {
            array_pop($parameters);
        }
        if (count($parameters) > 1) {
            throw $this->createParameterException($method, $parameters[1]);
        }
        $inputParameter = array_first($parameters);
        if (null === $inputParameter) {
            return null;
        }
        if ($inputParameter->isVariadic() || $this->isRequestContext($inputParameter) || null === $this->resolveClass($inputParameter->getType())) {
            throw $this->createParameterException($method, $inputParameter);
        }

        return $inputParameter;
    }

    /**
     * @return class-string|null
     */
    private function resolveOutputClass(ReflectionMethod $method): ?string
    {
        $type = $method->getReturnType();
        $class = $this->resolveClass($type);
        if (is_string($class) && false === $type?->allowsNull()) {
            return CallToolResult::class === $class ? null : $class;
        }

        throw McpConfigurationException::createInvalidToolReturn(
            class: $method->class,
            method: $method->name,
            resultClass: CallToolResult::class,
            type: (string) $type,
        );
    }

    /**
     * @return class-string|null
     */
    private function resolveClass(?ReflectionType $type): ?string
    {
        if (false === $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }
        $class = $type->getName();
        if (class_exists($class)) {
            return $class;
        }

        return null;
    }

    private function isRequestContext(ReflectionParameter $parameter): bool
    {
        return RequestContext::class === $this->resolveClass($parameter->getType());
    }

    private function createParameterException(ReflectionMethod $method, ReflectionParameter $parameter): McpConfigurationException
    {
        return McpConfigurationException::createInvalidToolParameter(
            class: $method->class,
            method: $method->name,
            contextClass: RequestContext::class,
            parameter: $parameter->getName(),
        );
    }
}
