<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpPromptDefinitionsCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpToolDefinitionsCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\McpPromptDefinitions;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\ExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\PlainExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\UnknownToolExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePermissionTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExplicitGrantExampleTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class McpPromptDefinitionsCompilerPassTest extends TestCase
{
    public function testCollectsTheToolsOfAsMcpPromptsOnly(): void
    {
        $container = $this->createMcpContainer([ExamplePrompt::class, PlainExamplePrompt::class]);

        new McpPromptDefinitionsCompilerPass()
            ->process($container);

        self::assertSame(
            [ExamplePrompt::NAME => [ExamplePermissionTool::NAME, ExplicitGrantExampleTool::NAME]],
            $container->getDefinition(McpPromptDefinitions::class)
                ->getArgument(McpPromptDefinitionsCompilerPass::TOOL_NAMES_BY_PROMPT_ARGUMENT),
        );
    }

    public function testPromptNamingAnUnknownToolFailsAtCompileTime(): void
    {
        $container = $this->createMcpContainer([UnknownToolExamplePrompt::class]);

        $this->expectExceptionObject(McpConfigurationException::createPromptOfUnknownTool(
            UnknownToolExamplePrompt::NAME,
            UnknownToolExamplePrompt::UNKNOWN_TOOL_NAME,
        ));

        new McpPromptDefinitionsCompilerPass()
            ->process($container);
    }

    /**
     * @param list<class-string> $promptClasses
     */
    private function createMcpContainer(array $promptClasses): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(McpToolDefinitions::class)
            ->setArgument(McpToolDefinitionsCompilerPass::DEFINITIONS_ARGUMENT, [
                ExamplePermissionTool::NAME => new Definition(),
                ExplicitGrantExampleTool::NAME => new Definition(),
            ]);
        $container->register(McpPromptDefinitions::class)
            ->setArgument(McpPromptDefinitionsCompilerPass::TOOL_NAMES_BY_PROMPT_ARGUMENT, []);
        foreach ($promptClasses as $promptClass) {
            $container->register($promptClass)
                ->addTag(McpPromptDefinitionsCompilerPass::PROMPT_TAG);
        }

        return $container;
    }
}
