<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Factory\McpOutputDescriptionFactory;
use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use Mcp\Capability\Discovery\ElementMetadataResolver;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Runs after the mcp-bundle McpPass, which puts the schema of the handler parameters into each addTool call.
 */
final class McpToolSchemaCompilerPass implements CompilerPassInterface
{
    use McpServerNameTrait;

    public const int PRIORITY = -10;

    private const string ADD_TOOL_METHOD = 'addTool';
    private const int HANDLER_ARGUMENT = 0;
    private const int NAME_ARGUMENT = 1;
    private const int DESCRIPTION_ARGUMENT = 3;
    private const int INPUT_SCHEMA_ARGUMENT = 5;
    private const string DESCRIPTION_SEPARATOR = PHP_EOL . PHP_EOL;
    private const string WRITE_LIMIT_SENTENCE = 'Writes through this tool have a limit per user.';
    private const string SHARED_WRITE_LIMIT_SENTENCE = 'Writes through %s share one limit per user.';
    private const string TOOL_NAME_SEPARATOR = ', ';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasParameter(McpCompilerPass::SERVER_NAME_PARAM)) {
            return;
        }
        $builderId = $this->resolveBuilderId($this->resolveServerName($container));
        if (false === $container->hasDefinition($builderId)) {
            return;
        }

        $schemaFactory = new McpSchemaFactory();
        $outputDescriptionFactory = new McpOutputDescriptionFactory($schemaFactory);
        $signatureResolver = new McpToolSignatureResolver();
        $builder = $container->getDefinition($builderId);
        $methodCalls = $builder->getMethodCalls();
        $rateLimiters = $this->resolveRateLimiters($container);
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
            $methodCalls[$index][1][self::DESCRIPTION_ARGUMENT] = implode(self::DESCRIPTION_SEPARATOR, array_filter([
                $arguments[self::DESCRIPTION_ARGUMENT],
                $this->createWriteLimitSentence(
                    ElementMetadataResolver::resolveName(new ReflectionMethod($class, $handlerMethod), $arguments[self::NAME_ARGUMENT]),
                    $rateLimiters,
                ),
                is_string($signature->outputClass) ? $outputDescriptionFactory->createForClass($signature->outputClass) : null,
            ]));
            if (is_string($signature->inputClass)) {
                $methodCalls[$index][1][self::INPUT_SCHEMA_ARGUMENT] = $schemaFactory->createForClass($signature->inputClass);
            }
        }

        $builder->setMethodCalls($methodCalls);
        foreach ($schemaFactory->getDescribedClasses() as $describedClass) {
            $container->addObjectResource($describedClass);
        }
    }

    /**
     * McpToolDefinitionsCompilerPass collected the limiters, and McpWriteRateLimiter names the tools of a limiter from
     * the same definitions, so the description and the error of the limit list the same tools.
     *
     * @return array<string, string> tool name => rate limiter name
     */
    private function resolveRateLimiters(ContainerBuilder $container): array
    {
        if (false === $container->hasDefinition(McpToolDefinitions::class)) {
            return [];
        }
        /** @var array<string, Definition> $definitions */
        $definitions = $container->getDefinition(McpToolDefinitions::class)
            ->getArgument(McpToolDefinitionsCompilerPass::DEFINITIONS_ARGUMENT);

        return array_map(
            static fn (Definition $definition): ?string => $definition->getArgument(McpToolDefinitionsCompilerPass::RATE_LIMITER_ARGUMENT),
            $definitions,
        ) |> array_filter(...);
    }

    /**
     * @param array<string, string> $rateLimiters
     */
    private function createWriteLimitSentence(string $toolName, array $rateLimiters): ?string
    {
        $rateLimiter = $rateLimiters[$toolName] ?? null;
        if (null === $rateLimiter) {
            return null;
        }
        $toolNames = array_keys(array: $rateLimiters, filter_value: $rateLimiter, strict: true);
        if (1 === count($toolNames)) {
            return self::WRITE_LIMIT_SENTENCE;
        }

        return sprintf(self::SHARED_WRITE_LIMIT_SENTENCE, implode(self::TOOL_NAME_SEPARATOR, $toolNames));
    }
}
