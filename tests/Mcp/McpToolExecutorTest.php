<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Closure;
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

final class McpToolExecutorTest extends TestCase
{
    private const int USER_ID = 42;
    private const int LIMIT = 5;
    private const int LIMIT_INVALID = 0;
    private const string TOOL_NAME = 'test_tool';
    private const string TOOL_PERMISSION = 'cms_test_read';
    private const string BACKEND_ERROR_MESSAGE = 'Backend is temporarily unavailable, retry the call.';
    private const string LEVEL = 'ERROR';
    private const string TITLE_FIELD = 'title';
    private const string QUESTIONS_FIELD = 'questions';

    private array $insertedDocuments = [];

    public function testSuccessSerializesObjectResultAndLogsRequestParams(): void
    {
        $result = $this->createExecutor()
            ->execute(
                self::TOOL_NAME,
                $this->createRequest(self::LIMIT),
                static fn (): McpLogsByContextResponse => new McpLogsByContextResponse(new McpLogsByContextResult([], [], [])),
            );

        self::assertFalse($result->isError);
        self::assertSame(['auditLogs', 'appLogs', 'mcpToolCalls', 'hint'], array_keys($result->structuredContent));
        self::assertSame([], $result->structuredContent['auditLogs']);
        self::assertCount(1, $this->insertedDocuments);
        $document = $this->insertedDocuments[0];
        self::assertSame(McpLogger::LEVEL_NAME_INFO, $document['levelName']);
        self::assertSame(self::TOOL_NAME, $document['tool']);
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

    public function testInvalidRequestIsReturnedAsToolErrorWithoutRunningCallback(): void
    {
        $result = $this->createExecutor()
            ->execute(
                self::TOOL_NAME,
                $this->createRequest(self::LIMIT_INVALID),
                static fn (): array => throw new RuntimeException('must not run'),
            );

        self::assertTrue($result->isError);
        self::assertSame('Invalid arguments: limit: limit must be at least 1.', $result->structuredContent[McpToolExecutor::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame($result->structuredContent[McpToolExecutor::ERROR_KEY], $this->insertedDocuments[0]['error']);
    }

    public function testInputExceptionIsReturnedAsToolErrorAndNullRequestLogsEmptyParams(): void
    {
        $result = $this->createExecutor()
            ->execute(
                self::TOOL_NAME,
                null,
                static fn (): array => throw new McpToolInputException('Invalid input.'),
            );

        self::assertTrue($result->isError);
        self::assertSame('Invalid input.', $result->structuredContent[McpToolExecutor::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame('Invalid input.', $this->insertedDocuments[0]['error']);
        self::assertSame([], $this->insertedDocuments[0]['params']);
    }

    /**
     * @dataProvider deniedToolProvider
     */
    public function testDeniedToolIsReturnedAsToolErrorWithoutRunningCallback(
        bool $toolGranted,
        bool $writeGranted,
        string $expectedError,
    ): void {
        $result = $this->createExecutor(toolGranted: $toolGranted, readOnlyTool: false, writeGranted: $writeGranted)
            ->execute(self::TOOL_NAME, null, static fn (): array => throw new RuntimeException('must not run'));

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolExecutor::ERROR_KEY]);
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
                sprintf('Access denied — the current MCP user is not allowed to use the tool "%s".', self::TOOL_NAME),
            ],
            'write tool not granted to the request' => [
                true,
                false,
                sprintf('Access denied — the write tool "%s" is not granted to this request.', self::TOOL_NAME),
            ],
        ];
    }

    /**
     * @dataProvider handledExceptionProvider
     */
    public function testHandledExceptionIsReturnedAsToolError(Throwable $exception, string $expectedError): void
    {
        $result = $this->createExecutor()
            ->execute(
                toolName: self::TOOL_NAME,
                request: null,
                callback: static fn (): array => throw $exception,
            );

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolExecutor::ERROR_KEY]);
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
                'Invalid arguments: title: error_field_empty questions: error_field_length_min questions: error_field_invalid',
            ],
            'access denied' => [
                new AccessDeniedException(),
                'Access denied — the current MCP user is not allowed to access the requested resource.',
            ],
        ];
    }

    public function testWriteCallInReadOnlyModeIsRejectedWithoutRunningCallback(): void
    {
        $this->initAppReadOnlyMode(true);
        try {
            $result = $this->createExecutor(readOnlyTool: false)
                ->execute(
                    toolName: self::TOOL_NAME,
                    request: null,
                    callback: static fn (): array => throw new RuntimeException('must not run'),
                );
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertTrue($result->isError);
        self::assertSame(
            'The system is in read-only mode, changes are temporarily disabled — retry later.',
            $result->structuredContent[McpToolExecutor::ERROR_KEY],
        );
    }

    /**
     * @dataProvider auditLogProvider
     *
     * @param Closure(Request): array<string, mixed> $callback
     */
    public function testOnlySuccessfulWriteCallIsIncludedInAuditLogs(
        bool $readOnlyTool,
        Closure $callback,
        bool $expectedExcluded,
    ): void {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->createExecutor(readOnlyTool: $readOnlyTool, requestStack: $requestStack)
            ->execute(toolName: self::TOOL_NAME, request: null, callback: static fn (): array => $callback($request));

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
        $executor = $this->createExecutor(readOnlyTool: false, requestStack: $requestStack);

        $executor->execute(
            toolName: self::TOOL_NAME,
            request: null,
            callback: static function () use ($request): array {
                AuditLogResourceHelper::excludeFromAuditLogs($request);

                return [];
            },
        );
        $result = $executor->execute(
            toolName: self::TOOL_NAME,
            request: null,
            callback: static fn (): array => throw new RuntimeException('must not run'),
        );

        self::assertTrue($result->isError);
        self::assertSame(
            sprintf('A write tool already ran in this request — send the call of "%s" in a separate request.', self::TOOL_NAME),
            $result->structuredContent[McpToolExecutor::ERROR_KEY],
        );
        self::assertTrue($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    public function testConfiguredToolErrorExceptionIsMappedToMessage(): void
    {
        $executor = $this->createExecutor([RuntimeException::class => self::BACKEND_ERROR_MESSAGE]);

        $result = $executor->execute(
            self::TOOL_NAME,
            null,
            static fn (): array => throw new RuntimeException('backend down'),
        );

        self::assertTrue($result->isError);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $result->structuredContent[McpToolExecutor::ERROR_KEY]);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $this->insertedDocuments[0]['error']);
    }

    public function testUnknownExceptionIsRethrownAndLoggedAsError(): void
    {
        $executor = $this->createExecutor();

        try {
            $executor->execute(self::TOOL_NAME, null, static fn (): array => throw new RuntimeException('boom'));
            self::fail('Expected ' . RuntimeException::class);
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(sprintf('Unhandled %s', RuntimeException::class), $this->insertedDocuments[0]['error']);
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

    private function createRequest(int $limit): SearchAppLogsRequest
    {
        return new SearchAppLogsRequest(
            level: self::LEVEL,
            messageContains: null,
            contextId: null,
            from: null,
            until: null,
            limit: $limit,
        );
    }

    /**
     * @param array<class-string, string> $toolErrorExceptions
     */
    private function createExecutor(
        array $toolErrorExceptions = [],
        bool $toolGranted = true,
        bool $readOnlyTool = true,
        bool $writeGranted = true,
        RequestStack $requestStack = new RequestStack(),
    ): McpToolExecutor {
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

        return new McpToolExecutor(
            currentUserProvider: $currentUserProvider,
            logger: new NullLogger(),
            mcpLogger: new McpLogger($collection),
            toolAccessChecker: new McpToolAccessChecker(
                toolPermissions: [self::TOOL_NAME => self::TOOL_PERMISSION],
                security: $security,
                readOnlyToolNames: $readOnlyTool ? [self::TOOL_NAME] : [],
            ),
            validator: Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator(),
            serializer: McpSerializerFactory::create(),
            requestStack: $requestStack,
            toolErrorExceptions: $toolErrorExceptions,
        );
    }
}
