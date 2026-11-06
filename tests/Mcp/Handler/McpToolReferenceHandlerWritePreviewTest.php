<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception\ExampleToolCallRejectedException;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception\ExampleToolInputException;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWritePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWriteTool;
use Closure;
use Doctrine\ORM\OptimisticLockException;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Result\CallToolResult;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

final class McpToolReferenceHandlerWritePreviewTest extends AbstractMcpToolReferenceHandlerTestCase
{
    private const string HASH_VIOLATION = 'previewHash: ' . ExampleWriteRequest::MESSAGE_PREVIEW_HASH_START . ExampleWritePreviewTool::NAME . ExampleWriteRequest::MESSAGE_PREVIEW_HASH_END;

    /**
     * @dataProvider auditLogProvider
     *
     * @param Closure(Request): mixed $toolSideEffect
     */
    public function testOnlySuccessfulWriteCallIsIncludedInAuditLogs(
        bool $readOnlyTool,
        Closure $toolSideEffect,
        bool $expectedExcluded,
    ): void {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static function () use ($toolSideEffect, $request): McpLogsByContextResponse {
                $toolSideEffect($request);

                return self::createResponse();
            },
            readOnlyTool: $readOnlyTool,
            requestStack: $requestStack,
        );
        $this->callToolWithoutInput($handler);

        self::assertSame($expectedExcluded, $request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    /**
     * @return array<string, array{bool, Closure(Request): mixed, bool}>
     */
    public static function auditLogProvider(): array
    {
        return [
            'write call is included in the audit log' => [
                false,
                static fn (Request $request): null => null,
                false,
            ],
            'read call stays excluded from the audit log' => [
                true,
                static fn (Request $request): null => null,
                true,
            ],
            'failed write call stays excluded from the audit log' => [
                false,
                static fn (Request $request): never => throw new AccessDeniedException(),
                true,
            ],
            'failed write call that recorded a written resource is included in the audit log' => [
                false,
                static function (Request $request): void {
                    AuditLogResourceHelper::setResourceByEntity($request, new User()->setId(self::USER_ID));

                    throw new AccessDeniedException();
                },
                false,
            ],
            'write tool excluding itself stays excluded from the audit log' => [
                false,
                static function (Request $request): void {
                    AuditLogResourceHelper::excludeFromAuditLogs($request);
                },
                true,
            ],
        ];
    }

    /**
     * @dataProvider invalidPreviewHashProvider
     *
     * @param array<string, string> $toolArguments
     */
    public function testInvalidPreviewHashFailsBeforeThePreview(array $toolArguments): void
    {
        $handler = $this->createWriteHandler();

        $result = $this->callWriteTool($handler, $this->createModernArguments(toolArguments: $toolArguments));

        $this->assertToolError(McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . self::HASH_VIOLATION, $result);
        self::assertSame(0, $this->previewRuns);
        self::assertSame(0, $this->writeRuns);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function invalidPreviewHashProvider(): array
    {
        return [
            'missing' => [[self::TITLE_FIELD => self::TITLE]],
            'other format' => [[self::TITLE_FIELD => self::TITLE, self::HASH_FIELD => 'not-a-hash']],
        ];
    }

    /**
     * @dataProvider previewExceptionProvider
     *
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    public function testPreviewExceptionIsTheToolError(Throwable $exception, array $toolErrorExceptions, string $expectedError): void
    {
        $handler = $this->createWriteHandler(previewException: $exception, toolErrorExceptions: $toolErrorExceptions);

        $result = $this->callWriteTool($handler, $this->createModernArguments());

        $this->assertToolError($expectedError, $result);
        self::assertSame(1, $this->previewRuns);
        self::assertSame(0, $this->writeRuns);
    }

    /**
     * @return array<string, array{Throwable, array<class-string<Throwable>, string>, string}>
     */
    public static function previewExceptionProvider(): array
    {
        return [
            'rejected call' => [ExampleToolCallRejectedException::createExample(self::INPUT_ERROR_MESSAGE), [], self::INPUT_ERROR_MESSAGE],
            'invalid input' => [ExampleToolInputException::createExample(self::INPUT_ERROR_MESSAGE), [], self::INPUT_ERROR_MESSAGE],
            'validation exception' => [
                new ValidationException()
                    ->addFormattedError(self::TITLE_FIELD, ValidationException::ERROR_FIELD_EMPTY),
                [],
                McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . 'title: error_field_empty',
            ],
            'access denied' => [new AccessDeniedException(), [], McpToolErrorResolver::ACCESS_DENIED_MESSAGE],
            'configured tool error exception' => [
                new RuntimeException('backend down'),
                [RuntimeException::class => self::BACKEND_ERROR_MESSAGE],
                self::BACKEND_ERROR_MESSAGE,
            ],
        ];
    }

    public function testUnhandledPreviewExceptionIsRethrown(): void
    {
        $handler = $this->createWriteHandler(previewException: new RuntimeException('boom'));

        try {
            $this->callWriteTool($handler, $this->createModernArguments());
            self::fail('Expected ' . RuntimeException::class);
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertSame(0, $this->writeRuns);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(
            sprintf(McpToolReferenceHandler::UNHANDLED_ERROR_FORMAT, RuntimeException::class),
            $this->insertedDocuments[0]['error'],
        );
    }

    public function testOtherPreviewHashIsRefused(): void
    {
        $handler = $this->createWriteHandler(currentState: new ExampleWritePreviewRequest());

        $result = $this->callWriteTool($handler, $this->createModernArguments());

        $this->assertToolError(McpWriteRejectedException::createPreviewStale(ExampleWritePreviewTool::NAME)->getMessage(), $result);
        self::assertSame(1, $this->previewRuns);
        self::assertSame(0, $this->writeRuns);
    }

    public function testOtherPreviewHashIsRefusedWithoutElicitationToo(): void
    {
        $handler = $this->createWriteHandler(currentState: new ExampleWritePreviewRequest());

        $result = $this->callWriteTool($handler, $this->createModernArguments(capabilities: new ClientCapabilities()));

        $this->assertToolError(McpWriteRejectedException::createPreviewStale(ExampleWritePreviewTool::NAME)->getMessage(), $result);
        self::assertSame(0, $this->writeRuns);
    }

    public function testNullPreviewHashRunsTheWriteWithoutAsking(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $handler = $this->createWriteHandler(writable: false, requestStack: $requestStack);

        $firstResult = $this->callWriteTool($handler, $this->createModernArguments());
        $secondResult = $this->callWriteTool($handler, $this->createModernArguments());

        self::assertInstanceOf(CallToolResult::class, $firstResult);
        self::assertFalse($firstResult->isError);
        self::assertSame(1, $this->previewRuns);
        self::assertSame(1, $this->writeRuns);
        self::assertInstanceOf(CallToolResult::class, $secondResult);
        self::assertSame(
            McpWriteRejectedException::createAlreadyWritten(ExampleWriteTool::NAME)->getMessage(),
            $secondResult->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
    }

    public function testOptimisticLockOfAWriteAsksForANewPreview(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleWriteTool(static function (): McpLogsByContextResponse {
                throw new OptimisticLockException('The row changed.', null);
            }),
            readOnlyTool: false,
            previewTool: new ExampleWritePreviewTool(
                static fn (ExampleWritePreviewRequest $request): ExampleWritePreviewResponse => new ExampleWritePreviewResponse($request->title),
            ),
        );

        $result = $this->callWriteTool($handler, $this->createModernArguments(capabilities: new ClientCapabilities()));

        $this->assertToolError(McpWriteRejectedException::createPreviewStale(ExampleWritePreviewTool::NAME)->getMessage(), $result);
    }

    public function testPreviewCalledDirectlyIsSigned(): void
    {
        $handler = $this->createWriteHandler();

        $result = $handler->handle(
            new ToolReference($this->createSdkTool(ExampleWritePreviewTool::NAME), [ExampleWritePreviewTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleWritePreviewTool::NAME, toolArguments: [self::TITLE_FIELD => self::TITLE]),
        );

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertSame(self::PREVIEW_HASH, $result->structuredContent[self::HASH_FIELD]);
    }

    public function testPreviewThatIsNotWritableHasNoHash(): void
    {
        $handler = $this->createWriteHandler(writable: false);

        $result = $handler->handle(
            new ToolReference($this->createSdkTool(ExampleWritePreviewTool::NAME), [ExampleWritePreviewTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleWritePreviewTool::NAME, toolArguments: [self::TITLE_FIELD => self::TITLE]),
        );

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertNull($result->structuredContent[self::HASH_FIELD]);
    }
}
