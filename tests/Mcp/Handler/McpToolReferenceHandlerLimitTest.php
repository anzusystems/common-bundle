<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Factory\McpPreviewHashFactory;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpPreviewHashReservation;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\UnserializableExampleResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleResultWriteTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWritePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWriteTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\UnserializableResponseWriteTool;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use Closure;
use Doctrine\ORM\OptimisticLockException;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\InputRequiredResult;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class McpToolReferenceHandlerLimitTest extends AbstractMcpToolReferenceHandlerTestCase
{
    public function testWriteCallInReadOnlyModeIsRejectedWithoutRunningTheTool(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static fn (): McpLogsByContextResponse => throw new RuntimeException(self::MUST_NOT_RUN_MESSAGE),
            readOnlyTool: false,
        );

        $this->initAppReadOnlyMode(true);
        try {
            $result = $this->callToolWithoutInput($handler);
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertTrue($result->isError);
        self::assertSame(McpWriteRejectedException::createReadOnlyMode()->getMessage(), $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
    }

    public function testSecondWriteCallOfARequestIsRejectedWithoutAuditResourceAndKeepsTheFirstExclusion(): void
    {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $handled = 0;
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static function () use ($request, &$handled): McpLogsByContextResponse {
                ++$handled;
                AuditLogResourceHelper::excludeFromAuditLogs($request);

                return self::createResponse();
            },
            readOnlyTool: false,
            requestStack: $requestStack,
        );

        $this->callToolWithoutInput($handler);
        $result = $this->callToolWithoutInput($handler);

        self::assertSame(1, $handled);
        self::assertTrue($result->isError);
        self::assertSame(
            McpWriteRejectedException::createAlreadyWritten(ExampleWithoutInputTool::NAME)->getMessage(),
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertTrue($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    public function testRepeatedWriteWithoutACurrentStateIsRefused(): void
    {
        $handler = $this->createWriteHandler();
        $capabilities = new ClientCapabilities();

        $firstResult = $this->callWriteTool($handler, $this->createModernArguments(capabilities: $capabilities));
        $secondResult = $this->callWriteTool($handler, $this->createModernArguments(capabilities: $capabilities));

        self::assertInstanceOf(CallToolResult::class, $firstResult);
        self::assertFalse($firstResult->isError);
        self::assertInstanceOf(CallToolResult::class, $secondResult);
        self::assertTrue($secondResult->isError);
        self::assertSame(
            McpWriteRejectedException::createPreviewHashUsed(ExampleWriteTool::NAME, McpPreviewHashReservation::LIFETIME_SECONDS)->getMessage(),
            $secondResult->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertSame(1, $this->writeRuns);
        self::assertSame(2, $this->previewRuns);
    }

    public function testConfirmationRoundReservesNothing(): void
    {
        $handler = $this->createWriteHandler();

        $askingResult = $this->callWriteTool($handler, $this->createModernArguments());
        $answeredResult = $this->callWriteTool($handler, $this->createModernArguments(
            inputResponses: [self::CONFIRM_KEY => $this->createAcceptedAnswer()],
        ));

        self::assertInstanceOf(InputRequiredResult::class, $askingResult);
        self::assertInstanceOf(CallToolResult::class, $answeredResult);
        self::assertFalse($answeredResult->isError);
        self::assertSame(1, $this->writeRuns);
    }

    public function testWriteWithACurrentStateIsNotReserved(): void
    {
        $currentState = new ExampleWritePreviewRequest();
        $handler = $this->createWriteHandler(currentState: $currentState);
        $arguments = [self::TITLE_FIELD => self::TITLE, self::HASH_FIELD => $this->createPreviewHash(ExampleWriteTool::NAME, $currentState)];
        $capabilities = new ClientCapabilities();

        $this->callWriteTool($handler, $this->createModernArguments(toolArguments: $arguments, capabilities: $capabilities));
        $result = $this->callWriteTool($handler, $this->createModernArguments(toolArguments: $arguments, capabilities: $capabilities));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame(2, $this->writeRuns);
    }

    /**
     * @dataProvider failedWriteProvider
     *
     * @param Closure(): CallToolResult $failure
     */
    public function testFailedWriteReleasesThePreviewHash(Closure $failure): void
    {
        $runs = 0;
        $handler = $this->createHandler(
            tool: new ExampleResultWriteTool(static function () use (&$runs, $failure): CallToolResult {
                ++$runs;

                return $failure();
            }),
            toolErrorExceptions: [RuntimeException::class => self::BACKEND_ERROR_MESSAGE],
            readOnlyTool: false,
            previewTool: new ExampleWritePreviewTool(
                static fn (ExampleWritePreviewRequest $request): ExampleWritePreviewResponse => new ExampleWritePreviewResponse($request->title),
            ),
        );
        $arguments = $this->createModernArguments(
            toolName: ExampleResultWriteTool::NAME,
            toolArguments: [self::TITLE_FIELD => self::TITLE, self::HASH_FIELD => $this->createPreviewHash(ExampleResultWriteTool::NAME, null)],
            capabilities: new ClientCapabilities(),
        );
        $reference = new ToolReference($this->createSdkTool(ExampleResultWriteTool::NAME), [ExampleResultWriteTool::class, McpToolSignatureResolver::INVOKE_METHOD]);

        $firstResult = $handler->handle($reference, $arguments);
        $secondResult = $handler->handle($reference, $arguments);

        self::assertTrue($firstResult->isError);
        self::assertTrue($secondResult->isError);
        self::assertSame(2, $runs);
    }

    /**
     * @return array<string, array{Closure(): CallToolResult}>
     */
    public static function failedWriteProvider(): array
    {
        return [
            'error result' => [static fn (): CallToolResult => McpToolReferenceHandler::createErrorResult(self::INPUT_ERROR_MESSAGE)],
            'handled exception' => [static fn (): CallToolResult => throw new RuntimeException(self::HANDLED_EXCEPTION_MESSAGE)],
            'optimistic lock' => [static fn (): CallToolResult => throw new OptimisticLockException(self::OPTIMISTIC_LOCK_MESSAGE, null)],
        ];
    }

    public function testResponseFailureAfterTheWriteKeepsThePreviewHash(): void
    {
        $runs = 0;
        $handler = $this->createHandler(
            tool: new UnserializableResponseWriteTool(static function () use (&$runs): UnserializableExampleResponse {
                ++$runs;

                return new UnserializableExampleResponse();
            }),
            toolErrorExceptions: [RuntimeException::class => self::BACKEND_ERROR_MESSAGE],
            readOnlyTool: false,
            previewTool: new ExampleWritePreviewTool(
                static fn (ExampleWritePreviewRequest $request): ExampleWritePreviewResponse => new ExampleWritePreviewResponse($request->title),
            ),
        );
        $arguments = $this->createModernArguments(
            toolName: UnserializableResponseWriteTool::NAME,
            toolArguments: [self::TITLE_FIELD => self::TITLE, self::HASH_FIELD => $this->createPreviewHash(UnserializableResponseWriteTool::NAME, null)],
            capabilities: new ClientCapabilities(),
        );
        $reference = new ToolReference($this->createSdkTool(UnserializableResponseWriteTool::NAME), [UnserializableResponseWriteTool::class, McpToolSignatureResolver::INVOKE_METHOD]);

        $firstResult = $handler->handle($reference, $arguments);
        $secondResult = $handler->handle($reference, $arguments);

        self::assertSame(self::BACKEND_ERROR_MESSAGE, $firstResult->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(
            McpWriteRejectedException::createPreviewHashUsed(UnserializableResponseWriteTool::NAME, McpPreviewHashReservation::LIFETIME_SECONDS)->getMessage(),
            $secondResult->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertSame(1, $runs);
    }

    private function createPreviewHash(string $writeToolName, ?object $currentState): string
    {
        $input = new ExampleWritePreviewRequest();
        $input->title = self::TITLE;

        return new McpPreviewHashFactory(McpSerializerFactory::create(), self::SECRET)
            ->create(writeToolName: $writeToolName, input: $input, currentState: $currentState);
    }
}
