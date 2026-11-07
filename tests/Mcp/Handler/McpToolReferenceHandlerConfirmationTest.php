<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolAccessRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Log\McpToolCallLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteConfirmation;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWriteTool;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;

final class McpToolReferenceHandlerConfirmationTest extends AbstractMcpToolReferenceHandlerTestCase
{
    private const string OTHER_PREVIEW_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string OTHER_CONFIRM_KEY = McpWriteConfirmation::CONFIRM_KEY_PREFIX . self::OTHER_PREVIEW_HASH;
    private const int CONFIRM_KEY_LENGTH = 72;
    private const string LOCALE_EN = 'en';
    private const string LOCALE_SK = 'sk';
    private const string ACTION_DECLINE = 'decline';
    private const string ACTION_CANCEL = 'cancel';

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
                            'message' => $this->translated(ExampleWritePreviewResponse::MESSAGE_KEY, self::LOCALE_SK),
                            'requestedSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    McpWriteConfirmation::CONFIRM_FIELD => [
                                        'type' => 'boolean',
                                        'title' => $this->translated(McpWriteConfirmation::FIELD_TITLE_KEY, self::LOCALE_SK),
                                    ],
                                ],
                                'required' => [McpWriteConfirmation::CONFIRM_FIELD],
                            ],
                        ],
                    ],
                ],
            ],
            json_decode(json: json_encode($result, JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR),
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
            inputResponses: [self::CONFIRM_KEY => $this->createAcceptedAnswer()],
        ));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame(1, $this->writeRuns);
        self::assertSame(1, $this->previewRuns);
        self::assertSame(McpLogger::LEVEL_NAME_INFO, $this->insertedDocuments[0][McpLogger::FIELD_LEVEL_NAME]);
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
            inputResponses: [self::OTHER_CONFIRM_KEY => $this->createAcceptedAnswer()],
        ));

        self::assertInstanceOf(InputRequiredResult::class, $result);
        self::assertSame([self::CONFIRM_KEY], array_keys($result->inputRequests));
        self::assertSame(1, $this->previewRuns);
        self::assertSame(0, $this->writeRuns);
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
            new ToolReference($this->createSdkTool(ExampleSearchTool::NAME), [ExampleSearchTool::class, McpToolSignatureResolver::INVOKE_METHOD]),
            $this->createModernArguments(toolName: ExampleSearchTool::NAME, toolArguments: $toolArguments),
        );

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
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
}
