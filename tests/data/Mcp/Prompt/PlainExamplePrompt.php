<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Prompt\McpPromptMessageTrait;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;

#[McpPrompt(name: PlainExamplePrompt::NAME, description: 'Greets.')]
final readonly class PlainExamplePrompt
{
    use McpPromptMessageTrait;

    public const string NAME = 'plain_example';

    public function __invoke(): PromptMessage
    {
        return $this->createUserMessage('Hello.');
    }
}
