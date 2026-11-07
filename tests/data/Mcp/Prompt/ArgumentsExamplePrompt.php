<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Enum\ExampleItemStatus;
use Mcp\Capability\Attribute\CompletionProvider;
use Mcp\Capability\Completion\ListCompletionProvider;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Mcp\Server\RequestContext;

#[AsMcpPrompt(
    name: ArgumentsExamplePrompt::NAME,
    description: 'Lists the examples of a topic.',
    tools: [],
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
        #[CompletionProvider(provider: ListCompletionProvider::class)]
        string $topic,
        #[McpField(description: 'Number of examples, at most {{ max }}.', descriptionValues: ['max' => self::LIMIT_MAX])]
        #[CompletionProvider(values: self::LIMIT_COMPLETIONS)]
        int $limit = 10,
        #[McpField(description: 'Status of the examples.')]
        #[CompletionProvider(enum: ExampleItemStatus::class)]
        ExampleItemStatus $status = ExampleItemStatus::Default,
    ): PromptMessage {
        return new PromptMessage(Role::User, new TextContent(sprintf('List %d %s examples of %s.', $limit, $status->value, $topic)));
    }
}
