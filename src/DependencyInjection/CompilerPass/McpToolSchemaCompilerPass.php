<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Factory\McpOutputDescriptionFactory;
use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use LogicException;
use Mcp\Server\RequestContext;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runs after the mcp-bundle McpPass, which puts the input schema made from the handler parameters into each addTool
 * call of the server builder; a tool with #[AsMcpTool] gets the schema of its input DTO instead and the fields of its
 * output appended to its description.
 */
final class McpToolSchemaCompilerPass implements CompilerPassInterface
{
    public const int PRIORITY = -10;

    private const string ADD_TOOL_METHOD = 'addTool';
    private const int HANDLER_ARGUMENT = 0;
    private const int DESCRIPTION_ARGUMENT = 3;
    private const int INPUT_SCHEMA_ARGUMENT = 5;
    private const string DESCRIPTION_SEPARATOR = PHP_EOL . PHP_EOL;

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasParameter(McpCompilerPass::SERVER_NAME_PARAM)) {
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

        $schemaFactory = new McpSchemaFactory();
        $outputDescriptionFactory = new McpOutputDescriptionFactory($schemaFactory);
        $builder = $container->getDefinition($builderId);
        $methodCalls = $builder->getMethodCalls();
        foreach ($methodCalls as $index => [$method, $arguments]) {
            if (self::ADD_TOOL_METHOD !== $method) {
                continue;
            }
            [$class, $handlerMethod] = $arguments[self::HANDLER_ARGUMENT];
            if (false === is_string($class) || false === class_exists($class)) {
                continue;
            }
            $tool = $this->resolveTool($class);
            if (null === $tool) {
                continue;
            }
            if (is_string($tool->output)) {
                $methodCalls[$index][1][self::DESCRIPTION_ARGUMENT] = implode(self::DESCRIPTION_SEPARATOR, array_filter([
                    $arguments[self::DESCRIPTION_ARGUMENT],
                    $outputDescriptionFactory->createForClass($tool->output),
                ]));
            }
            if (null === $tool->input) {
                continue;
            }
            $this->assertInputComesFromRequestContext($class, $handlerMethod, $tool->input);
            $methodCalls[$index][1][self::INPUT_SCHEMA_ARGUMENT] = $schemaFactory->createForClass($tool->input);
        }

        $builder->setMethodCalls($methodCalls);
    }

    /**
     * @param class-string $class
     */
    private function resolveTool(string $class): ?AsMcpTool
    {
        $attributes = new ReflectionClass($class)
            ->getAttributes(AsMcpTool::class);
        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @param class-string $class
     * @param class-string $inputClass
     */
    private function assertInputComesFromRequestContext(string $class, string $method, string $inputClass): void
    {
        foreach (new ReflectionMethod($class, $method)->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && RequestContext::class === $type->getName()) {
                continue;
            }

            throw new LogicException(sprintf(
                'The MCP tool "%s::%s()" takes its input from #[AsMcpTool(input: %s)], so its only parameter is %s; move "$%s" into the input class.',
                $class,
                $method,
                $inputClass,
                RequestContext::class,
                $parameter->getName(),
            ));
        }
    }
}
