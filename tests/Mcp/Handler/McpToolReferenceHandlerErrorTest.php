<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolArgumentsInputException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpDateWindowResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Serializer\Handler\Handlers\McpDateTimeHandler;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Exception\ExampleToolInputException;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\Contracts\AnzuApp;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Exception\MissingRequiredClientCapabilityException;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Result\CallToolResult;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

final class McpToolReferenceHandlerErrorTest extends AbstractMcpToolReferenceHandlerTestCase
{
    private const string QUESTIONS_FIELD = 'questions';
    private const string FROM = 'from';
    private const string UNTIL = 'until';
    private const string INVALID_DATE = 'not-a-date';
    private const string JULY_FIRST = '2026-07-01';
    private const string JULY_TENTH = '2026-07-10T00:00:00+02:00';
    private const string FAR_FUTURE = '2099-01-01';

    public function testInvalidRequestIsReturnedAsToolErrorWithoutRunningTheTool(): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => throw new RuntimeException(self::MUST_NOT_RUN_MESSAGE)),
        );

        $result = $this->callTool(handler: $handler, toolClass: ExampleSearchTool::class, toolName: ExampleSearchTool::NAME, limit: self::LIMIT_INVALID);

        self::assertTrue($result->isError);
        self::assertSame(
            McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0][McpLogger::FIELD_LEVEL_NAME]);
        self::assertSame($result->structuredContent[McpToolReferenceHandler::ERROR_KEY], $this->insertedDocuments[0][McpLogger::FIELD_ERROR]);
    }

    /**
     * @dataProvider invalidDateWindowProvider
     *
     * @param array<string, string> $window
     */
    public function testInvalidDateWindowIsAnInvalidArgumentWithoutRunningTheTool(array $window, string $expectedError): void
    {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => throw new RuntimeException(self::MUST_NOT_RUN_MESSAGE)),
        );

        $result = $this->callSearchTool($handler, $window);

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidDateWindowProvider(): array
    {
        return [
            'unreadable date' => [
                [self::FROM => self::INVALID_DATE],
                McpToolArgumentsInputException::createInvalidArgument(
                    propertyPath: self::FROM,
                    message: sprintf(McpDateTimeHandler::INVALID_VALUE_MESSAGE, self::INVALID_DATE),
                )->getMessage(),
            ],
            'until before from' => [
                [self::FROM => self::JULY_TENTH, self::UNTIL => self::JULY_FIRST],
                McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . sprintf(
                    McpToolErrorResolver::VIOLATION_FORMAT,
                    self::UNTIL,
                    strtr(McpDateWindowResolver::NOT_BEFORE_FROM_MESSAGE, ['{{ compared_value_path }}' => self::FROM]),
                ),
            ],
            'from in the future without until' => [
                [self::FROM => self::FAR_FUTURE],
                McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . sprintf(
                    McpToolErrorResolver::VIOLATION_FORMAT,
                    self::FROM,
                    McpDateWindowResolver::NOT_IN_FUTURE_MESSAGE,
                ),
            ],
        ];
    }

    /**
     * @dataProvider validDateWindowProvider
     *
     * @param array<string, string> $window
     */
    public function testValidDateWindowReachesTheToolInUtc(array $window, ?string $expectedFrom, ?string $expectedUntil): void
    {
        $request = null;
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static function (SearchAppLogsRequest $input) use (&$request): McpLogsByContextResponse {
                $request = $input;

                return self::createResponse();
            }),
        );

        $result = $this->callSearchTool($handler, $window);

        self::assertFalse($result->isError);
        self::assertInstanceOf(SearchAppLogsRequest::class, $request);
        self::assertSame($expectedFrom, $request->from?->format(DATE_ATOM));
        self::assertSame($expectedUntil, $request->until?->format(DATE_ATOM));
    }

    /**
     * @return array<string, array{array<string, string>, ?string, ?string}>
     */
    public static function validDateWindowProvider(): array
    {
        return [
            'no dates' => [[], null, null],
            'date only and offset' => [
                [self::FROM => self::JULY_FIRST, self::UNTIL => self::JULY_TENTH],
                '2026-07-01T00:00:00+00:00',
                '2026-07-09T22:00:00+00:00',
            ],
            'equal dates' => [
                [self::FROM => self::JULY_TENTH, self::UNTIL => self::JULY_TENTH],
                '2026-07-09T22:00:00+00:00',
                '2026-07-09T22:00:00+00:00',
            ],
            'from only' => [[self::FROM => self::JULY_TENTH], '2026-07-09T22:00:00+00:00', null],
        ];
    }

    public function testInputExceptionIsReturnedAsToolErrorAndToolWithoutInputLogsEmptyParams(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            static fn (): McpLogsByContextResponse => throw ExampleToolInputException::createExample(self::INPUT_ERROR_MESSAGE),
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame(self::INPUT_ERROR_MESSAGE, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0][McpLogger::FIELD_LEVEL_NAME]);
        self::assertSame(self::INPUT_ERROR_MESSAGE, $this->insertedDocuments[0][McpLogger::FIELD_ERROR]);
        self::assertSame([], $this->insertedDocuments[0][McpLogger::FIELD_PARAMS]);
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
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0][McpLogger::FIELD_LEVEL_NAME]);
        self::assertSame($expectedError, $this->insertedDocuments[0][McpLogger::FIELD_ERROR]);
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
                McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . implode(' ', [
                    sprintf(McpToolErrorResolver::VIOLATION_FORMAT, self::TITLE_FIELD, ValidationException::ERROR_FIELD_EMPTY),
                    sprintf(McpToolErrorResolver::VIOLATION_FORMAT, self::QUESTIONS_FIELD, ValidationException::ERROR_FIELD_LENGTH_MIN),
                    sprintf(McpToolErrorResolver::VIOLATION_FORMAT, self::QUESTIONS_FIELD, ValidationException::ERROR_FIELD_INVALID),
                ]),
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
            toolHandler: static fn (): McpLogsByContextResponse => throw new RuntimeException(self::HANDLED_EXCEPTION_MESSAGE),
            toolErrorExceptions: [RuntimeException::class => self::BACKEND_ERROR_MESSAGE],
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(self::BACKEND_ERROR_MESSAGE, $this->insertedDocuments[0][McpLogger::FIELD_ERROR]);
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
            McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . sprintf(McpToolErrorResolver::VIOLATION_FORMAT, self::TITLE_FIELD, ValidationException::ERROR_FIELD_EMPTY),
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
    }

    public function testUnknownExceptionIsReturnedAsToolErrorWithContextId(): void
    {
        $handler = $this->createHandlerWithoutInputTool(
            static fn (): McpLogsByContextResponse => throw new RuntimeException(self::UNHANDLED_EXCEPTION_MESSAGE),
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame(
            sprintf(McpToolReferenceHandler::UNHANDLED_ERROR_MESSAGE_FORMAT, AnzuApp::getContextId()),
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
        self::assertStringNotContainsString(self::UNHANDLED_EXCEPTION_MESSAGE, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertCount(1, $this->insertedDocuments);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0][McpLogger::FIELD_LEVEL_NAME]);
        self::assertSame(
            sprintf(McpToolReferenceHandler::UNHANDLED_ERROR_FORMAT, RuntimeException::class),
            $this->insertedDocuments[0][McpLogger::FIELD_ERROR],
        );
    }

    public function testMissingClientCapabilityIsRethrownForTheSdk(): void
    {
        $exception = new MissingRequiredClientCapabilityException(new ClientCapabilities());
        $handler = $this->createHandlerWithoutInputTool(static fn (): McpLogsByContextResponse => throw $exception);

        try {
            $this->callToolWithoutInput($handler);
            self::fail('Expected ' . MissingRequiredClientCapabilityException::class);
        } catch (MissingRequiredClientCapabilityException $thrown) {
            self::assertSame($exception, $thrown);
        }
    }

    /**
     * @param array<string, string> $arguments
     */
    private function callSearchTool(McpToolReferenceHandler $handler, array $arguments): CallToolResult
    {
        $result = $handler->handle(
            new ToolReference($this->createSdkTool(ExampleSearchTool::NAME), [ExampleSearchTool::class, McpToolSignatureResolver::INVOKE_METHOD]),
            $this->createArguments(ExampleSearchTool::NAME, $arguments),
        );
        self::assertInstanceOf(CallToolResult::class, $result);

        return $result;
    }
}
