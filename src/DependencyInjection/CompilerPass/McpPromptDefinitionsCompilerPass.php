<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\McpPromptDefinitions;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Discovery\ElementMetadataResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the tools of every #[AsMcpPrompt] into McpPromptDefinitions. It runs after McpToolDefinitionsCompilerPass,
 * whose tool definitions are the tools a prompt may name.
 */
final class McpPromptDefinitionsCompilerPass implements CompilerPassInterface
{
    use McpTaggedElementTrait;

    public const string PROMPT_TAG = 'mcp.prompt';
    public const string TOOL_NAMES_BY_PROMPT_ARGUMENT = '$toolNamesByPrompt';

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition(McpPromptDefinitions::class)) {
            return;
        }

        $toolNames = $container->getDefinition(McpToolDefinitions::class)
            ->getArgument(McpToolDefinitionsCompilerPass::DEFINITIONS_ARGUMENT)
            |> array_keys(...);
        $toolNamesByPrompt = [];
        foreach ($this->resolveTaggedMethods($container, self::PROMPT_TAG) as $reflection) {
            $prompt = $this->resolveElementAttribute($reflection, McpPrompt::class);
            if (false === $prompt instanceof AsMcpPrompt) {
                continue;
            }
            $promptName = ElementMetadataResolver::resolveName($reflection, $prompt->name);
            $this->assertToolsAreKnown(promptName: $promptName, promptToolNames: $prompt->tools, toolNames: $toolNames);
            $toolNamesByPrompt[$promptName] = $prompt->tools;
        }
        $container
            ->getDefinition(McpPromptDefinitions::class)
            ->replaceArgument(self::TOOL_NAMES_BY_PROMPT_ARGUMENT, $toolNamesByPrompt);
    }

    /**
     * @param list<string> $promptToolNames
     * @param list<string> $toolNames
     */
    private function assertToolsAreKnown(string $promptName, array $promptToolNames, array $toolNames): void
    {
        foreach ($promptToolNames as $toolName) {
            if (false === in_array(needle: $toolName, haystack: $toolNames, strict: true)) {
                throw McpConfigurationException::createPromptOfUnknownTool($promptName, $toolName);
            }
        }
    }
}
