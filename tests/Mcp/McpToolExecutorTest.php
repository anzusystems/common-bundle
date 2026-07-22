<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolInputException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\McpToolExecutor;
use AnzuSystems\Contracts\Entity\AnzuUser;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class McpToolExecutorTest extends TestCase
{
    private const int USER_ID = 42;
    private const string TOOL_NAME = 'test_tool';
    private const string BACKEND_ERROR_MESSAGE = 'Backend is temporarily unavailable, retry the call.';

    private array $insertedDocuments = [];

    public function testSuccessReturnsCallbackResultAndLogsInfo(): void
    {
        $result = $this->createExecutor()
            ->execute(self::TOOL_NAME, ['foo' => 'bar'], static fn (): array => ['ok' => true]);

        self::assertSame(['ok' => true], $result);
        self::assertCount(1, $this->insertedDocuments);
        $document = $this->insertedDocuments[0];
        self::assertSame(McpLogger::LEVEL_NAME_INFO, $document['levelName']);
        self::assertSame(self::TOOL_NAME, $document['tool']);
        self::assertSame(['foo' => 'bar'], $document['params']);
        self::assertSame(self::USER_ID, $document['userId']);
        self::assertNull($document['error']);
    }

    public function testInputExceptionIsReturnedAsToolError(): void
    {
        $result = $this->createExecutor()
            ->execute(
                self::TOOL_NAME,
                [],
                static fn (): array => throw new McpToolInputException('Invalid input.'),
            );

        self::assertSame('Invalid input.', $result[McpToolExecutor::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame('Invalid input.', $this->insertedDocuments[0]['error']);
    }

    public function testAccessDeniedIsReturnedAsToolError(): void
    {
        $result = $this->createExecutor()
            ->execute(
                self::TOOL_NAME,
                [],
                static fn (): array => throw new AccessDeniedException(),
            );

        self::assertStringContainsString('Access denied', $result[McpToolExecutor::ERROR_KEY]);
    }

    public function testConfiguredToolErrorExceptionIsMappedToMessage(): void
    {
        $executor = $this->createExecutor([RuntimeException::class => self::BACKEND_ERROR_MESSAGE]);

        $result = $executor->execute(
            self::TOOL_NAME,
            [],
            static fn (): array => throw new RuntimeException('backend down'),
        );

        self::assertSame(self::BACKEND_ERROR_MESSAGE, $result[McpToolExecutor::ERROR_KEY]);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $this->insertedDocuments[0]['error']);
    }

    public function testUnknownExceptionIsRethrownAndLoggedAsError(): void
    {
        $executor = $this->createExecutor();

        try {
            $executor->execute(self::TOOL_NAME, [], static fn (): array => throw new RuntimeException('boom'));
            self::fail('Expected ' . RuntimeException::class);
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
        self::assertSame(sprintf('Unhandled %s', RuntimeException::class), $this->insertedDocuments[0]['error']);
    }

    /**
     * @param array<class-string, string> $toolErrorExceptions
     */
    private function createExecutor(array $toolErrorExceptions = []): McpToolExecutor
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

        return new McpToolExecutor(
            $currentUserProvider,
            new NullLogger(),
            new McpLogger($collection),
            $toolErrorExceptions,
        );
    }
}
