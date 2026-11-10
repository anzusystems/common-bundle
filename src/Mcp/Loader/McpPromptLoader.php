<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Loader;

use AnzuSystems\CommonBundle\Mcp\Model\McpPromptDefinition;
use Mcp\Capability\Attribute\CompletionProvider;
use Mcp\Capability\Completion\EnumCompletionProvider;
use Mcp\Capability\Completion\ListCompletionProvider;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use ReflectionAttribute;
use ReflectionMethod;

/**
 * Registers the #[AsMcpPrompt] prompts with the arguments McpPromptLoaderCompilerPass made from their #[McpField]s,
 * because the SDK takes the description of a prompt argument only from the @param tag $prompts of the handler.
 */
final readonly class McpPromptLoader implements LoaderInterface
{
    /**
     * @param list<McpPromptDefinition> $prompts
     */
    public function __construct(
        private array $prompts,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        foreach ($this->prompts as $prompt) {
            $registry->registerPrompt(
                prompt: $prompt->prompt,
                handler: $prompt->handler,
                completionProviders: $this->resolveCompletionProviders(new ReflectionMethod(...$prompt->handler)),
            );
        }
    }

    /**
     * Mirrors the SDK ReflectedElementLoader; an object provider of the attribute cannot be dumped into the container.
     *
     * @return array<string, class-string|object>
     */
    private function resolveCompletionProviders(ReflectionMethod $method): array
    {
        $completionProviders = [];
        foreach ($method->getParameters() as $parameter) {
            $attribute = array_first($parameter->getAttributes(CompletionProvider::class, ReflectionAttribute::IS_INSTANCEOF))
                ?->newInstance();
            if (null === $attribute) {
                continue;
            }
            $completionProviders[$parameter->getName()] = match (true) {
                null !== $attribute->provider => $attribute->provider,
                null !== $attribute->values => new ListCompletionProvider($attribute->values),
                default => new EnumCompletionProvider($attribute->enum),
            };
        }

        return $completionProviders;
    }
}
