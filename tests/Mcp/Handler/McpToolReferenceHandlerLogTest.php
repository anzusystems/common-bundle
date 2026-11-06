<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultWithoutKeyTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Implementation;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use Mcp\Server\Stateless\RequestMeta;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class McpToolReferenceHandlerLogTest extends AbstractMcpToolReferenceHandlerTestCase
{
    public function testErrorResultOfAWriteToolIsLoggedAsErrorAndIsNotAWrite(): void
    {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $handler = $this->createHandler(
            tool: new ExampleErrorResultTool(),
            readOnlyTool: false,
            requestStack: $requestStack,
        );

        $firstResult = $this->callTool(handler: $handler, toolClass: ExampleErrorResultTool::class, toolName: ExampleErrorResultTool::NAME);
        $secondResult = $this->callTool(handler: $handler, toolClass: ExampleErrorResultTool::class, toolName: ExampleErrorResultTool::NAME);

        self::assertTrue($firstResult->isError);
        self::assertSame(
            [McpToolReferenceHandler::ERROR_KEY => ExampleErrorResultTool::ERROR],
            $secondResult->structuredContent,
        );
        self::assertSame(ExampleErrorResultTool::ERROR, $this->insertedDocuments[0]['error']);
        self::assertTrue($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    public function testErrorResultWithoutAnErrorKeyIsLoggedWithTheGenericError(): void
    {
        $handler = $this->createHandler(tool: new ExampleErrorResultWithoutKeyTool());

        $result = $this->callTool(handler: $handler, toolClass: ExampleErrorResultWithoutKeyTool::class, toolName: ExampleErrorResultWithoutKeyTool::NAME);

        self::assertTrue($result->isError);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(McpToolReferenceHandler::ERROR_RESULT_MESSAGE, $this->insertedDocuments[0]['error']);
    }

    /**
     * @dataProvider clientNameProvider
     *
     * @param array<string, string>|null $handshakeClientInfo
     */
    public function testClientNameIsLogged(?RequestMeta $requestMeta, ?array $handshakeClientInfo, ?string $expectedClient): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => self::createResponse()),
            logger: $this->createRecordingLogger(),
        );
        $session = new Session(new InMemorySessionStore());
        $session->set(RequestMeta::class, $requestMeta);
        $session->set('client_info', $handshakeClientInfo);
        $toolArguments = ['level' => self::LEVEL, 'limit' => self::LIMIT];

        $handler->handle(
            new ToolReference($this->createSdkTool(ExampleSearchTool::NAME), [ExampleSearchTool::class, self::INVOKE_METHOD]),
            $toolArguments + [
                self::SESSION_ARGUMENT => $session,
                self::REQUEST_ARGUMENT => new CallToolRequest(ExampleSearchTool::NAME, $toolArguments),
            ],
        );

        self::assertSame($expectedClient, $this->insertedDocuments[0]['client']);
        self::assertSame(McpToolCallLogger::CALL_LOG_MESSAGE, $this->logRecords[0]['message']);
        self::assertSame($expectedClient, $this->logRecords[0]['context']['client']);
    }

    /**
     * @return array<string, array{RequestMeta|null, array<string, string>|null, string|null}>
     */
    public static function clientNameProvider(): array
    {
        return [
            'modern client' => [
                self::createRequestMeta(new ClientCapabilities(), new Implementation(self::CLIENT_NAME, self::CLIENT_VERSION)),
                null,
                self::CLIENT_NAME,
            ],
            'modern client without its info' => [self::createRequestMeta(new ClientCapabilities()), null, null],
            'handshake client' => [null, ['name' => self::CLIENT_NAME, 'version' => self::CLIENT_VERSION], self::CLIENT_NAME],
            'no client info' => [null, null, null],
        ];
    }
}
