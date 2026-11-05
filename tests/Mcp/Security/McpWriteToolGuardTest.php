<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Security;

use AnzuSystems\CommonBundle\Log\Helper\AuditLogResourceHelper;
use AnzuSystems\CommonBundle\Mcp\Exception\McpToolCallRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScope;
use AnzuSystems\CommonBundle\Mcp\Security\McpWriteToolGuard;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Security\StaticMcpToolScopeProvider;
use AnzuSystems\Contracts\AnzuApp;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class McpWriteToolGuardTest extends TestCase
{
    private const string READ_TOOL_NAME = 'list_examples';
    private const string WRITE_TOOL_NAME = 'create_example';
    private const string TOOL_PERMISSION = 'cms_example_read';
    private const int FIRST_USER_ID = 7;
    private const int SECOND_USER_ID = 8;

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

        $guard->includeInAuditLogs();
        $guard->markWritten();
        $guard->excludeFailedWriteFromAuditLogs();
        $guard->addAuditLogResource(new User()->setId(self::FIRST_USER_ID));
        $guard->assertWritable(self::WRITE_TOOL_NAME);

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider failedWriteProvider
     */
    public function testFailedWriteIsExcludedFromAuditLogsOnlyWithoutWrittenResource(bool $withResource, bool $expectedExcluded): void
    {
        $request = new Request();
        AuditLogResourceHelper::excludeFromAuditLogs($request);
        $guard = $this->createGuard(request: $request);

        $guard->includeInAuditLogs();
        self::assertFalse($request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
        if ($withResource) {
            $guard->addAuditLogResource(new User()->setId(self::FIRST_USER_ID));
        }
        $guard->excludeFailedWriteFromAuditLogs();

        self::assertSame($expectedExcluded, $request->attributes->has(AuditLogResourceHelper::RESOURCE_EXCLUDE_ATTR_NAME));
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function failedWriteProvider(): array
    {
        return [
            'without written resource' => [false, true],
            'with written resource' => [true, false],
        ];
    }

    public function testAuditLogResourcesAreAppended(): void
    {
        $request = new Request();
        $guard = $this->createGuard(request: $request);

        $guard->addAuditLogResource(new User()->setId(self::FIRST_USER_ID));
        $guard->addAuditLogResource(new User()->setId(self::SECOND_USER_ID));

        self::assertSame(
            [self::FIRST_USER_ID, self::SECOND_USER_ID],
            $request->attributes->get(AuditLogResourceHelper::RESOURCE_ID_ATTR_NAME),
        );
    }

    private function createGuard(bool $writeGranted = true, ?Request $request = null): McpWriteToolGuard
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturn(true);
        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        return new McpWriteToolGuard(
            toolAccessChecker: new McpToolAccessChecker(
                toolPermissions: [
                    self::READ_TOOL_NAME => self::TOOL_PERMISSION,
                    self::WRITE_TOOL_NAME => self::TOOL_PERMISSION,
                ],
                security: $security,
                readOnlyToolNames: [self::READ_TOOL_NAME],
                toolScopeProvider: new StaticMcpToolScopeProvider(
                    new McpToolScope(writeToolNames: $writeGranted ? [self::WRITE_TOOL_NAME] : []),
                ),
            ),
            requestStack: $requestStack,
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
