<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Resolver;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use LogicException;
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
            throw new LogicException(sprintf('The MCP tool "%s" declares #[AsMcpTool], so it declares %s().', $class, self::INVOKE_METHOD));
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

        throw new LogicException(sprintf(
            'The MCP tool "%s::%s()" returns a response class or %s, not "%s".',
            $method->class,
            $method->name,
            CallToolResult::class,
            (string) $type,
        ));
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

    private function createParameterException(ReflectionMethod $method, ReflectionParameter $parameter): LogicException
    {
        return new LogicException(sprintf(
            'The MCP tool "%s::%s()" takes its input class and then the optional %s, so "$%s" does not fit: move it into the input class.',
            $method->class,
            $method->name,
            RequestContext::class,
            $parameter->getName(),
        ));
    }
}
