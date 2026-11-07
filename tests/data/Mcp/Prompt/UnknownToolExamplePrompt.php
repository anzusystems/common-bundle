<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

#[AsMcpPrompt(
    name: UnknownToolExamplePrompt::NAME,
    description: 'Names a tool the server does not have.',
    tools: [UnknownToolExamplePrompt::UNKNOWN_TOOL_NAME],
)]
final readonly class UnknownToolExamplePrompt
{
    public const string NAME = 'unknown_tool_example';
    public const string UNKNOWN_TOOL_NAME = 'missing_tool';

    public function __invoke(): PromptMessage
    {
        return new PromptMessage(Role::User, new TextContent('Call missing_tool.'));
    }
}
