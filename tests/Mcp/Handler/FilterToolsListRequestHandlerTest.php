<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Handler\FilterToolsListRequestHandler;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
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

    public function testHandleReturnsOnlyGrantedTools(): void
    {
        $response = $this->createHandler()
            ->handle(new ListToolsRequest()->withId(self::REQUEST_ID), $this->createStub(SessionInterface::class));

        self::assertInstanceOf(Response::class, $response);
        self::assertInstanceOf(ListToolsResult::class, $response->result);
        self::assertCount(1, $response->result->tools);
        self::assertSame(self::GRANTED_TOOL_NAME, $response->result->tools[0]->name);
    }

    private function createHandler(): FilterToolsListRequestHandler
    {
        $tools = [];
        foreach ([self::GRANTED_TOOL_NAME, self::DENIED_TOOL_NAME] as $toolName) {
            $tools[$toolName] = new Tool($toolName, null, ['type' => 'object'], null, null);
        }
        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getTools')
            ->with(self::PAGE_SIZE, null)
            ->willReturn(new Page($tools, null));

        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => self::GRANTED_PERMISSION === $attribute);
        $toolAccessChecker = new McpToolAccessChecker(
            [self::GRANTED_TOOL_NAME => self::GRANTED_PERMISSION, self::DENIED_TOOL_NAME => self::DENIED_PERMISSION],
            $security,
        );

        return new FilterToolsListRequestHandler($registry, $toolAccessChecker, self::PAGE_SIZE);
    }
}
