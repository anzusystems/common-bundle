<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Loader\McpPromptLoader;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Runs after the mcp-bundle McpPass, whose addPrompt calls let the SDK take the description of a prompt argument only
 * from the docblock of the handler. An #[AsMcpPrompt] goes to McpPromptLoader instead, with arguments from the
 * #[McpField] of each handler parameter. A prompt of the plain #[McpPrompt] names no tools, so the prompt filter would
 * show it to every token; it fails the compilation instead.
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
                throw McpConfigurationException::createPlainPrompt($arguments[self::NAME_ARGUMENT], $class);
            }
            $prompts[] = [
                'prompt' => new Definition(Prompt::class, [
                    '$name' => $arguments[self::NAME_ARGUMENT],
                    '$title' => $arguments[self::TITLE_ARGUMENT],
                    '$description' => $arguments[self::DESCRIPTION_ARGUMENT],
                    '$arguments' => $this->createArgumentDefinitions($reflection, $arguments[self::NAME_ARGUMENT]),
                    '$icons' => $arguments[self::ICONS_ARGUMENT],
                    '$meta' => $arguments[self::META_ARGUMENT],
                ]),
                'handler' => [$class, $handlerMethod],
            ];
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
            $type = $parameter->getType();
            // The SDK injects a parameter of a class type (RequestContext, ClientGateway), so the client never sends it.
            if ($type instanceof ReflectionNamedType && false === $type->isBuiltin()) {
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
}
