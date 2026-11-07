<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Loader\McpPromptLoader;
use AnzuSystems\CommonBundle\Mcp\Model\McpPromptDefinition;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Discovery\ElementMetadataResolver;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Server\ClientGateway;
use Mcp\Server\RequestContext;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Runs after the mcp-bundle McpPass, whose addPrompt calls take an argument description only from the handler docblock.
 * A plain #[McpPrompt] names no tools, so the prompt filter would show it to every token.
 */
final class McpPromptLoaderCompilerPass implements CompilerPassInterface
{
    use McpServerNameTrait;
    use McpTaggedElementTrait;

    public const int PRIORITY = -10;
    public const string ADD_PROMPT_METHOD = 'addPrompt';
    public const string ADD_LOADER_METHOD = 'addLoader';

    private const int HANDLER_ARGUMENT = 0;
    private const int NAME_ARGUMENT = 1;
    private const int TITLE_ARGUMENT = 2;
    private const int DESCRIPTION_ARGUMENT = 3;
    private const int ICONS_ARGUMENT = 4;
    private const int META_ARGUMENT = 5;
    private const array SDK_INJECTED_TYPES = [RequestContext::class, ClientGateway::class];

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasParameter(McpCompilerPass::SERVER_NAME_PARAM)) {
            return;
        }
        $builderId = $this->resolveBuilderId($this->resolveServerName($container));
        if (false === $container->hasDefinition($builderId)) {
            return;
        }

        $builder = $container->getDefinition($builderId);
        $methodCalls = $builder->getMethodCalls();
        $prompts = [];
        foreach ($methodCalls as $index => [$method, $arguments]) {
            if (self::ADD_PROMPT_METHOD !== $method) {
                continue;
            }
            [$class, $handlerMethod] = $arguments[self::HANDLER_ARGUMENT];
            $reflection = new ReflectionMethod($class, $handlerMethod);
            if (false === $this->resolveElementAttribute($reflection, McpPrompt::class) instanceof AsMcpPrompt) {
                throw McpConfigurationException::createPlainPrompt(
                    ElementMetadataResolver::resolveName($reflection, $arguments[self::NAME_ARGUMENT]),
                    $class,
                );
            }
            $prompts[] = new Definition(McpPromptDefinition::class, [
                '$prompt' => new Definition(Prompt::class, [
                    '$name' => $arguments[self::NAME_ARGUMENT],
                    '$title' => $arguments[self::TITLE_ARGUMENT],
                    '$description' => $arguments[self::DESCRIPTION_ARGUMENT],
                    '$arguments' => $this->createArgumentDefinitions($reflection, $arguments[self::NAME_ARGUMENT]),
                    '$icons' => $arguments[self::ICONS_ARGUMENT],
                    '$meta' => $arguments[self::META_ARGUMENT],
                ]),
                '$handler' => [$class, $handlerMethod],
            ]);
            unset($methodCalls[$index]);
        }
        if (empty($prompts)) {
            return;
        }

        $builder
            ->setMethodCalls(array_values($methodCalls))
            ->addMethodCall(self::ADD_LOADER_METHOD, [new Definition(McpPromptLoader::class, [$prompts])]);
    }

    /**
     * @return list<Definition>
     *
     * @throws McpConfigurationException
     */
    private function createArgumentDefinitions(ReflectionMethod $reflection, string $promptName): array
    {
        $arguments = [];
        foreach ($reflection->getParameters() as $parameter) {
            if ($this->isInjectedBySdk($parameter)) {
                continue;
            }
            $field = array_first($parameter->getAttributes(McpField::class, ReflectionAttribute::IS_INSTANCEOF))
                ?->newInstance()
                ?? throw McpConfigurationException::createPromptArgumentWithoutField($promptName, $parameter->getName());
            $arguments[] = new Definition(PromptArgument::class, [
                '$name' => $parameter->getName(),
                '$description' => $field->description,
                '$required' => false === $parameter->isOptional() && false === $parameter->isDefaultValueAvailable(),
            ]);
        }

        return $arguments;
    }

    /**
     * Mirrors the @internal InjectableParameters of the SDK; any other class type, e.g. a backed enum, the client sends.
     */
    private function isInjectedBySdk(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();
        if (false === $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }
        foreach (self::SDK_INJECTED_TYPES as $injectedType) {
            if (is_a(object_or_class: $type->getName(), class: $injectedType, allow_string: true)) {
                return true;
            }
        }

        return false;
    }
}
