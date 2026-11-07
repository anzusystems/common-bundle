<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use Mcp\Capability\Attribute\CompletionProvider;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Mcp\Server\RequestContext;

#[AsMcpPrompt(
    name: ArgumentsExamplePrompt::NAME,
    description: 'Lists the examples of a topic.',
    title: 'Arguments example',
)]
final readonly class ArgumentsExamplePrompt
{
    public const string NAME = 'arguments_example';
    public const int LIMIT_MAX = 50;
    public const array LIMIT_COMPLETIONS = ['10', '50'];

    public function __invoke(
        RequestContext $context,
        #[McpField(description: 'Name or id of the topic.')]
        string $topic,
        #[McpField(description: 'Number of examples, at most {{ max }}.', descriptionValues: ['max' => self::LIMIT_MAX])]
        #[CompletionProvider(values: self::LIMIT_COMPLETIONS)]
        int $limit = 10,
    ): PromptMessage {
        return new PromptMessage(Role::User, new TextContent(sprintf('List %d examples of %s.', $limit, $topic)));
    }
}
