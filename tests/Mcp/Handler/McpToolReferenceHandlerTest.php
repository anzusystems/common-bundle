<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolAccessRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Factory\McpPreviewHashFactory;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\McpWriteRateLimiter;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpInputEnricherInterface;
use AnzuSystems\CommonBundle\Mcp\Security\McpPreviewHashReservation;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteConfirmation;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception\ExampleToolCallRejectedException;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception\ExampleToolInputException;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\McpToolDefinitionsFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\StaticMcpToolScopeProvider;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleContextTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultWithoutKeyTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleResultWriteTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWritePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWriteTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\InputPermissionExampleTool;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\OptimisticLockException;
use Mcp\Capability\Registry;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use Mcp\Server\Stateless\InputContext;
use Mcp\Server\Stateless\RequestMeta;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final class McpToolReferenceHandlerTest extends TestCase
{
    private const int USER_ID = 42;
    private const int LIMIT = 5;
    private const int LIMIT_INVALID = 0;
    private const string TOOL_PERMISSION = 'cms_test_read';
    private const string BACKEND_ERROR_MESSAGE = 'Backend is temporarily unavailable, retry the call.';
    private const string INPUT_ERROR_MESSAGE = 'Invalid input.';
    private const string LEVEL = 'ERROR';
    private const string TITLE_FIELD = 'title';
    private const string QUESTIONS_FIELD = 'questions';
    private const string LIMIT_VIOLATION = 'limit: This value should be greater than or equal to 1.';
    private const string INVOKE_METHOD = '__invoke';
    private const string READ_TOOL_METHOD = 'listExamples';
    private const string PREVIEWED_WRITE_TOOL_NAME = 'create_example';
    private const string SECRET = 'test_secret';
    // The HMAC of the write tool, WRITE_ARGUMENTS and no current state under SECRET.
    private const string PREVIEW_HASH = 'd04da74f4926e319f2beddde58330604183699c996a026576e10c10cc8a1e375';
    private const string OTHER_PREVIEW_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string CONFIRM_KEY = McpWriteConfirmation::CONFIRM_KEY_PREFIX . self::PREVIEW_HASH;
    private const string OTHER_CONFIRM_KEY = McpWriteConfirmation::CONFIRM_KEY_PREFIX . self::OTHER_PREVIEW_HASH;
    private const int CONFIRM_KEY_LENGTH = 72;
    private const string TITLE = 'Test';
    private const string CLIENT_NAME = 'claude-code';
    private const string CLIENT_VERSION = '2.1.317';
    private const string LOCALE_EN = 'en';
    private const string LOCALE_SK = 'sk';
    private const string HASH_FIELD = 'previewHash';
    private const string ACTION_FIELD = 'action';
    private const string CONTENT_FIELD = 'content';
    private const string ACTION_ACCEPT = 'accept';
    private const string ACTION_DECLINE = 'decline';
    private const string ACTION_CANCEL = 'cancel';
    private const string SESSION_ARGUMENT = '_session';
    private const string REQUEST_ARGUMENT = '_request';
    private const string LOCALE_SEPARATOR = '|';
    private const string HASH_VIOLATION = 'previewHash: ' . ExampleWriteRequest::PREVIEW_HASH_MESSAGE;
    private const array WRITE_ARGUMENTS = [
        self::TITLE_FIELD => self::TITLE,
        self::HASH_FIELD => self::PREVIEW_HASH,
    ];
    private const array TOOL_NAMES = [
        ExampleSearchTool::NAME,
        ExampleWithoutInputTool::NAME,
        ExampleContextTool::NAME,
        ExampleErrorResultTool::NAME,
        ExampleErrorResultWithoutKeyTool::NAME,
        ExampleResultWriteTool::NAME,
        ExampleWriteTool::NAME,
        InputPermissionExampleTool::NAME,
    ];

    private array $insertedDocuments = [];
    private array $logRecords = [];
    private array $translations = [];
    private int $writeRuns = 0;
    private int $previewRuns = 0;
    private ?object $previewInput = null;

    public function testSuccessSerializesObjectResultAndLogsRequestParams(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => self::createResponse()),
        );

        $result = $this->callTool(handler: $handler, toolClass: ExampleSearchTool::class, toolName: ExampleSearchTool::NAME, limit: self::LIMIT);

        self::assertFalse($result->isError);
        self::assertSame(['auditLogs', 'appLogs', 'mcpToolCalls', 'hint'], array_keys($result->structuredContent));
        self::assertSame([], $result->structuredContent['auditLogs']);
        self::assertCount(1, $this->insertedDocuments);
        $document = $this->insertedDocuments[0];
        self::assertSame(McpLogger::LEVEL_NAME_INFO, $document['levelName']);
        self::assertSame(ExampleSearchTool::NAME, $document['tool']);
        self::assertSame(
            [
                'level' => self::LEVEL,
                'messageContains' => null,
                'contextId' => null,
                'from' => null,
                'until' => null,
                'limit' => self::LIMIT,
            ],
            $document['params'],
        );
        self::assertSame(self::USER_ID, $document['userId']);
        self::assertNull($document['error']);
    }

    public function testRequestContextIsInjectedAndCallToolResultIsPassedThroughAndLogged(): void
    {
        $handler = $this->createHandler(tool: new ExampleContextTool());

        $result = $this->callTool(handler: $handler, toolClass: ExampleContextTool::class, toolName: ExampleContextTool::NAME, limit: self::LIMIT);

        self::assertFalse($result->isError);
        self::assertSame(['tool' => ExampleContextTool::NAME, 'level' => self::LEVEL], $result->structuredContent);
        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(ExampleContextTool::NAME, $this->insertedDocuments[0]['tool']);
        self::assertSame(self::LIMIT, $this->insertedDocuments[0]['params']['limit']);
        self::assertNull($this->insertedDocuments[0]['error']);
    }

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

    public function testToolWithoutAsMcpToolIsDeniedBecauseItHasNoPermission(): void
    {
        $handler = $this->createHandler(tool: new ExampleTool());
        $reference = new ToolReference(
            $this->createSdkTool(self::READ_TOOL_METHOD),
            [ExampleTool::class, self::READ_TOOL_METHOD],
        );

        $result = $handler->handle($reference, $this->createArguments(self::READ_TOOL_METHOD, []));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertSame(
            McpToolAccessRejectedException::createNotGranted(self::READ_TOOL_METHOD)->getMessage(),
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
    }

    public function testInvalidRequestIsReturnedAsToolErrorWithoutRunningTheTool(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => throw new RuntimeException('must not run')),
        );

        $result = $this->callTool(handler: $handler, toolClass: ExampleSearchTool::class, toolName: ExampleSearchTool::NAME, limit: self::LIMIT_INVALID);

        self::assertTrue($result->isError);
        self::assertSame(
            McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame($result->structuredContent[McpToolReferenceHandler::ERROR_KEY], $this->insertedDocuments[0]['error']);
    }

    public function testInputExceptionIsReturnedAsToolErrorAndToolWithoutInputLogsEmptyParams(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            static fn (): McpLogsByContextResponse => throw ExampleToolInputException::createExample(self::INPUT_ERROR_MESSAGE),
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame(self::INPUT_ERROR_MESSAGE, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(self::INPUT_ERROR_MESSAGE, $this->insertedDocuments[0]['error']);
        self::assertSame([], $this->insertedDocuments[0]['params']);
    }

    /**
     * @dataProvider deniedToolProvider
     */
    public function testDeniedToolIsReturnedAsToolErrorWithoutRunningTheTool(
        bool $toolGranted,
        bool $writeGranted,
        string $expectedError,
    ): void {
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static fn (): McpLogsByContextResponse => throw new RuntimeException('must not run'),
            toolGranted: $toolGranted,
            readOnlyTool: false,
            writeGranted: $writeGranted,
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
    }

    /**
     * @return array<string, array{bool, bool, string}>
     */
    public static function deniedToolProvider(): array
    {
        return [
            'missing tool permission' => [
                false,
                true,
                McpToolAccessRejectedException::createNotGranted(ExampleWithoutInputTool::NAME)->getMessage(),
            ],
            'write tool not granted to the request' => [
                true,
                false,
                McpWriteRejectedException::createNotGranted(ExampleWithoutInputTool::NAME)->getMessage(),
            ],
        ];
    }

    /**
     * @dataProvider previewProvider
     */
    public function testPreviewRunsAsAReadToolOnlyWithItsWriteGrant(
        bool $writeGranted,
        int $expectedRuns,
        ?string $expectedError,
    ): void {
        $runs = 0;
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static function () use (&$runs): McpLogsByContextResponse {
                ++$runs;

                return self::createResponse();
            },
            writeGranted: $writeGranted,
            previewOf: self::PREVIEWED_WRITE_TOOL_NAME,
        );

        $this->initAppReadOnlyMode(true);
        try {
            $result = $this->callToolWithoutInput($handler);
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertSame($expectedRuns, $runs);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY] ?? null);
    }

    /**
     * @return array<string, array{bool, int, string|null}>
     */
    public static function previewProvider(): array
    {
        return [
            'write grant runs the preview also in read-only mode' => [true, 1, null],
            'missing write grant denies the preview like a tool the token may not use' => [
                false,
                0,
                McpToolAccessRejectedException::createNotGranted(ExampleWithoutInputTool::NAME)->getMessage(),
            ],
        ];
    }

    /**
     * @dataProvider checkOrderProvider
     */
    public function testWriteGrantIsCheckedBeforeValidationAndValidationBeforeReadOnlyMode(
        bool $writeGranted,
        string $expectedError,
    ): void {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => throw new RuntimeException('must not run')),
            readOnlyTool: false,
            writeGranted: $writeGranted,
        );

        $this->initAppReadOnlyMode(true);
        try {
            $result = $this->callTool(handler: $handler, toolClass: ExampleSearchTool::class, toolName: ExampleSearchTool::NAME, limit: self::LIMIT_INVALID);
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function checkOrderProvider(): array
    {
        return [
            'write grant before validation' => [
                false,
                McpWriteRejectedException::createNotGranted(ExampleSearchTool::NAME)->getMessage(),
            ],
            'validation before read-only mode' => [
                true,
                McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
            ],
        ];
    }

    /**
     * @dataProvider handledExceptionProvider
     */
    public function testHandledExceptionIsReturnedAsToolError(Throwable $exception, string $expectedError): void
    {
        $handler = $this->createHandlerWithoutInputTool(static fn (): McpLogsByContextResponse => throw $exception);

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame($expectedError, $this->insertedDocuments[0]['error']);
    }

    /**
     * @return array<string, array{Throwable, string}>
     */
    public static function handledExceptionProvider(): array
    {
        return [
            'validation exception lists every field error' => [
                new ValidationException()
                    ->addFormattedError(self::TITLE_FIELD, ValidationException::ERROR_FIELD_EMPTY)
                    ->addFormattedError(self::QUESTIONS_FIELD, ValidationException::ERROR_FIELD_LENGTH_MIN)
                    ->addFormattedError(self::QUESTIONS_FIELD, ValidationException::ERROR_FIELD_INVALID),
                McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . 'title: error_field_empty questions: error_field_length_min questions: error_field_invalid',
            ],
            'access denied' => [
                new AccessDeniedException(),
                McpToolErrorResolver::ACCESS_DENIED_MESSAGE,
            ],
        ];
    }

    public function testWriteCallInReadOnlyModeIsRejectedWithoutRunningTheTool(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static fn (): McpLogsByContextResponse => throw new RuntimeException('must not run'),
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
            'write call is included in the audit log' => [false, static fn (Request $request): null => null, false],
            'read call stays excluded from the audit log' => [true, static fn (Request $request): null => null, true],
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

    public function testConfiguredToolErrorExceptionIsMappedToMessage(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static fn (): McpLogsByContextResponse => throw new RuntimeException('backend down'),
            toolErrorExceptions: [RuntimeException::class => self::BACKEND_ERROR_MESSAGE],
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $this->insertedDocuments[0]['error']);
    }

    public function testValidationExceptionKeepsItsFieldErrorsOverAConfiguredMessage(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static fn (): McpLogsByContextResponse => throw new ValidationException()
                ->addFormattedError(self::TITLE_FIELD, ValidationException::ERROR_FIELD_EMPTY),
            toolErrorExceptions: [ValidationException::class => self::BACKEND_ERROR_MESSAGE],
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertSame(
            McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . 'title: error_field_empty',
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
    }

    public function testUnknownExceptionIsRethrownAndLoggedAsError(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            static fn (): McpLogsByContextResponse => throw new RuntimeException('boom'),
        );

        try {
            $this->callToolWithoutInput($handler);
            self::fail('Expected ' . RuntimeException::class);
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(
            sprintf(McpToolReferenceHandler::UNHANDLED_ERROR_FORMAT, RuntimeException::class),
            $this->insertedDocuments[0]['error'],
        );
    }

    public function testRoundOneAsksWithTheTranslatedMessageAndWritesNothing(): void
    {
        $handler = $this->createWriteHandler(userLocale: self::LOCALE_SK, logger: $this->createRecordingLogger());

        $result = $this->callWriteTool($handler, $this->createModernArguments());

        self::assertInstanceOf(InputRequiredResult::class, $result);
        self::assertSame(self::CONFIRM_KEY_LENGTH, strlen(self::CONFIRM_KEY));
        self::assertSame(
            [
                'resultType' => InputRequiredResult::RESULT_TYPE,
                'inputRequests' => [
                    self::CONFIRM_KEY => [
                        'method' => 'elicitation/create',
                        'params' => [
                            'message' => self::translated(ExampleWritePreviewResponse::MESSAGE_KEY, self::LOCALE_SK),
                            'requestedSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    McpWriteConfirmation::CONFIRM_FIELD => [
                                        'type' => 'boolean',
                                        'title' => self::translated(McpWriteConfirmation::FIELD_TITLE_KEY, self::LOCALE_SK),
                                    ],
                                ],
                                'required' => [McpWriteConfirmation::CONFIRM_FIELD],
                            ],
                        ],
                    ],
                ],
            ],
            json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertInstanceOf(ExampleWriteRequest::class, $this->previewInput);
        self::assertSame(1, $this->previewRuns);
        self::assertSame(0, $this->writeRuns);
        self::assertSame([], $this->insertedDocuments);
        self::assertCount(1, $this->logRecords);
        self::assertSame(McpToolCallLogger::CONFIRMATION_ASKED_LOG_MESSAGE, $this->logRecords[0]['message']);
        self::assertSame(ExampleWriteTool::NAME, $this->logRecords[0]['context']['tool']);
        self::assertSame(self::USER_ID, $this->logRecords[0]['context']['userId']);
        self::assertSame(self::TITLE, $this->logRecords[0]['context']['params'][self::TITLE_FIELD]);
        self::assertArrayHasKey('durationMs', $this->logRecords[0]['context']);
    }

    public function testAcceptedConfirmationChecksThePreviewAgainAndWrites(): void
    {
        $handler = $this->createWriteHandler();

        $result = $this->callWriteTool($handler, $this->createModernArguments(
            inputResponses: [self::CONFIRM_KEY => self::createAcceptedAnswer()],
        ));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame(1, $this->writeRuns);
        self::assertSame(1, $this->previewRuns);
        self::assertSame(McpLogger::LEVEL_NAME_INFO, $this->insertedDocuments[0]['levelName']);
    }

    /**
     * @dataProvider refusedAnswerProvider
     *
     * @param array<string, mixed> $answer
     */
    public function testAnyOtherAnswerRefusesTheWrite(array $answer): void
    {
        $handler = $this->createWriteHandler();

        $result = $this->callWriteTool($handler, $this->createModernArguments(
            inputResponses: [self::CONFIRM_KEY => $answer],
        ));

        $expectedError = McpWriteRejectedException::createConfirmationRefused(ExampleWriteTool::NAME)->getMessage();
        $this->assertToolError($expectedError, $result);
        self::assertSame(0, $this->writeRuns);
        self::assertSame(0, $this->previewRuns);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function refusedAnswerProvider(): array
    {
        return [
            'accept with confirm false' => [[
                self::ACTION_FIELD => self::ACTION_ACCEPT,
                self::CONTENT_FIELD => [McpWriteConfirmation::CONFIRM_FIELD => false],
            ]],
            'accept without confirm' => [[self::ACTION_FIELD => self::ACTION_ACCEPT, self::CONTENT_FIELD => []]],
            'accept with a confirm that is not a boolean' => [[
                self::ACTION_FIELD => self::ACTION_ACCEPT,
                self::CONTENT_FIELD => [McpWriteConfirmation::CONFIRM_FIELD => 'true'],
            ]],
            'decline' => [[self::ACTION_FIELD => self::ACTION_DECLINE]],
            'decline with confirm true' => [[
                self::ACTION_FIELD => self::ACTION_DECLINE,
                self::CONTENT_FIELD => [McpWriteConfirmation::CONFIRM_FIELD => true],
            ]],
            'cancel' => [[self::ACTION_FIELD => self::ACTION_CANCEL]],
            'accept without content' => [[self::ACTION_FIELD => self::ACTION_ACCEPT]],
            'unknown action' => [[
                self::ACTION_FIELD => 'maybe',
                self::CONTENT_FIELD => [McpWriteConfirmation::CONFIRM_FIELD => true],
            ]],
        ];
    }

    public function testAnswerToAnotherKeyAsksAgain(): void
    {
        $handler = $this->createWriteHandler();

        $result = $this->callWriteTool($handler, $this->createModernArguments(
            inputResponses: [self::OTHER_CONFIRM_KEY => self::createAcceptedAnswer()],
        ));

        self::assertInstanceOf(InputRequiredResult::class, $result);
        self::assertSame([self::CONFIRM_KEY], array_keys($result->inputRequests));
        self::assertSame(1, $this->previewRuns);
        self::assertSame(0, $this->writeRuns);
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

    /**
     * @dataProvider closedAccessProvider
     */
    public function testClosedAccessFailsBeforeThePreview(
        bool $toolGranted,
        bool $writeGranted,
        bool $readOnlyMode,
        string $expectedError,
    ): void {
        $handler = $this->createWriteHandler(toolGranted: $toolGranted, writeGranted: $writeGranted);

        $this->initAppReadOnlyMode($readOnlyMode);
        try {
            $result = $this->callWriteTool($handler, $this->createModernArguments());
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        $this->assertToolError($expectedError, $result);
        self::assertSame(0, $this->previewRuns);
        self::assertSame(0, $this->writeRuns);
    }

    /**
     * @return array<string, array{bool, bool, bool, string}>
     */
    public static function closedAccessProvider(): array
    {
        return [
            'missing tool permission' => [
                false,
                true,
                false,
                McpToolAccessRejectedException::createNotGranted(ExampleWriteTool::NAME)->getMessage(),
            ],
            'write tool not granted to the request' => [
                true,
                false,
                false,
                McpWriteRejectedException::createNotGranted(ExampleWriteTool::NAME)->getMessage(),
            ],
            'read-only mode' => [true, true, true, McpWriteRejectedException::createReadOnlyMode()->getMessage()],
        ];
    }

    /**
     * @dataProvider clientWithoutFormElicitationProvider
     */
    public function testClientWithoutFormElicitationIsNotAsked(ClientCapabilities $capabilities): void
    {
        $handler = $this->createWriteHandler();

        $result = $this->callWriteTool($handler, $this->createModernArguments(capabilities: $capabilities));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame(1, $this->writeRuns);
        self::assertSame(1, $this->previewRuns);
    }

    /**
     * @return array<string, array{ClientCapabilities}>
     */
    public static function clientWithoutFormElicitationProvider(): array
    {
        return [
            'no elicitation' => [new ClientCapabilities()],
            'url elicitation only' => [ClientCapabilities::fromArray(['elicitation' => ['url' => []]])],
        ];
    }

    public function testHandshakeClientIsNeverAsked(): void
    {
        $handler = $this->createWriteHandler();
        $session = new Session(new InMemorySessionStore());
        $session->set('protocol_version', ProtocolVersion::V2025_11_25->value);
        $session->set('client_capabilities', ['elicitation' => []]);
        $session->set('client_info', ['name' => self::CLIENT_NAME, 'version' => self::CLIENT_VERSION]);

        $result = $this->callWriteTool($handler, self::WRITE_ARGUMENTS + [
            self::SESSION_ARGUMENT => $session,
            self::REQUEST_ARGUMENT => new CallToolRequest(ExampleWriteTool::NAME, self::WRITE_ARGUMENTS),
        ]);

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame(1, $this->writeRuns);
        self::assertSame(1, $this->previewRuns);
    }

    public function testReadToolIsNotAsked(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => self::createResponse()),
        );
        $toolArguments = ['level' => self::LEVEL, 'limit' => self::LIMIT];

        $result = $handler->handle(
            new ToolReference($this->createSdkTool(ExampleSearchTool::NAME), [ExampleSearchTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleSearchTool::NAME, toolArguments: $toolArguments),
        );

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
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
            inputResponses: [self::CONFIRM_KEY => self::createAcceptedAnswer()],
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
        $reference = new ToolReference($this->createSdkTool(ExampleResultWriteTool::NAME), [ExampleResultWriteTool::class, self::INVOKE_METHOD]);

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
            'handled exception' => [static fn (): CallToolResult => throw new RuntimeException('backend down')],
            'optimistic lock' => [static fn (): CallToolResult => throw new OptimisticLockException('The row changed.', null)],
        ];
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

    public function testInputPermissionRefusesTheCallBeforeTheToolRuns(): void
    {
        $runs = 0;
        $enricher = $this->createMock(McpInputEnricherInterface::class);
        $enricher->expects(self::never())->method('enrich');
        $handler = $this->createHandler(
            tool: new InputPermissionExampleTool(static function () use (&$runs): McpLogsByContextResponse {
                ++$runs;

                return self::createResponse();
            }),
            inputGranted: false,
            inputEnricher: $enricher,
        );

        $result = $handler->handle(
            new ToolReference($this->createSdkTool(InputPermissionExampleTool::NAME), [InputPermissionExampleTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: InputPermissionExampleTool::NAME, toolArguments: []),
        );

        $this->assertToolError(McpToolErrorResolver::ACCESS_DENIED_MESSAGE, $result);
        self::assertSame(0, $runs);
    }

    public function testGrantedInputIsEnrichedBeforeTheToolRuns(): void
    {
        $enrichedInput = null;
        $toolInput = null;
        $enricher = $this->createMock(McpInputEnricherInterface::class);
        $enricher->expects(self::once())
            ->method('enrich')
            ->willReturnCallback(static function (object $input) use (&$enrichedInput): void {
                $enrichedInput = $input;
            });
        $handler = $this->createHandler(
            tool: new InputPermissionExampleTool(static function (object $input) use (&$toolInput): McpLogsByContextResponse {
                $toolInput = $input;

                return self::createResponse();
            }),
            inputEnricher: $enricher,
        );

        $handler->handle(
            new ToolReference($this->createSdkTool(InputPermissionExampleTool::NAME), [InputPermissionExampleTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: InputPermissionExampleTool::NAME, toolArguments: []),
        );

        self::assertNotNull($toolInput);
        self::assertSame($toolInput, $enrichedInput);
    }

    public function testToolWithoutInputPermissionIsNotEnriched(): void
    {
        $enricher = $this->createMock(McpInputEnricherInterface::class);
        $enricher->expects(self::never())->method('enrich');
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => self::createResponse()),
            inputEnricher: $enricher,
        );

        $handler->handle(
            new ToolReference($this->createSdkTool(ExampleSearchTool::NAME), [ExampleSearchTool::class, self::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleSearchTool::NAME, toolArguments: []),
        );
    }

    /**
     * @dataProvider questionLanguageProvider
     */
    public function testQuestionLanguage(?string $userLocale, ?string $fallbackLocale, ?string $expectedLocale): void
    {
        $handler = $this->createWriteHandler(userLocale: $userLocale, fallbackLocale: $fallbackLocale);

        $this->callWriteTool($handler, $this->createModernArguments());

        self::assertSame(
            [
                [
                    'id' => ExampleWritePreviewResponse::MESSAGE_KEY,
                    'parameters' => [ExampleWritePreviewResponse::TITLE_PARAMETER => self::TITLE],
                    'domain' => McpWriteConfirmation::TRANSLATION_DOMAIN,
                    'locale' => $expectedLocale,
                ],
                [
                    'id' => McpWriteConfirmation::FIELD_TITLE_KEY,
                    'parameters' => [],
                    'domain' => McpWriteConfirmation::TRANSLATION_DOMAIN,
                    'locale' => $expectedLocale,
                ],
            ],
            $this->translations,
        );
    }

    /**
     * @return array<string, array{string|null, string|null, string|null}>
     */
    public static function questionLanguageProvider(): array
    {
        return [
            'language of the user' => [self::LOCALE_EN, null, self::LOCALE_EN],
            'language of the user wins over the fallback' => [self::LOCALE_EN, self::LOCALE_SK, self::LOCALE_EN],
            'fallback for a user without a language' => [null, self::LOCALE_SK, self::LOCALE_SK],
            'default of the translator without a fallback' => [null, null, null],
        ];
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

    private static function createRequestMeta(ClientCapabilities $capabilities, ?Implementation $clientInfo = null): RequestMeta
    {
        return new RequestMeta(
            protocolVersion: ProtocolVersion::V2026_07_28->value,
            clientCapabilities: $capabilities,
            clientInfo: $clientInfo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function createAcceptedAnswer(): array
    {
        return [
            self::ACTION_FIELD => self::ACTION_ACCEPT,
            self::CONTENT_FIELD => [McpWriteConfirmation::CONFIRM_FIELD => true],
        ];
    }

    private static function translated(string $key, ?string $locale): string
    {
        return $key . self::LOCALE_SEPARATOR . $locale;
    }

    private function assertToolError(string $expectedError, CallToolResult|InputRequiredResult $result): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame($expectedError, $this->insertedDocuments[0]['error']);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function callWriteTool(McpToolReferenceHandler $handler, array $arguments): CallToolResult|InputRequiredResult
    {
        return $handler->handle(
            new ToolReference($this->createSdkTool(ExampleWriteTool::NAME), [ExampleWriteTool::class, self::INVOKE_METHOD]),
            $arguments,
        );
    }

    /**
     * @param array<string, mixed> $toolArguments
     * @param array<string, mixed>|null $inputResponses
     *
     * @return array<string, mixed>
     */
    private function createModernArguments(
        string $toolName = ExampleWriteTool::NAME,
        array $toolArguments = self::WRITE_ARGUMENTS,
        ?array $inputResponses = null,
        ClientCapabilities $capabilities = new ClientCapabilities(elicitationForm: true),
    ): array {
        $session = new Session(new InMemorySessionStore());
        $session->set(RequestMeta::class, self::createRequestMeta($capabilities));
        if (is_array($inputResponses)) {
            $session->set(InputContext::class, new InputContext($inputResponses));
        }

        return $toolArguments + [
            self::SESSION_ARGUMENT => $session,
            self::REQUEST_ARGUMENT => new CallToolRequest($toolName, $toolArguments),
        ];
    }

    private function createRecordingLogger(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context = []): void {
                $this->logRecords[] = ['message' => $message, 'context' => $context];
            });

        return $logger;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
                $this->translations[] = ['id' => $id, 'parameters' => $parameters, 'domain' => $domain, 'locale' => $locale];

                return self::translated($id, $locale);
            });

        return $translator;
    }

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    private function createWriteHandler(
        bool $writable = true,
        ?object $currentState = null,
        ?Throwable $previewException = null,
        array $toolErrorExceptions = [],
        bool $toolGranted = true,
        bool $writeGranted = true,
        RequestStack $requestStack = new RequestStack(),
        ?string $userLocale = null,
        ?string $fallbackLocale = null,
        LoggerInterface $logger = new NullLogger(),
    ): McpToolReferenceHandler {
        return $this->createHandler(
            tool: new ExampleWriteTool(function (): McpLogsByContextResponse {
                ++$this->writeRuns;

                return self::createResponse();
            }),
            toolErrorExceptions: $toolErrorExceptions,
            toolGranted: $toolGranted,
            readOnlyTool: false,
            writeGranted: $writeGranted,
            requestStack: $requestStack,
            previewTool: new ExampleWritePreviewTool(
                function (ExampleWritePreviewRequest $request) use ($writable, $currentState, $previewException): ExampleWritePreviewResponse {
                    ++$this->previewRuns;
                    $this->previewInput = $request;
                    if ($previewException instanceof Throwable) {
                        throw $previewException;
                    }

                    return new ExampleWritePreviewResponse($request->title, $writable, $currentState);
                },
            ),
            userLocale: $userLocale,
            fallbackLocale: $fallbackLocale,
            logger: $logger,
        );
    }

    private function createPreviewHash(string $writeToolName, ?object $currentState): string
    {
        $input = new ExampleWritePreviewRequest();
        $input->title = self::TITLE;

        return new McpPreviewHashFactory(McpSerializerFactory::create(), self::SECRET)
            ->create(writeToolName: $writeToolName, input: $input, currentState: $currentState);
    }

    private static function createResponse(): McpLogsByContextResponse
    {
        return new McpLogsByContextResponse(new McpLogsByContextResult(new ArrayCollection(), new ArrayCollection(), new ArrayCollection()));
    }

    private function initAppReadOnlyMode(bool $readOnlyMode): void
    {
        AnzuApp::init(
            appNamespace: AnzuApp::getAppNamespace(),
            appSystem: AnzuApp::getAppSystem(),
            appVersion: AnzuApp::getAppVersion(),
            appReadOnlyMode: $readOnlyMode,
            projectDir: AnzuApp::getProjectDir(),
            appEnv: AnzuApp::getAppEnv(),
        );
    }

    /**
     * @param class-string $toolClass
     */
    private function callTool(
        McpToolReferenceHandler $handler,
        string $toolClass,
        string $toolName,
        ?int $limit = null,
    ): CallToolResult {
        $toolArguments = is_int($limit) ? ['level' => self::LEVEL, 'limit' => $limit] : [];
        $result = $handler->handle(
            new ToolReference($this->createSdkTool($toolName), [$toolClass, self::INVOKE_METHOD]),
            $this->createArguments($toolName, $toolArguments),
        );
        self::assertInstanceOf(CallToolResult::class, $result);

        return $result;
    }

    private function callToolWithoutInput(McpToolReferenceHandler $handler): CallToolResult
    {
        return $this->callTool(handler: $handler, toolClass: ExampleWithoutInputTool::class, toolName: ExampleWithoutInputTool::NAME);
    }

    private function createSdkTool(string $toolName): Tool
    {
        return new Tool(
            name: $toolName,
            title: null,
            inputSchema: ['type' => 'object'],
            description: null,
            annotations: null,
        );
    }

    /**
     * @param array<string, mixed> $toolArguments
     *
     * @return array<string, mixed>
     */
    private function createArguments(string $toolName, array $toolArguments): array
    {
        return $toolArguments + [
            self::SESSION_ARGUMENT => new Session(new InMemorySessionStore()),
            self::REQUEST_ARGUMENT => new CallToolRequest($toolName, $toolArguments),
        ];
    }

    /**
     * @param Closure(): McpLogsByContextResponse $toolHandler
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    private function createHandlerWithoutInputTool(
        Closure $toolHandler,
        array $toolErrorExceptions = [],
        bool $toolGranted = true,
        bool $readOnlyTool = true,
        bool $writeGranted = true,
        RequestStack $requestStack = new RequestStack(),
        ?string $previewOf = null,
    ): McpToolReferenceHandler {
        return $this->createHandler(
            tool: new ExampleWithoutInputTool($toolHandler),
            toolErrorExceptions: $toolErrorExceptions,
            toolGranted: $toolGranted,
            readOnlyTool: $readOnlyTool,
            writeGranted: $writeGranted,
            requestStack: $requestStack,
            previewOf: $previewOf,
        );
    }

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    private function createHandler(
        object $tool,
        array $toolErrorExceptions = [],
        bool $toolGranted = true,
        bool $readOnlyTool = true,
        bool $writeGranted = true,
        RequestStack $requestStack = new RequestStack(),
        ?string $previewOf = null,
        ?object $previewTool = null,
        bool $inputGranted = true,
        ?McpInputEnricherInterface $inputEnricher = null,
        ?string $userLocale = null,
        ?string $fallbackLocale = null,
        LoggerInterface $logger = new NullLogger(),
    ): McpToolReferenceHandler {
        $this->insertedDocuments = [];

        $collection = $this->createMock(Collection::class);
        $collection
            ->method('insertOne')
            ->willReturnCallback(function (array $document): InsertOneResult {
                $this->insertedDocuments[] = $document;

                return $this->createStub(InsertOneResult::class);
            });

        $user = $this->createConfiguredMock(AnzuUser::class, ['getId' => self::USER_ID, 'getLocale' => $userLocale]);
        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($user);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(
                static fn (mixed $attribute): bool => InputPermissionExampleTool::INPUT_PERMISSION === $attribute ? $inputGranted : $toolGranted,
            );

        $toolPermissions = array_fill_keys(self::TOOL_NAMES, self::TOOL_PERMISSION);
        $readOnlyToolNames = $readOnlyTool ? self::TOOL_NAMES : [];
        $writeToolNamesByPreview = [];
        if (is_string($previewOf)) {
            unset($toolPermissions[ExampleWithoutInputTool::NAME]);
            $toolPermissions[$previewOf] = self::TOOL_PERMISSION;
            $readOnlyToolNames[] = ExampleWithoutInputTool::NAME;
            $writeToolNamesByPreview[ExampleWithoutInputTool::NAME] = $previewOf;
        }
        $registry = new Registry();
        $services = [$tool::class => static fn (): object => $tool];
        $signature = new McpToolSignatureResolver()
            ->resolve($tool::class);
        $toolSignatures = null === $signature ? [] : [$tool::NAME => $signature];
        if (is_object($previewTool)) {
            $readOnlyToolNames[] = $previewTool::NAME;
            $writeToolNamesByPreview[$previewTool::NAME] = $tool::NAME;
            $registry->registerTool($this->createSdkTool($previewTool::NAME), [$previewTool::class, self::INVOKE_METHOD]);
            $services[$previewTool::class] = static fn (): object => $previewTool;
            $toolSignatures[$previewTool::NAME] = new McpToolSignatureResolver()->resolve($previewTool::class);
        }
        $toolDefinitions = McpToolDefinitionsFactory::create(
            toolPermissions: $toolPermissions,
            readOnlyToolNames: $readOnlyToolNames,
            writeToolNamesByPreview: $writeToolNamesByPreview,
            toolSignatures: array_filter($toolSignatures),
            toolInputPermissions: $tool instanceof InputPermissionExampleTool ? [$tool::NAME => $tool::INPUT_PERMISSION] : [],
        );
        $toolAccessChecker = new McpToolAccessChecker(
            toolDefinitions: $toolDefinitions,
            security: $security,
            toolScopeProvider: new StaticMcpToolScopeProvider(
                new McpToolScope(writeToolNames: $writeGranted ? array_keys($toolPermissions) : []),
            ),
        );

        return new McpToolReferenceHandler(
            inner: new ReferenceHandler(new ServiceLocator($services)),
            toolAccessChecker: $toolAccessChecker,
            writeToolGuard: new McpWriteToolGuard(
                toolAccessChecker: $toolAccessChecker,
                requestStack: $requestStack,
                writeConfirmation: new McpWriteConfirmation(
                    translator: $this->createTranslator(),
                    currentUserProvider: $currentUserProvider,
                    fallbackLocale: $fallbackLocale,
                ),
                writeRateLimiter: new McpWriteRateLimiter(new ServiceLocator([]), $toolDefinitions, $currentUserProvider),
                previewHashReservation: new McpPreviewHashReservation(new ArrayAdapter(), $currentUserProvider),
            ),
            toolDefinitions: $toolDefinitions,
            previewHashFactory: new McpPreviewHashFactory(McpSerializerFactory::create(), self::SECRET),
            requestStack: $requestStack,
            inputEnricher: $inputEnricher,
            registry: $registry,
            toolErrorResolver: new McpToolErrorResolver($toolErrorExceptions),
            toolCallLogger: new McpToolCallLogger(
                currentUserProvider: $currentUserProvider,
                logger: $logger,
                mcpLogger: new McpLogger($collection),
            ),
            validator: Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator(),
            serializer: McpSerializerFactory::create(),
        );
    }
}
