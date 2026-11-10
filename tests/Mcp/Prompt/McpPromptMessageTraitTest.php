<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Prompt;

use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\ExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\PlainExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\TextExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePermissionTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExplicitGrantExampleTool;
use LogicException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use PHPUnit\Framework\TestCase;

final class McpPromptMessageTraitTest extends TestCase
{
    private const string TOPIC = 'elections';

    public function testToolPlaceholdersBecomeToolNamesAndArgumentsTheirValues(): void
    {
        $message = new ExamplePrompt()(self::TOPIC);

        self::assertSame(Role::User, $message->role);
        self::assertSame(
            sprintf('Summarize "%s": list it with %s, then read it with %s.', self::TOPIC, ExamplePermissionTool::NAME, ExplicitGrantExampleTool::NAME),
            $this->getText($message),
        );
    }

    public function testArgumentValueIsNotRenderedAgain(): void
    {
        $value = '{{ example_permission }}';

        $message = new TextExamplePrompt()
            ->render('Use {{ example_permission }} for "{{ topic }}".', ['topic' => $value]);

        self::assertSame(sprintf('Use %s for "%s".', ExamplePermissionTool::NAME, $value), $this->getText($message));
    }

    public function testPlaceholderOfAToolOutsideTheToolsFails(): void
    {
        $this->expectExceptionObject(McpConfigurationException::createPromptPlaceholderOutsideTools(
            TextExamplePrompt::NAME,
            [ExplicitGrantExampleTool::NAME],
        ));

        new TextExamplePrompt()
            ->render('Use {{ example_permission }} and {{ example_explicit_grant }}.', []);
    }

    public function testToolTheTextDoesNotNameFails(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Values "%s" have no placeholder', ExamplePermissionTool::NAME));

        new TextExamplePrompt()
            ->render('Summarize "{{ topic }}".', ['topic' => self::TOPIC]);
    }

    public function testPromptWithoutAsMcpPromptFails(): void
    {
        $this->expectExceptionObject(McpConfigurationException::createPromptWithoutAttribute(PlainExamplePrompt::class));

        new PlainExamplePrompt()();
    }

    private function getText(PromptMessage $message): string
    {
        self::assertInstanceOf(TextContent::class, $message->content);

        return $message->content->text;
    }
}
