<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
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
use AnzuSystems\CommonBundle\Tests\Data\Mcp\McpToolDefinitionsFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\StaticMcpToolScopeProvider;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleContextTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultWithoutKeyTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleResultWriteTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWritePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWriteTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\UnserializableResponseWriteTool;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

abstract class AbstractMcpToolReferenceHandlerTestCase extends TestCase
{
    protected const int USER_ID = 42;
    protected const int LIMIT = 5;
    protected const int LIMIT_INVALID = 0;
    protected const string TOOL_PERMISSION = 'example_read';
    protected const string BACKEND_ERROR_MESSAGE = 'Backend is temporarily unavailable, retry the call.';
    protected const string INPUT_ERROR_MESSAGE = 'Invalid input.';
    protected const string MUST_NOT_RUN_MESSAGE = 'must not run';
    protected const string HANDLED_EXCEPTION_MESSAGE = 'backend down';
    protected const string UNHANDLED_EXCEPTION_MESSAGE = 'boom';
    protected const string OPTIMISTIC_LOCK_MESSAGE = 'The row changed.';
    protected const string LEVEL = 'ERROR';
    protected const string TITLE_FIELD = 'title';
    protected const string LIMIT_VIOLATION = 'limit: This value should be between 1 and 50.';
    protected const string SECRET = 'test_secret';
    // The HMAC of the write tool, WRITE_ARGUMENTS and no current state under SECRET.
    protected const string PREVIEW_HASH = 'd04da74f4926e319f2beddde58330604183699c996a026576e10c10cc8a1e375';
    protected const string CONFIRM_KEY = McpWriteConfirmation::CONFIRM_KEY_PREFIX . self::PREVIEW_HASH;
    protected const string TITLE = 'Test';
    protected const string CLIENT_NAME = 'claude-code';
    protected const string CLIENT_VERSION = '2.1.317';
    protected const string HASH_FIELD = 'previewHash';
    protected const string ACTION_FIELD = 'action';
    protected const string CONTENT_FIELD = 'content';
    protected const string ACTION_ACCEPT = 'accept';
    protected const string SESSION_ARGUMENT = '_session';
    protected const string REQUEST_ARGUMENT = '_request';
    protected const string LOCALE_SEPARATOR = '|';
    protected const array WRITE_ARGUMENTS = [
        self::TITLE_FIELD => self::TITLE,
        self::HASH_FIELD => self::PREVIEW_HASH,
    ];
    protected const array TOOL_NAMES = [
        ExampleSearchTool::NAME,
        ExampleWithoutInputTool::NAME,
        ExampleContextTool::NAME,
        ExampleErrorResultTool::NAME,
        ExampleErrorResultWithoutKeyTool::NAME,
        ExampleResultWriteTool::NAME,
        ExampleWriteTool::NAME,
        UnserializableResponseWriteTool::NAME,
    ];

    protected array $insertedDocuments = [];
    protected array $logRecords = [];
    protected array $translations = [];
    protected int $writeRuns = 0;
    protected int $previewRuns = 0;
    protected ?object $previewInput = null;

    protected static function createRequestMeta(ClientCapabilities $capabilities, ?Implementation $clientInfo = null): RequestMeta
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
    protected function createAcceptedAnswer(): array
    {
        return [
            self::ACTION_FIELD => self::ACTION_ACCEPT,
            self::CONTENT_FIELD => [McpWriteConfirmation::CONFIRM_FIELD => true],
        ];
    }

    protected function translated(string $key, ?string $locale): string
    {
        return $key . self::LOCALE_SEPARATOR . $locale;
    }

    protected function assertToolError(string $expectedError, CallToolResult|InputRequiredResult $result): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0][McpLogger::FIELD_LEVEL_NAME]);
        self::assertSame($expectedError, $this->insertedDocuments[0][McpLogger::FIELD_ERROR]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function callWriteTool(McpToolReferenceHandler $handler, array $arguments): CallToolResult|InputRequiredResult
    {
        return $handler->handle(
            new ToolReference($this->createSdkTool(ExampleWriteTool::NAME), [ExampleWriteTool::class, McpToolSignatureResolver::INVOKE_METHOD]),
            $arguments,
        );
    }

    /**
     * @param array<string, mixed> $toolArguments
     * @param array<string, mixed>|null $inputResponses
     *
     * @return array<string, mixed>
     */
    protected function createModernArguments(
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

    protected function createRecordingLogger(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info')
            ->willReturnCallback(function (string $message, array $context = []): void {
                $this->logRecords[] = ['message' => $message, 'context' => $context];
            });

        return $logger;
    }

    protected function createTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
                $this->translations[] = ['id' => $id, 'parameters' => $parameters, 'domain' => $domain, 'locale' => $locale];

                return $this->translated($id, $locale);
            });

        return $translator;
    }

    /**
     * @param array<class-string<Throwable>, string> $toolErrorExceptions
     */
    protected function createWriteHandler(
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

                    return new ExampleWritePreviewResponse(title: $request->title, writable: $writable, currentState: $currentState);
                },
            ),
            userLocale: $userLocale,
            fallbackLocale: $fallbackLocale,
            logger: $logger,
        );
    }

    protected static function createResponse(): McpLogsByContextResponse
    {
        return new McpLogsByContextResponse(new McpLogsByContextResult(auditLogs: new ArrayCollection(), appLogs: new ArrayCollection(), mcpToolCalls: new ArrayCollection()));
    }

    protected function initAppReadOnlyMode(bool $readOnlyMode): void
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
    protected function callTool(
        McpToolReferenceHandler $handler,
        string $toolClass,
        string $toolName,
        ?int $limit = null,
    ): CallToolResult {
        $toolArguments = is_int($limit) ? ['level' => self::LEVEL, 'limit' => $limit] : [];
        $result = $handler->handle(
            new ToolReference($this->createSdkTool($toolName), [$toolClass, McpToolSignatureResolver::INVOKE_METHOD]),
            $this->createArguments($toolName, $toolArguments),
        );
        self::assertInstanceOf(CallToolResult::class, $result);

        return $result;
    }

    protected function callToolWithoutInput(McpToolReferenceHandler $handler): CallToolResult
    {
        return $this->callTool(handler: $handler, toolClass: ExampleWithoutInputTool::class, toolName: ExampleWithoutInputTool::NAME);
    }

    protected function createSdkTool(string $toolName): Tool
    {
        return new Tool(
            name: $toolName,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => null],
            description: null,
            annotations: null,
        );
    }

    /**
     * @param array<string, mixed> $toolArguments
     *
     * @return array<string, mixed>
     */
    protected function createArguments(string $toolName, array $toolArguments): array
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
    protected function createHandlerWithoutInputTool(
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
    protected function createHandler(
        object $tool,
        array $toolErrorExceptions = [],
        bool $toolGranted = true,
        bool $readOnlyTool = true,
        bool $writeGranted = true,
        RequestStack $requestStack = new RequestStack(),
        ?string $previewOf = null,
        ?object $previewTool = null,
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
                static fn (): bool => $toolGranted,
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
            $registry->registerTool($this->createSdkTool($previewTool::NAME), [$previewTool::class, McpToolSignatureResolver::INVOKE_METHOD]);
            $services[$previewTool::class] = static fn (): object => $previewTool;
            $toolSignatures[$previewTool::NAME] = new McpToolSignatureResolver()->resolve($previewTool::class);
        }
        $toolDefinitions = McpToolDefinitionsFactory::create(
            toolPermissions: $toolPermissions,
            readOnlyToolNames: $readOnlyToolNames,
            writeToolNamesByPreview: $writeToolNamesByPreview,
            toolSignatures: array_filter($toolSignatures),
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
                writeRateLimiter: new McpWriteRateLimiter(rateLimiters: new ServiceLocator([]), toolDefinitions: $toolDefinitions, currentUserProvider: $currentUserProvider),
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
