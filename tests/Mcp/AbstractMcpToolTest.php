<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\AbstractMcpTool;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Closure;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validation;
use Throwable;

final class AbstractMcpToolTest extends TestCase
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

    private array $insertedDocuments = [];

    public function testSuccessSerializesObjectResultAndLogsRequestParams(): void
    {
        $tool = $this->createSearchTool(
            static fn (): McpLogsByContextResponse => new McpLogsByContextResponse(new McpLogsByContextResult([], [], [])),
        );

        $result = $tool($this->createContext(ExampleSearchTool::NAME, self::LIMIT));

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

    public function testInvalidRequestIsReturnedAsToolErrorWithoutRunningHandle(): void
    {
        $tool = $this->createSearchTool(static fn (): array => throw new RuntimeException('must not run'));

        $result = $tool($this->createContext(ExampleSearchTool::NAME, self::LIMIT_INVALID));

        self::assertTrue($result->isError);
        self::assertSame(
            AbstractMcpTool::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
            $result->structuredContent[AbstractMcpTool::ERROR_KEY],
        );
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame($result->structuredContent[AbstractMcpTool::ERROR_KEY], $this->insertedDocuments[0]['error']);
    }

    public function testInputExceptionIsReturnedAsToolErrorAndToolWithoutInputLogsEmptyParams(): void
    {
        $tool = $this->createToolWithoutInput(static fn (): array => throw new McpToolInputException(self::INPUT_ERROR_MESSAGE));

        $result = $tool($this->createContext(ExampleWithoutInputTool::NAME));

        self::assertTrue($result->isError);
        self::assertSame(self::INPUT_ERROR_MESSAGE, $result->structuredContent[AbstractMcpTool::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(self::INPUT_ERROR_MESSAGE, $this->insertedDocuments[0]['error']);
        self::assertSame([], $this->insertedDocuments[0]['params']);
    }

    /**
     * @dataProvider deniedToolProvider
     */
    public function testDeniedToolIsReturnedAsToolErrorWithoutRunningHandle(
        bool $toolGranted,
        bool $writeGranted,
        string $expectedError,
    ): void {
        $tool = $this->createToolWithoutInput(
            handler: static fn (): array => throw new RuntimeException('must not run'),
            toolGranted: $toolGranted,
            readOnlyTool: false,
            writeGranted: $writeGranted,
        );

        $result = $tool($this->createContext(ExampleWithoutInputTool::NAME));

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[AbstractMcpTool::ERROR_KEY]);
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
                sprintf(AbstractMcpTool::TOOL_ACCESS_DENIED_MESSAGE, ExampleWithoutInputTool::NAME),
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
        $this->initAppReadOnlyMode(true);
        try {
            $result = $this->createSearchTool(
                handler: static fn (): array => throw new RuntimeException('must not run'),
                readOnlyTool: false,
                writeGranted: $writeGranted,
            )($this->createContext(ExampleSearchTool::NAME, self::LIMIT_INVALID));
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[AbstractMcpTool::ERROR_KEY]);
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
                AbstractMcpTool::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
            ],
        ];
    }

    /**
     * @dataProvider handledExceptionProvider
     */
    public function testHandledExceptionIsReturnedAsToolError(Throwable $exception, string $expectedError): void
    {
        $tool = $this->createToolWithoutInput(static fn (): array => throw $exception);

        $result = $tool($this->createContext(ExampleWithoutInputTool::NAME));

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[AbstractMcpTool::ERROR_KEY]);
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
                AbstractMcpTool::INVALID_ARGUMENTS_PREFIX . 'title: error_field_empty questions: error_field_length_min questions: error_field_invalid',
            ],
            'access denied' => [
                new AccessDeniedException(),
                AbstractMcpTool::ACCESS_DENIED_MESSAGE,
            ],
        ];
    }

    public function testWriteCallInReadOnlyModeIsRejectedWithoutRunningHandle(): void
    {
        $tool = $this->createToolWithoutInput(
            handler: static fn (): array => throw new RuntimeException('must not run'),
            readOnlyTool: false,
        );

        $this->initAppReadOnlyMode(true);
        try {
            $result = $tool($this->createContext(ExampleWithoutInputTool::NAME));
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertTrue($result->isError);
        self::assertSame(McpWriteToolGuard::READ_ONLY_MODE_MESSAGE, $result->structuredContent[AbstractMcpTool::ERROR_KEY]);
    }

    /**
     * @dataProvider auditLogProvider
     *
     * @param Closure(Request): array<string, mixed> $handler
     */
    public function testOnlySuccessfulWriteCallIsIncludedInAuditLogs(
        bool $readOnlyTool,
        Closure $handler,
        bool $expectedExcluded,
    ): void {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tool = $this->createToolWithoutInput(
            handler: static fn (): array => $handler($request),
            readOnlyTool: $readOnlyTool,
            requestStack: $requestStack,
        );
        $tool($this->createContext(ExampleWithoutInputTool::NAME));

        self::assertSame($expectedExcluded, $request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    /**
     * @return array<string, array{bool, Closure(Request): array<string, mixed>, bool}>
     */
    public static function auditLogProvider(): array
    {
        return [
            'write call is included in the audit log' => [false, static fn (Request $request): array => [], false],
            'read call stays excluded from the audit log' => [true, static fn (Request $request): array => [], true],
            'failed write call stays excluded from the audit log' => [
                false,
                static fn (Request $request): array => throw new AccessDeniedException(),
                true,
            ],
            'failed write call that recorded a written resource is included in the audit log' => [
                false,
                static function (Request $request): array {
                    AuditLogResourceHelper::addResourceByEntity($request, new User()->setId(self::USER_ID));

                    throw new AccessDeniedException();
                },
                false,
            ],
            'write tool excluding itself stays excluded from the audit log' => [
                false,
                static function (Request $request): array {
                    AuditLogResourceHelper::excludeFromAuditLogs($request);

                    return [];
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
        $tool = $this->createToolWithoutInput(
            handler: static function () use ($request, &$handled): array {
                ++$handled;
                AuditLogResourceHelper::excludeFromAuditLogs($request);

                return [];
            },
            readOnlyTool: false,
            requestStack: $requestStack,
        );

        $tool($this->createContext(ExampleWithoutInputTool::NAME));
        $result = $tool($this->createContext(ExampleWithoutInputTool::NAME));

        self::assertSame(1, $handled);
        self::assertTrue($result->isError);
        self::assertSame(
            sprintf(McpWriteToolGuard::WRITE_TOOL_DONE_MESSAGE, ExampleWithoutInputTool::NAME),
            $result->structuredContent[AbstractMcpTool::ERROR_KEY],
        );
        self::assertTrue($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    public function testConfiguredToolErrorExceptionIsMappedToMessage(): void
    {
        $tool = $this->createToolWithoutInput(
            handler: static fn (): array => throw new RuntimeException('backend down'),
            toolErrorExceptions: [RuntimeException::class => self::BACKEND_ERROR_MESSAGE],
        );

        $result = $tool($this->createContext(ExampleWithoutInputTool::NAME));

        self::assertTrue($result->isError);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $result->structuredContent[AbstractMcpTool::ERROR_KEY]);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $this->insertedDocuments[0]['error']);
    }

    public function testUnknownExceptionIsRethrownAndLoggedAsError(): void
    {
        $tool = $this->createToolWithoutInput(static fn (): array => throw new RuntimeException('boom'));

        try {
            $tool($this->createContext(ExampleWithoutInputTool::NAME));
            self::fail('Expected ' . RuntimeException::class);
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(
            sprintf(AbstractMcpTool::UNHANDLED_ERROR_FORMAT, RuntimeException::class),
            $this->insertedDocuments[0]['error'],
        );
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

    private function createContext(string $toolName, ?int $limit = null): RequestContext
    {
        $arguments = is_int($limit) ? ['level' => self::LEVEL, 'limit' => $limit] : [];

        return new RequestContext(
            new Session(new InMemorySessionStore()),
            new CallToolRequest($toolName, $arguments),
        );
    }

    /**
     * @param Closure(SearchAppLogsRequest): (object|array<string, mixed>) $handler
     */
    private function createSearchTool(
        Closure $handler,
        bool $readOnlyTool = true,
        bool $writeGranted = true,
    ): ExampleSearchTool {
        $tool = new ExampleSearchTool($handler);
        $this->wireTool(
            tool: $tool,
            toolErrorExceptions: [],
            toolGranted: true,
            readOnlyTool: $readOnlyTool,
            writeGranted: $writeGranted,
            requestStack: new RequestStack(),
        );

        return $tool;
    }

    /**
     * @param Closure(): (object|array<string, mixed>) $handler
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    private function createToolWithoutInput(
        Closure $handler,
        array $toolErrorExceptions = [],
        bool $toolGranted = true,
        bool $readOnlyTool = true,
        bool $writeGranted = true,
        RequestStack $requestStack = new RequestStack(),
    ): ExampleWithoutInputTool {
        $tool = new ExampleWithoutInputTool($handler);
        $this->wireTool(
            tool: $tool,
            toolErrorExceptions: $toolErrorExceptions,
            toolGranted: $toolGranted,
            readOnlyTool: $readOnlyTool,
            writeGranted: $writeGranted,
            requestStack: $requestStack,
        );

        return $tool;
    }

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    private function wireTool(
        AbstractMcpTool $tool,
        array $toolErrorExceptions,
        bool $toolGranted,
        bool $readOnlyTool,
        bool $writeGranted,
        RequestStack $requestStack,
    ): void {
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

        $toolNames = [ExampleSearchTool::NAME, ExampleWithoutInputTool::NAME];
        $toolAccessChecker = new McpToolAccessChecker(
            toolPermissions: array_fill_keys($toolNames, self::TOOL_PERMISSION),
            security: $security,
            readOnlyToolNames: $readOnlyTool ? $toolNames : [],
        );
        $writeToolGuard = new McpWriteToolGuard(
            toolAccessChecker: $toolAccessChecker,
            requestStack: $requestStack,
        );
        $toolCallLogger = new McpToolCallLogger(
            currentUserProvider: $currentUserProvider,
            logger: new NullLogger(),
            mcpLogger: new McpLogger($collection),
        );
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
        $serializer = McpSerializerFactory::create();

        $tool->setToolAccessChecker($toolAccessChecker);
        $tool->setWriteToolGuard($writeToolGuard);
        $tool->setToolErrorResolver(new McpToolErrorResolver($toolErrorExceptions));
        $tool->setToolCallLogger($toolCallLogger);
        $tool->setValidator($validator);
        $tool->setSerializer($serializer);
    }
}
