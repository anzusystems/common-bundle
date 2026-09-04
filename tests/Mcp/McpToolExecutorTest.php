<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\CommonBundle\Mcp\Model\McpLogsByContextResult;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\Contracts\Entity\AnzuUser;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validation;

final class McpToolExecutorTest extends TestCase
{
    private const int USER_ID = 42;
    private const int LIMIT = 5;
    private const int LIMIT_INVALID = 0;
    private const string TOOL_NAME = 'test_tool';
    private const string TOOL_PERMISSION = 'cms_test_read';
    private const string BACKEND_ERROR_MESSAGE = 'Backend is temporarily unavailable, retry the call.';
    private const string LEVEL = 'ERROR';

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

    public function testMissingToolPermissionIsReturnedAsToolErrorWithoutRunningCallback(): void
    {
        $result = $this->createExecutor(toolGranted: false)
            ->execute(self::TOOL_NAME, null, static fn (): array => throw new RuntimeException('must not run'));

        self::assertTrue($result->isError);
        self::assertStringContainsString('Access denied', $result->structuredContent[McpToolExecutor::ERROR_KEY]);
        self::assertStringContainsString(self::TOOL_NAME, $result->structuredContent[McpToolExecutor::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
    }

    public function testAccessDeniedIsReturnedAsToolError(): void
    {
        $result = $this->createExecutor()
            ->execute(
                self::TOOL_NAME,
                null,
                static fn (): array => throw new AccessDeniedException(),
            );

        self::assertTrue($result->isError);
        self::assertStringContainsString('Access denied', $result->structuredContent[McpToolExecutor::ERROR_KEY]);
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
    private function createExecutor(array $toolErrorExceptions = [], bool $toolGranted = true): McpToolExecutor
    {
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
            ->willReturn($toolGranted);

        return new McpToolExecutor(
            $currentUserProvider,
            new NullLogger(),
            new McpLogger($collection),
            new McpToolAccessChecker([self::TOOL_NAME => self::TOOL_PERMISSION], $security),
            Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator(),
            McpSerializerFactory::create(),
            $toolErrorExceptions,
        );
    }
}
