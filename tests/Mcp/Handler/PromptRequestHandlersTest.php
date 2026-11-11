<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolAccessRejectedException;
use AnzuSystems\CommonBundle\Mcp\Handler\FilterPromptsListRequestHandler;
use AnzuSystems\CommonBundle\Mcp\Handler\RejectUngrantedPromptRequestHandler;
use AnzuSystems\CommonBundle\Mcp\McpPromptDefinitions;
use AnzuSystems\CommonBundle\Mcp\Security\McpPromptAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\McpToolDefinitionsFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\StaticMcpToolScopeProvider;
use Closure;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptReference;
use Mcp\Schema\Request\CompletionCompleteRequest;
use Mcp\Schema\Request\GetPromptRequest;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\ResourceReference;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class PromptRequestHandlersTest extends TestCase
{
    private const string READ_TOOL = 'example_read';
    private const string LOG_TOOL = 'search_app_logs';
    private const string DRAFT_TOOL = 'example_explicit_grant';
    private const string WRITE_TOOL = 'example_write';
    private const string PREVIEW_TOOL = 'example_preview';
    private const string GRANTED_PERMISSION = 'example_read';
    private const string DENIED_PERMISSION = 'app_log_read';
    private const string TOOLLESS_PROMPT = 'greeting';
    private const string READ_PROMPT = 'example_prompt';
    private const string LOG_PROMPT = 'example_log_prompt';
    private const string DRAFT_PROMPT = 'example_draft_prompt';
    private const array TOOL_NAMES_BY_PROMPT = [
        self::TOOLLESS_PROMPT => [],
        self::READ_PROMPT => [self::READ_TOOL],
        self::LOG_PROMPT => [self::READ_TOOL, self::LOG_TOOL],
        self::DRAFT_PROMPT => [self::DRAFT_TOOL, self::PREVIEW_TOOL, self::WRITE_TOOL],
    ];
    private const int PAGE_SIZE = 20;
    private const int REQUEST_ID = 1;
    private const array COMPLETION_ARGUMENT = ['name' => 'topic', 'value' => 'ex'];
    private const string RESOURCE_URI = 'file:///example.txt';

    /**
     * @dataProvider toolScopeProvider
     *
     * @param list<string> $expectedPromptNames
     */
    public function testListShowsOnlyPromptsWhoseToolsAreAllGranted(McpToolScope $toolScope, array $expectedPromptNames): void
    {
        $response = $this->createListHandler($toolScope)
            ->handle(new ListPromptsRequest()->withId(self::REQUEST_ID), $this->createStub(SessionInterface::class));

        self::assertInstanceOf(Response::class, $response);
        self::assertInstanceOf(ListPromptsResult::class, $response->result);
        self::assertSame(
            $expectedPromptNames,
            array_map(static fn (Prompt $prompt): string => $prompt->name, $response->result->prompts),
        );
    }

    /**
     * @dataProvider toolScopeProvider
     *
     * @param list<string> $expectedPromptNames
     */
    public function testGetRejectsPromptsWhoseToolsAreNotAllGranted(McpToolScope $toolScope, array $expectedPromptNames): void
    {
        $this->assertUngrantedPromptsAreRejected(
            toolScope: $toolScope,
            expectedPromptNames: $expectedPromptNames,
            createRequest: static fn (string $promptName): Request => new GetPromptRequest($promptName),
        );
    }

    /**
     * @dataProvider toolScopeProvider
     *
     * @param list<string> $expectedPromptNames
     */
    public function testCompletionRejectsArgumentsOfPromptsWhoseToolsAreNotAllGranted(McpToolScope $toolScope, array $expectedPromptNames): void
    {
        $this->assertUngrantedPromptsAreRejected(
            toolScope: $toolScope,
            expectedPromptNames: $expectedPromptNames,
            createRequest: static fn (string $promptName): Request => new CompletionCompleteRequest(
                ref: new PromptReference($promptName),
                argument: self::COMPLETION_ARGUMENT,
            ),
        );
    }

    public function testCompletionOfAResourceIsLeftToTheSdk(): void
    {
        $request = new CompletionCompleteRequest(ref: new ResourceReference(self::RESOURCE_URI), argument: self::COMPLETION_ARGUMENT)
            ->withId(self::REQUEST_ID);

        self::assertFalse(new RejectUngrantedPromptRequestHandler($this->createPromptAccessChecker(new McpToolScope()))->supports($request));
    }

    /**
     * @return array<string, array{McpToolScope, list<string>}>
     */
    public static function toolScopeProvider(): array
    {
        return [
            'every read tool leaves out the explicitly granted one' => [
                new McpToolScope(writeToolNames: [self::WRITE_TOOL]),
                [self::TOOLLESS_PROMPT, self::READ_PROMPT],
            ],
            'explicitly granted tool with the write tool' => [
                new McpToolScope(readToolNames: [self::READ_TOOL], writeToolNames: [self::WRITE_TOOL], explicitToolNames: [self::DRAFT_TOOL]),
                [self::TOOLLESS_PROMPT, self::READ_PROMPT, self::DRAFT_PROMPT],
            ],
            'explicitly granted tool with every read tool and the write tool' => [
                new McpToolScope(writeToolNames: [self::WRITE_TOOL], explicitToolNames: [self::DRAFT_TOOL]),
                [self::TOOLLESS_PROMPT, self::READ_PROMPT, self::DRAFT_PROMPT],
            ],
            'explicitly granted tool without the write tool' => [
                new McpToolScope(readToolNames: [], explicitToolNames: [self::DRAFT_TOOL]),
                [self::TOOLLESS_PROMPT],
            ],
            'explicitly granted tool named only in the read tools' => [
                new McpToolScope(readToolNames: [self::READ_TOOL, self::DRAFT_TOOL], writeToolNames: [self::WRITE_TOOL]),
                [self::TOOLLESS_PROMPT, self::READ_PROMPT],
            ],
        ];
    }

    public function testSupportsOnlyItsRequest(): void
    {
        $toolScope = new McpToolScope();

        self::assertFalse($this->createListHandler($toolScope)->supports(new GetPromptRequest(self::READ_PROMPT)->withId(self::REQUEST_ID)));
        self::assertFalse(new RejectUngrantedPromptRequestHandler($this->createPromptAccessChecker($toolScope))->supports(new ListPromptsRequest()->withId(self::REQUEST_ID)));
    }

    private function createListHandler(McpToolScope $toolScope): FilterPromptsListRequestHandler
    {
        $prompts = [];
        foreach (array_keys(self::TOOL_NAMES_BY_PROMPT) as $promptName) {
            $prompts[$promptName] = new Prompt(name: $promptName);
        }
        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getPrompts')
            ->with(self::PAGE_SIZE, null)
            ->willReturn(new Page($prompts, null));

        return new FilterPromptsListRequestHandler(
            registry: $registry,
            promptAccessChecker: $this->createPromptAccessChecker($toolScope),
            pageSize: self::PAGE_SIZE,
        );
    }

    /**
     * @param list<string> $expectedPromptNames
     * @param Closure(string): Request $createRequest
     */
    private function assertUngrantedPromptsAreRejected(McpToolScope $toolScope, array $expectedPromptNames, Closure $createRequest): void
    {
        $handler = new RejectUngrantedPromptRequestHandler($this->createPromptAccessChecker($toolScope));

        foreach (array_keys(self::TOOL_NAMES_BY_PROMPT) as $promptName) {
            $request = $createRequest($promptName)
                ->withId(self::REQUEST_ID);
            $granted = in_array(needle: $promptName, haystack: $expectedPromptNames, strict: true);

            self::assertSame(false === $granted, $handler->supports($request), $promptName);
            if ($granted) {
                continue;
            }
            $error = $handler->handle($request, $this->createStub(SessionInterface::class));
            self::assertSame(Error::INVALID_PARAMS, $error->code);
            self::assertSame(McpToolAccessRejectedException::createPromptNotGranted($promptName)->getMessage(), $error->message);
        }
    }

    private function createPromptAccessChecker(McpToolScope $toolScope): McpPromptAccessChecker
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => self::GRANTED_PERMISSION === $attribute);

        return new McpPromptAccessChecker(
            promptDefinitions: new McpPromptDefinitions(self::TOOL_NAMES_BY_PROMPT),
            toolAccessChecker: new McpToolAccessChecker(
                toolDefinitions: McpToolDefinitionsFactory::create(
                    toolPermissions: [
                        self::READ_TOOL => self::GRANTED_PERMISSION,
                        self::LOG_TOOL => self::DENIED_PERMISSION,
                        self::DRAFT_TOOL => self::GRANTED_PERMISSION,
                        self::WRITE_TOOL => self::GRANTED_PERMISSION,
                    ],
                    readOnlyToolNames: [self::READ_TOOL, self::LOG_TOOL, self::DRAFT_TOOL, self::PREVIEW_TOOL],
                    writeToolNamesByPreview: [self::PREVIEW_TOOL => self::WRITE_TOOL],
                    explicitGrantToolNames: [self::DRAFT_TOOL],
                ),
                security: $security,
                toolScopeProvider: new StaticMcpToolScopeProvider($toolScope),
            ),
        );
    }
}
