<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

#[AsMcpPrompt(name: FieldlessArgumentExamplePrompt::NAME, description: 'Has an argument without #[McpField].')]
final readonly class FieldlessArgumentExamplePrompt
{
    public const string NAME = 'fieldless_argument_example';
    public const string ARGUMENT_NAME = 'topic';

    /**
     * @param string $topic name or id of the topic
     */
    public function __invoke(string $topic): PromptMessage
    {
        return new PromptMessage(Role::User, new TextContent($topic));
    }
}
