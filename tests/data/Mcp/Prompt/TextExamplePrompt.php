<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Prompt\McpPromptMessageTrait;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePermissionTool;
use Mcp\Schema\Content\PromptMessage;

#[AsMcpPrompt(
    name: TextExamplePrompt::NAME,
    description: 'Renders the text a test gives it.',
    tools: [ExamplePermissionTool::NAME],
)]
final readonly class TextExamplePrompt
{
    use McpPromptMessageTrait;

    public const string NAME = 'text_example';

    /**
     * @param array<string, string> $arguments
     */
    public function render(string $text, array $arguments): PromptMessage
    {
        return $this->createUserMessage($text, $arguments);
    }
}
