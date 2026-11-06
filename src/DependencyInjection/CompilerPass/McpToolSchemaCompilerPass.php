<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Factory\McpOutputDescriptionFactory;
use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runs after the mcp-bundle McpPass, which puts the input schema made from the handler parameters into each addTool
 * call of the server builder; a tool with #[AsMcpTool] gets the schema of the input class of its __invoke() instead and
 * the fields of its return class and its write limit appended to its description.
 */
final class McpToolSchemaCompilerPass implements CompilerPassInterface
{
    use McpServerNameTrait;

    public const int PRIORITY = -10;

    private const string ADD_TOOL_METHOD = 'addTool';
    private const int HANDLER_ARGUMENT = 0;
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
        $toolNamesByRateLimiter = $this->resolveToolNamesByRateLimiter($methodCalls);
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
                $this->createWriteLimitSentence($this->resolveToolAttribute($class), $toolNamesByRateLimiter),
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
     * @param list<array{string, array<array-key, mixed>}> $methodCalls
     *
     * @return array<string, list<string>>
     */
    private function resolveToolNamesByRateLimiter(array $methodCalls): array
    {
        $toolNames = [];
        foreach ($methodCalls as [$method, $arguments]) {
            if (self::ADD_TOOL_METHOD !== $method) {
                continue;
            }
            $class = $arguments[self::HANDLER_ARGUMENT][0] ?? null;
            if (false === is_string($class) || false === class_exists($class)) {
                continue;
            }
            $tool = $this->resolveToolAttribute($class);
            if (is_string($tool?->rateLimiter)) {
                $toolNames[$tool->rateLimiter][] = (string) $tool->name;
            }
        }

        return $toolNames;
    }

    /**
     * @param array<string, list<string>> $toolNamesByRateLimiter
     */
    private function createWriteLimitSentence(?AsMcpTool $tool, array $toolNamesByRateLimiter): ?string
    {
        $rateLimiter = $tool?->rateLimiter;
        if (null === $rateLimiter) {
            return null;
        }
        $toolNames = $toolNamesByRateLimiter[$rateLimiter];
        if (1 === count($toolNames)) {
            return self::WRITE_LIMIT_SENTENCE;
        }

        return sprintf(self::SHARED_WRITE_LIMIT_SENTENCE, implode(self::TOOL_NAME_SEPARATOR, $toolNames));
    }

    /**
     * @param class-string $class
     */
    private function resolveToolAttribute(string $class): ?AsMcpTool
    {
        $attributes = new ReflectionClass($class)
            ->getAttributes(AsMcpTool::class);

        return empty($attributes) ? null : $attributes[0]->newInstance();
    }
}
