<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Handler\FilterToolsListRequestHandler;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\McpToolDefinitionsFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\StaticMcpToolScopeProvider;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Page;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class FilterToolsListRequestHandlerTest extends TestCase
{
    private const string GRANTED_TOOL_NAME = 'list_sites';
    private const string DENIED_TOOL_NAME = 'search_app_logs';
    private const string GRANTED_WRITE_TOOL_NAME = 'create_quiz';
    private const string NOT_GRANTED_WRITE_TOOL_NAME = 'update_robots_txt';
    private const string GRANTED_PREVIEW_TOOL_NAME = 'preview_quiz';
    private const string NOT_GRANTED_PREVIEW_TOOL_NAME = 'preview_robots_txt_update';
    private const string GRANTED_PERMISSION = 'cms_site_read';
    private const string DENIED_PERMISSION = 'cms_log_read';
    private const int PAGE_SIZE = 20;
    private const int REQUEST_ID = 1;

    public function testSupportsOnlyListToolsRequest(): void
    {
        $handler = $this->createHandler();

        self::assertTrue($handler->supports(new ListToolsRequest()->withId(self::REQUEST_ID)));
        self::assertFalse($handler->supports(new CallToolRequest(self::GRANTED_TOOL_NAME, [])->withId(self::REQUEST_ID)));
    }

    public function testHandleReturnsOnlyToolsWithGrantedPermissionAndGrantedWriteTools(): void
    {
        $response = $this->createHandler()
            ->handle(new ListToolsRequest()->withId(self::REQUEST_ID), $this->createStub(SessionInterface::class));

        self::assertInstanceOf(Response::class, $response);
        self::assertInstanceOf(ListToolsResult::class, $response->result);
        self::assertSame(
            [self::GRANTED_TOOL_NAME, self::GRANTED_WRITE_TOOL_NAME, self::GRANTED_PREVIEW_TOOL_NAME],
            array_map(static fn (Tool $tool): string => $tool->name, $response->result->tools),
        );
    }

    private function createHandler(): FilterToolsListRequestHandler
    {
        $tools = [];
        $toolNames = [
            self::GRANTED_TOOL_NAME,
            self::DENIED_TOOL_NAME,
            self::GRANTED_WRITE_TOOL_NAME,
            self::NOT_GRANTED_WRITE_TOOL_NAME,
            self::GRANTED_PREVIEW_TOOL_NAME,
            self::NOT_GRANTED_PREVIEW_TOOL_NAME,
        ];
        foreach ($toolNames as $toolName) {
            $tools[$toolName] = new Tool(name: $toolName, title: null, inputSchema: ['type' => 'object', 'properties' => [], 'required' => null], description: null, annotations: null);
        }
        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getTools')
            ->with(self::PAGE_SIZE, null)
            ->willReturn(new Page($tools, null));

        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => self::GRANTED_PERMISSION === $attribute);
        $toolAccessChecker = new McpToolAccessChecker(
            toolDefinitions: McpToolDefinitionsFactory::create(
                toolPermissions: [
                    self::GRANTED_TOOL_NAME => self::GRANTED_PERMISSION,
                    self::DENIED_TOOL_NAME => self::DENIED_PERMISSION,
                    self::GRANTED_WRITE_TOOL_NAME => self::GRANTED_PERMISSION,
                    self::NOT_GRANTED_WRITE_TOOL_NAME => self::GRANTED_PERMISSION,
                ],
                readOnlyToolNames: [
                    self::GRANTED_TOOL_NAME,
                    self::DENIED_TOOL_NAME,
                    self::GRANTED_PREVIEW_TOOL_NAME,
                    self::NOT_GRANTED_PREVIEW_TOOL_NAME,
                ],
                writeToolNamesByPreview: [
                    self::GRANTED_PREVIEW_TOOL_NAME => self::GRANTED_WRITE_TOOL_NAME,
                    self::NOT_GRANTED_PREVIEW_TOOL_NAME => self::NOT_GRANTED_WRITE_TOOL_NAME,
                ],
            ),
            security: $security,
            toolScopeProvider: new StaticMcpToolScopeProvider(
                new McpToolScope(writeToolNames: [self::GRANTED_WRITE_TOOL_NAME]),
            ),
        );

        return new FilterToolsListRequestHandler(registry: $registry, toolAccessChecker: $toolAccessChecker, pageSize: self::PAGE_SIZE);
    }
}
