<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpPrompt;
use AnzuSystems\CommonBundle\Mcp\Attribute\McpField;
use AnzuSystems\CommonBundle\Mcp\Prompt\McpPromptMessageTrait;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePermissionTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExplicitGrantExampleTool;
use Mcp\Schema\Content\PromptMessage;

#[AsMcpPrompt(
    name: ExamplePrompt::NAME,
    description: 'Summarizes an example.',
    tools: [ExamplePermissionTool::NAME, ExplicitGrantExampleTool::NAME],
)]
final readonly class ExamplePrompt
{
    use McpPromptMessageTrait;

    public const string NAME = 'example_summary';
    public const string TEXT = 'Summarize "{{ topic }}": list it with {{ example_permission }}, then read it with {{ example_explicit_grant }}.';

    public function __invoke(
        #[McpField(description: 'Topic to summarize.')]
        string $topic,
    ): PromptMessage {
        return $this->createUserMessage(self::TEXT, ['topic' => $topic]);
    }
}
