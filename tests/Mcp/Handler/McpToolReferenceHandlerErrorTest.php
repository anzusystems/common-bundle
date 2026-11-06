<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception\ExampleToolInputException;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

final class McpToolReferenceHandlerErrorTest extends AbstractMcpToolReferenceHandlerTestCase
{
    private const string QUESTIONS_FIELD = 'questions';

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
}
