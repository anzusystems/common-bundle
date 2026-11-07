<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use ReflectionClass;

/**
 * Reads the #[AsMcpPrompt] of the prompt class when it renders instead of taking McpPromptDefinitions: a trait cannot
 * add a constructor argument to the host prompt, and a #[Required] setter would leave the readonly property of a prompt
 * built without the container uninitialized.
 */
trait McpPromptMessageTrait
{
    /**
     * @param array<string, int|float|string> $arguments argument name => value
     *
     * @throws McpConfigurationException
     */
    private function createUserMessage(string $text, array $arguments = []): PromptMessage
    {
        $prompt = $this->resolvePromptAttribute();
        preg_match_all(pattern: McpField::PLACEHOLDER_PATTERN, subject: $text, matches: $matches);
        $unknownNames = array_diff($matches[1], $prompt->tools, array_keys($arguments))
            |> array_unique(...)
            |> array_values(...);
        if (false === empty($unknownNames)) {
            throw McpConfigurationException::createPromptPlaceholderOutsideTools((string) $prompt->name, $unknownNames);
        }

        return new PromptMessage(Role::User, new TextContent(McpField::fillPlaceholders(
            $text,
            [...array_combine($prompt->tools, $prompt->tools), ...$arguments],
        )));
    }

    private function resolvePromptAttribute(): AsMcpPrompt
    {
        return array_first(new ReflectionClass(static::class)->getAttributes(AsMcpPrompt::class))
            ?->newInstance()
            ?? throw McpConfigurationException::createPromptWithoutAttribute(static::class);
    }
}
