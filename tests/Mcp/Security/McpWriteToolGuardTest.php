<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Security;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\McpWriteRateLimiter;
use AnzuSystems\CommonBundle\Mcp\Security\McpPreviewHashReservation;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteConfirmation;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\McpToolDefinitionsFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Response\ExampleWritePreviewResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\StaticMcpToolScopeProvider;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\AnzuUser;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class McpWriteToolGuardTest extends TestCase
{
    private const string READ_TOOL_NAME = 'list_examples';
    private const string WRITE_TOOL_NAME = 'create_example';
    private const string TOOL_PERMISSION = 'example_read';
    private const string PREVIEW_TOOL_NAME = 'preview_example';
    private const string LIMITER = 'example_write';
    private const string PREVIEW_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string OTHER_PREVIEW_HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string PREVIEW_TITLE = 'Example';
    private const int USER_ID = 5;

    private McpPreviewHashReservation $previewHashReservation;

    /**
     * @dataProvider writeGrantProvider
     */
    public function testWriteGrantIsRequiredOnlyForWriteTool(string $toolName, bool $writeGranted, bool $expectedRejected): void
    {
        $guard = $this->createGuard(writeGranted: $writeGranted);

        $rejected = false;
        try {
            $guard->assertWriteGranted($toolName);
        } catch (McpToolCallRejectedException $exception) {
            $rejected = true;
            self::assertSame(McpWriteRejectedException::createNotGranted($toolName)->getMessage(), $exception->getMessage());
        }

        self::assertSame($expectedRejected, $rejected);
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function writeGrantProvider(): array
    {
        return [
            'granted write tool' => [self::WRITE_TOOL_NAME, true, false],
            'write tool without grant' => [self::WRITE_TOOL_NAME, false, true],
            'read tool without grant' => [self::READ_TOOL_NAME, false, false],
        ];
    }

    public function testReadOnlyModeRejectsWrite(): void
    {
        $guard = $this->createGuard();

        $this->expectException(McpToolCallRejectedException::class);
        $this->expectExceptionMessage(McpWriteRejectedException::createReadOnlyMode()->getMessage());

        $this->initAppReadOnlyMode(true);
        try {
            $guard->assertWritable(self::WRITE_TOOL_NAME);
        } finally {
            $this->initAppReadOnlyMode(false);
        }
    }

    public function testSecondWriteOfTheMainRequestIsRejected(): void
    {
        $guard = $this->createGuard(request: new Request());
        $guard->assertWritable(self::WRITE_TOOL_NAME);
        $guard->markWritten();

        $this->expectException(McpToolCallRejectedException::class);
        $this->expectExceptionMessage(McpWriteRejectedException::createAlreadyWritten(self::WRITE_TOOL_NAME)->getMessage());

        $guard->assertWritable(self::WRITE_TOOL_NAME);
    }

    public function testWithoutMainRequestThereIsNoGuard(): void
    {
        $guard = $this->createGuard();

        $guard->markWritten();
        $guard->assertWritable(self::WRITE_TOOL_NAME);

        $this->addToAssertionCount(1);
    }

    public function testRetryWithAUsedPreviewHashTakesNoToken(): void
    {
        $guard = $this->createGuard(writeLimit: 2);
        $this->guardPreview($guard, self::PREVIEW_HASH);

        try {
            $this->guardPreview($guard, self::PREVIEW_HASH);
            self::fail('The used preview hash was accepted.');
        } catch (McpWriteRejectedException $exception) {
            self::assertSame(
                McpWriteRejectedException::createPreviewHashUsed(self::WRITE_TOOL_NAME, McpPreviewHashReservation::LIFETIME_SECONDS)->getMessage(),
                $exception->getMessage(),
            );
        }

        self::assertNull($this->guardPreview($guard, self::OTHER_PREVIEW_HASH));
    }

    public function testPreviewHashRefusedByTheWriteLimitStaysUsable(): void
    {
        $guard = $this->createGuard(writeLimit: 1);
        $this->guardPreview($guard, self::PREVIEW_HASH);

        try {
            $this->guardPreview($guard, self::OTHER_PREVIEW_HASH);
            self::fail('The write limit was not applied.');
        } catch (McpWriteRejectedException $exception) {
            self::assertStringContainsString(self::WRITE_TOOL_NAME, $exception->getMessage());
        }

        $this->previewHashReservation->reserve(self::WRITE_TOOL_NAME, $this->createInput(self::OTHER_PREVIEW_HASH));
        $this->addToAssertionCount(1);
    }

    private function guardPreview(McpWriteToolGuard $guard, string $previewHash): ?InputRequiredResult
    {
        $preview = new ExampleWritePreviewResponse(self::PREVIEW_TITLE);
        $preview->previewHash = $previewHash;

        return $guard->guardPreview(
            toolName: self::WRITE_TOOL_NAME,
            previewToolName: self::PREVIEW_TOOL_NAME,
            input: $this->createInput($previewHash),
            context: new RequestContext(
                new Session(new InMemorySessionStore()),
                new CallToolRequest(self::WRITE_TOOL_NAME, [])->withId(1),
            ),
            preview: $preview,
        );
    }

    private function createInput(string $previewHash): ExampleWriteRequest
    {
        $input = new ExampleWriteRequest();
        $input->previewHash = $previewHash;

        return $input;
    }

    private function createGuard(bool $writeGranted = true, ?Request $request = null, ?int $writeLimit = null): McpWriteToolGuard
    {
        $currentUserProvider = $this->createStub(CurrentAnzuUserProvider::class);
        $currentUserProvider->method('getCurrentUser')
            ->willReturn($this->createConfiguredMock(AnzuUser::class, ['getId' => self::USER_ID]));
        $this->previewHashReservation = new McpPreviewHashReservation(new ArrayAdapter(), $currentUserProvider);
        $rateLimiters = [];
        $toolRateLimiters = [];
        if (is_int($writeLimit)) {
            $factory = new RateLimiterFactory(
                ['id' => self::LIMITER, 'policy' => 'fixed_window', 'limit' => $writeLimit, 'interval' => '1 hour'],
                new InMemoryStorage(),
            );
            $rateLimiters[self::LIMITER] = static fn (): RateLimiterFactory => $factory;
            $toolRateLimiters[self::WRITE_TOOL_NAME] = self::LIMITER;
        }

        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturn(true);
        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        return new McpWriteToolGuard(
            toolAccessChecker: new McpToolAccessChecker(
                toolDefinitions: McpToolDefinitionsFactory::create(
                    toolPermissions: [
                        self::READ_TOOL_NAME => self::TOOL_PERMISSION,
                        self::WRITE_TOOL_NAME => self::TOOL_PERMISSION,
                    ],
                    readOnlyToolNames: [self::READ_TOOL_NAME],
                ),
                security: $security,
                toolScopeProvider: new StaticMcpToolScopeProvider(
                    new McpToolScope(writeToolNames: $writeGranted ? [self::WRITE_TOOL_NAME] : []),
                ),
            ),
            requestStack: $requestStack,
            writeConfirmation: new McpWriteConfirmation(
                translator: $this->createStub(TranslatorInterface::class),
                currentUserProvider: $currentUserProvider,
            ),
            writeRateLimiter: new McpWriteRateLimiter(
                rateLimiters: new ServiceLocator($rateLimiters),
                toolDefinitions: McpToolDefinitionsFactory::create(toolRateLimiters: $toolRateLimiters),
                currentUserProvider: $currentUserProvider,
            ),
            previewHashReservation: $this->previewHashReservation,
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
}
