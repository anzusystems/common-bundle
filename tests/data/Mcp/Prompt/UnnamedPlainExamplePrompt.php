<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Prompt\McpPromptMessageTrait;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;

#[McpPrompt(description: 'Greets.')]
final readonly class UnnamedPlainExamplePrompt
{
    use McpPromptMessageTrait;

    public function __invoke(): PromptMessage
    {
        return $this->createUserMessage('Hello.');
    }
}
