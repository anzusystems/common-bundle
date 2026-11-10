<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePermissionTool;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

#[AsMcpPrompt(
    name: ExamplePrompt::NAME,
    description: 'Takes the name of another prompt.',
    tools: [ExamplePermissionTool::NAME],
)]
final readonly class DuplicateNameExamplePrompt
{
    public function __invoke(): PromptMessage
    {
        return new PromptMessage(Role::User, new TextContent('Call example_permission.'));
    }
}
