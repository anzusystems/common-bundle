<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Factory\McpOutputDescriptionFactory;
use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runs after the mcp-bundle McpPass, which puts the input schema made from the handler parameters into each addTool
 * call of the server builder; a tool with #[AsMcpTool] gets the schema of the input class of its __invoke() instead and
 * the fields of its return class appended to its description.
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
        $signatureResolver = new McpToolSignatureResolver();
        $builder = $container->getDefinition($builderId);
        $methodCalls = $builder->getMethodCalls();
        foreach ($methodCalls as $index => [$method, $arguments]) {
            if (self::ADD_TOOL_METHOD !== $method) {
                continue;
            }
            [$class, $handlerMethod] = $arguments[self::HANDLER_ARGUMENT];
            if (McpToolSignatureResolver::INVOKE_METHOD !== $handlerMethod || false === is_string($class) || false === class_exists($class)) {
                continue;
            }
            $signature = $signatureResolver->resolve($class);
            if (null === $signature) {
                continue;
            }
            if (is_string($signature->outputClass)) {
                $methodCalls[$index][1][self::DESCRIPTION_ARGUMENT] = implode(self::DESCRIPTION_SEPARATOR, array_filter([
                    $arguments[self::DESCRIPTION_ARGUMENT],
                    $outputDescriptionFactory->createForClass($signature->outputClass),
                ]));
            }
            if (is_string($signature->inputClass)) {
                $methodCalls[$index][1][self::INPUT_SCHEMA_ARGUMENT] = $schemaFactory->createForClass($signature->inputClass);
            }
        }

        $builder->setMethodCalls($methodCalls);
    }
}
