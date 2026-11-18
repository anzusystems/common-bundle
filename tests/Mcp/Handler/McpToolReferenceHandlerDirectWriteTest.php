<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\DirectWriteExampleTool;
use Closure;
use Doctrine\ORM\OptimisticLockException;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Result\CallToolResult;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Throwable;

final class McpToolReferenceHandlerDirectWriteTest extends AbstractMcpToolReferenceHandlerTestCase
{
    private const int WRITE_LIMIT = 5;

    private ?ExampleWritePreviewRequest $directWriteInput = null;

    public function testClientWithElicitationGetsTheResultWithoutAQuestion(): void
    {
        $handler = $this->createDirectWriteHandler();

        $result = $this->callDirectWriteTool($handler);

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame(1, $this->writeRuns);
        self::assertSame(self::TITLE, $this->directWriteInput?->title);
    }

    /**
     * @dataProvider refusedCallProvider
     */
    public function testRefusedCallDoesNotRunTheTool(
        bool $writeGranted,
        bool $readOnlyMode,
        bool $hasMainRequest,
        int $writeLimit,
        int $earlierCalls,
        string $expectedErrorStart,
    ): void {
        $requestStack = new RequestStack();
        if ($hasMainRequest) {
            $requestStack->push(new Request());
        }
        $handler = $this->createDirectWriteHandler(writeGranted: $writeGranted, requestStack: $requestStack, writeLimit: $writeLimit);
        for ($call = 0; $call < $earlierCalls; ++$call) {
            $this->callDirectWriteTool($handler);
        }

        $this->initAppReadOnlyMode($readOnlyMode);
        try {
            $result = $this->callDirectWriteTool($handler);
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertTrue($result->isError);
        self::assertStringStartsWith($expectedErrorStart, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame($earlierCalls, $this->writeRuns);
    }

    /**
     * @return array<string, array{bool, bool, bool, int, int, string}>
     */
    public static function refusedCallProvider(): array
    {
        return [
            'not granted to the token' => [
                false, false, false, self::WRITE_LIMIT, 0,
                McpWriteRejectedException::createNotGranted(DirectWriteExampleTool::NAME)->getMessage(),
            ],
            'read-only mode' => [
                true, true, false, self::WRITE_LIMIT, 0,
                McpWriteRejectedException::createReadOnlyMode()->getMessage(),
            ],
            'second write of the request' => [
                true, false, true, self::WRITE_LIMIT, 1,
                McpWriteRejectedException::createAlreadyWritten(DirectWriteExampleTool::NAME)->getMessage(),
            ],
            'write limit reached' => [
                true, false, false, 1, 1,
                McpWriteRejectedException::RATE_LIMIT_EXCEEDED_START,
            ],
        ];
    }

    public function testSuccessfulCallIsInTheAuditLogAndEndsTheWritesOfTheRequest(): void
    {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $handler = $this->createDirectWriteHandler(requestStack: $requestStack);

        $firstResult = $this->callDirectWriteTool($handler);
        $secondResult = $this->callDirectWriteTool($handler);

        self::assertFalse($firstResult->isError);
        self::assertFalse($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
        self::assertSame(
            McpWriteRejectedException::createAlreadyWritten(DirectWriteExampleTool::NAME)->getMessage(),
            $secondResult->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertSame(1, $this->writeRuns);
        self::assertCount(2, $this->insertedDocuments);
        self::assertNull($this->insertedDocuments[0][McpLogger::FIELD_ERROR]);
    }

    public function testFailedCallIsExcludedFromTheAuditLogAndKeepsItsToken(): void
    {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $handler = $this->createDirectWriteHandler(
            requestStack: $requestStack,
            writeLimit: 1,
            failure: new RuntimeException(self::HANDLED_EXCEPTION_MESSAGE),
        );

        $firstResult = $this->callDirectWriteTool($handler);
        $secondResult = $this->callDirectWriteTool($handler);

        self::assertSame(self::BACKEND_ERROR_MESSAGE, $firstResult->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertTrue($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
        self::assertStringStartsWith(
            McpWriteRejectedException::RATE_LIMIT_EXCEEDED_START,
            $secondResult->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertSame(1, $this->writeRuns);
    }

    public function testOptimisticLockAsksToCallTheToolAgain(): void
    {
        $handler = $this->createDirectWriteHandler(failure: new OptimisticLockException(self::OPTIMISTIC_LOCK_MESSAGE, null));

        $result = $this->callDirectWriteTool($handler);

        $this->assertToolError(McpWriteRejectedException::createRecordChanged(DirectWriteExampleTool::NAME)->getMessage(), $result);
    }

    private function callDirectWriteTool(McpToolReferenceHandler $handler): CallToolResult
    {
        $result = $handler->handle(
            new ToolReference($this->createSdkTool(DirectWriteExampleTool::NAME), [DirectWriteExampleTool::class, McpToolSignatureResolver::INVOKE_METHOD]),
            $this->createModernArguments(toolName: DirectWriteExampleTool::NAME, toolArguments: [self::TITLE_FIELD => self::TITLE]),
        );
        self::assertInstanceOf(CallToolResult::class, $result);

        return $result;
    }

    private function createDirectWriteHandler(
        bool $writeGranted = true,
        RequestStack $requestStack = new RequestStack(),
        int $writeLimit = self::WRITE_LIMIT,
        ?Throwable $failure = null,
    ): McpToolReferenceHandler {
        return $this->createHandler(
            tool: new DirectWriteExampleTool($this->createToolHandler($failure)),
            toolErrorExceptions: [RuntimeException::class => self::BACKEND_ERROR_MESSAGE],
            readOnlyTool: false,
            writeGranted: $writeGranted,
            requestStack: $requestStack,
            directWrite: true,
            rateLimiter: new RateLimiterFactory(
                ['id' => self::RATE_LIMITER, 'policy' => 'fixed_window', 'limit' => $writeLimit, 'interval' => '1 hour'],
                new InMemoryStorage(),
            ),
        );
    }

    /**
     * @return Closure(ExampleWritePreviewRequest): McpLogsByContextResponse
     */
    private function createToolHandler(?Throwable $failure): Closure
    {
        return function (ExampleWritePreviewRequest $request) use ($failure): McpLogsByContextResponse {
            ++$this->writeRuns;
            $this->directWriteInput = $request;
            if ($failure instanceof Throwable) {
                throw $failure;
            }

            return self::createResponse();
        };
    }
}
