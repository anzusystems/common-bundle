<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleContextTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Closure;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validation;
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
    private const array TOOL_NAMES = [
        ExampleSearchTool::NAME,
        ExampleWithoutInputTool::NAME,
        ExampleContextTool::NAME,
        ExampleErrorResultTool::NAME,
    ];

    private array $insertedDocuments = [];

    public function testSuccessSerializesObjectResultAndLogsRequestParams(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => self::createResponse()),
        );

        $result = $this->callTool($handler, ExampleSearchTool::class, ExampleSearchTool::NAME, self::LIMIT);

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

        $result = $this->callTool($handler, ExampleContextTool::class, ExampleContextTool::NAME, self::LIMIT);

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

        $firstResult = $this->callTool($handler, ExampleErrorResultTool::class, ExampleErrorResultTool::NAME);
        $secondResult = $this->callTool($handler, ExampleErrorResultTool::class, ExampleErrorResultTool::NAME);

        self::assertTrue($firstResult->isError);
        self::assertSame(
            [McpToolReferenceHandler::ERROR_KEY => ExampleErrorResultTool::ERROR],
            $secondResult->structuredContent,
        );
        self::assertSame(ExampleErrorResultTool::ERROR, $this->insertedDocuments[0]['error']);
        self::assertTrue($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    public function testReferenceOfAToolWithoutAsMcpToolGoesToTheInnerHandlerUnchanged(): void
    {
        $handler = $this->createHandler(tool: new ExampleTool());
        $reference = new ToolReference(
            $this->createSdkTool(self::READ_TOOL_METHOD),
            [ExampleTool::class, self::READ_TOOL_METHOD],
        );

        $result = $handler->handle($reference, $this->createArguments(self::READ_TOOL_METHOD, []));

        self::assertSame([], $result);
        self::assertSame([], $this->insertedDocuments);
    }

    public function testInvalidRequestIsReturnedAsToolErrorWithoutRunningTheTool(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => throw new RuntimeException('must not run')),
        );

        $result = $this->callTool($handler, ExampleSearchTool::class, ExampleSearchTool::NAME, self::LIMIT_INVALID);

        self::assertTrue($result->isError);
        self::assertSame(
            McpToolReferenceHandler::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame($result->structuredContent[McpToolReferenceHandler::ERROR_KEY], $this->insertedDocuments[0]['error']);
    }

    public function testInputExceptionIsReturnedAsToolErrorAndToolWithoutInputLogsEmptyParams(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            static fn (): McpLogsByContextResponse => throw new McpToolInputException(self::INPUT_ERROR_MESSAGE),
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
                sprintf(McpToolReferenceHandler::TOOL_ACCESS_DENIED_MESSAGE, ExampleWithoutInputTool::NAME),
            ],
            'write tool not granted to the request' => [
                true,
                false,
                sprintf(McpWriteToolGuard::WRITE_TOOL_NOT_GRANTED_MESSAGE, ExampleWithoutInputTool::NAME),
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
            $result = $this->callTool($handler, ExampleSearchTool::class, ExampleSearchTool::NAME, self::LIMIT_INVALID);
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
                sprintf(McpWriteToolGuard::WRITE_TOOL_NOT_GRANTED_MESSAGE, ExampleSearchTool::NAME),
            ],
            'validation before read-only mode' => [
                true,
                McpToolReferenceHandler::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
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
                McpToolReferenceHandler::INVALID_ARGUMENTS_PREFIX . 'title: error_field_empty questions: error_field_length_min questions: error_field_invalid',
            ],
            'access denied' => [
                new AccessDeniedException(),
                McpToolReferenceHandler::ACCESS_DENIED_MESSAGE,
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
        self::assertSame(McpWriteToolGuard::READ_ONLY_MODE_MESSAGE, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
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
                    AuditLogResourceHelper::addResourceByEntity($request, new User()->setId(self::USER_ID));

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
            sprintf(McpWriteToolGuard::WRITE_TOOL_DONE_MESSAGE, ExampleWithoutInputTool::NAME),
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

    private static function createResponse(): McpLogsByContextResponse
    {
        return new McpLogsByContextResponse(new McpLogsByContextResult([], [], []));
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
        return $this->callTool($handler, ExampleWithoutInputTool::class, ExampleWithoutInputTool::NAME);
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
            '_session' => new Session(new InMemorySessionStore()),
            '_request' => new CallToolRequest($toolName, $toolArguments),
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
    ): McpToolReferenceHandler {
        return $this->createHandler(
            tool: new ExampleWithoutInputTool($toolHandler),
            toolErrorExceptions: $toolErrorExceptions,
            toolGranted: $toolGranted,
            readOnlyTool: $readOnlyTool,
            writeGranted: $writeGranted,
            requestStack: $requestStack,
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
    ): McpToolReferenceHandler {
        $this->insertedDocuments = [];

        $collection = $this->createMock(Collection::class);
        $collection
            ->method('insertOne')
            ->willReturnCallback(function (array $document): InsertOneResult {
                $this->insertedDocuments[] = $document;

                return $this->createStub(InsertOneResult::class);
            });

        $user = $this->createConfiguredMock(AnzuUser::class, ['getId' => self::USER_ID]);
        $currentUserProvider = $this->createMock(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($user);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => match ($attribute) {
                McpToolAccessChecker::WRITE_PERMISSION => $writeGranted,
                default => $toolGranted,
            });

        $toolAccessChecker = new McpToolAccessChecker(
            toolPermissions: array_fill_keys(self::TOOL_NAMES, self::TOOL_PERMISSION),
            security: $security,
            readOnlyToolNames: $readOnlyTool ? self::TOOL_NAMES : [],
        );

        return new McpToolReferenceHandler(
            inner: new ReferenceHandler(new ServiceLocator([$tool::class => static fn (): object => $tool])),
            toolAccessChecker: $toolAccessChecker,
            writeToolGuard: new McpWriteToolGuard(
                toolAccessChecker: $toolAccessChecker,
                requestStack: $requestStack,
            ),
            toolErrorResolver: new McpToolErrorResolver($toolErrorExceptions),
            toolCallLogger: new McpToolCallLogger(
                currentUserProvider: $currentUserProvider,
                logger: new NullLogger(),
                mcpLogger: new McpLogger($collection),
            ),
            validator: Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator(),
            serializer: McpSerializerFactory::create(),
            toolSignatures: array_filter([$tool::class => new McpToolSignatureResolver()->resolve($tool::class)]),
        );
    }
}
