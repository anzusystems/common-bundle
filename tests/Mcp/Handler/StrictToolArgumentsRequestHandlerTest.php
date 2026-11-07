<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Handler\StrictToolArgumentsRequestHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\McpToolDefinitionsFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\StaticMcpToolScopeProvider;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Mcp\Capability\Registry;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

final class StrictToolArgumentsRequestHandlerTest extends TestCase
{
    private const string TOOL_NAME = 'example_read';
    private const string ALLOWED_ARGUMENT = 'title';
    private const string ALLOWED_VALUE = 'x';
    private const string UNKNOWN_ARGUMENT = 'nonsense';
    private const string PERMISSION = 'example_read';
    private const int USER_ID = 7;

    /**
     * @var list<array<string, mixed>>
     */
    private array $insertedDocuments = [];

    public function testUnknownArgumentOfAGrantedToolListsTheAllowedOnesAndIsLogged(): void
    {
        $handler = $this->createHandler(toolGranted: true);
        $request = $this->createRequest([self::UNKNOWN_ARGUMENT => 'a secret', self::ALLOWED_ARGUMENT => self::ALLOWED_VALUE]);

        self::assertTrue($handler->supports($request));
        $response = $handler->handle($request, new Session(new InMemorySessionStore()));

        self::assertInstanceOf(Response::class, $response);
        $result = $response->result;
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertSame(
            'Unknown argument "nonsense". Allowed arguments: title.',
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(self::TOOL_NAME, $this->insertedDocuments[0]['tool']);
        self::assertSame([self::ALLOWED_ARGUMENT => self::ALLOWED_VALUE], $this->insertedDocuments[0]['params']);
        self::assertSame($result->structuredContent[McpToolReferenceHandler::ERROR_KEY], $this->insertedDocuments[0][McpLogger::ERROR_FIELD]);
    }

    public function testToolThatIsNotGrantedIsLeftToTheSdk(): void
    {
        self::assertFalse($this->createHandler(toolGranted: false)->supports($this->createRequest([self::UNKNOWN_ARGUMENT => 1])));
    }

    public function testKnownArgumentsAreLeftToTheSdk(): void
    {
        self::assertFalse($this->createHandler(toolGranted: true)->supports($this->createRequest([self::ALLOWED_ARGUMENT => self::ALLOWED_VALUE])));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function createRequest(array $arguments): CallToolRequest
    {
        return new CallToolRequest(self::TOOL_NAME, $arguments)
            ->withId(1);
    }

    private function createHandler(bool $toolGranted): StrictToolArgumentsRequestHandler
    {
        $this->insertedDocuments = [];
        $registry = new Registry();
        $registry->registerTool(
            new Tool(
                name: self::TOOL_NAME,
                title: null,
                inputSchema: ['type' => 'object', 'properties' => [self::ALLOWED_ARGUMENT => ['type' => 'string']], 'required' => null],
                description: null,
                annotations: null,
            ),
            static fn (): null => null,
        );
        $security = $this->createStub(Security::class);
        $security->method('isGranted')
            ->willReturn($toolGranted);
        $collection = $this->createMock(Collection::class);
        $collection->method('insertOne')
            ->willReturnCallback(function (array $document): InsertOneResult {
                $this->insertedDocuments[] = $document;

                return $this->createStub(InsertOneResult::class);
            });
        $currentUserProvider = $this->createStub(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($this->createConfiguredMock(AnzuUser::class, ['getId' => self::USER_ID]));

        return new StrictToolArgumentsRequestHandler(
            registry: $registry,
            toolAccessChecker: new McpToolAccessChecker(
                toolDefinitions: McpToolDefinitionsFactory::create(
                    toolPermissions: [self::TOOL_NAME => self::PERMISSION],
                    readOnlyToolNames: [self::TOOL_NAME],
                ),
                security: $security,
                toolScopeProvider: new StaticMcpToolScopeProvider(new McpToolScope()),
            ),
            toolCallLogger: new McpToolCallLogger(
                currentUserProvider: $currentUserProvider,
                logger: new NullLogger(),
                mcpLogger: new McpLogger($collection),
            ),
        );
    }
}
