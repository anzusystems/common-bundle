<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolScopeProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class McpToolAccessCheckerTest extends TestCase
{
    private const string GRANTED_TOOL = 'list_sites';
    private const string DENIED_TOOL = 'search_app_logs';
    private const string UNMAPPED_TOOL = 'list_authors';
    private const string SECOND_GRANTED_TOOL = 'list_site_groups';
    private const string GRANTED_PERMISSION = 'cms_site_read';
    private const string DENIED_PERMISSION = 'cms_log_read';

    public function testDeniedAndUnmappedToolsAreDenied(): void
    {
        $checker = $this->createChecker([self::DENIED_TOOL, self::UNMAPPED_TOOL], [self::DENIED_TOOL, self::UNMAPPED_TOOL]);

        self::assertFalse($checker->isToolGranted(self::DENIED_TOOL));
        self::assertFalse($checker->isToolGranted(self::UNMAPPED_TOOL));
    }

    /**
     * @dataProvider writeToolProvider
     *
     * @param list<string> $readOnlyToolNames
     * @param list<string> $grantedWriteTools
     */
    public function testWriteToolRequiresTheTokenToGrantIt(
        array $readOnlyToolNames,
        array $grantedWriteTools,
        bool $expectedWriteTool,
        bool $expectedGranted,
    ): void {
        $checker = $this->createChecker($readOnlyToolNames, $grantedWriteTools);

        self::assertSame($expectedWriteTool, $checker->isWriteTool(self::GRANTED_TOOL));
        self::assertSame($expectedGranted, $checker->isWriteGranted(self::GRANTED_TOOL));
        self::assertSame($expectedGranted, $checker->isToolGranted(self::GRANTED_TOOL));
    }

    /**
     * @return array<string, array{list<string>, list<string>, bool, bool}>
     */
    public static function writeToolProvider(): array
    {
        return [
            'read tool needs no write grant' => [[self::GRANTED_TOOL], [], false, true],
            'write tool granted by the token' => [[], [self::GRANTED_TOOL], true, true],
            'write tool missing from the read-only names is denied when the token grants another tool' => [
                [],
                [self::DENIED_TOOL],
                true,
                false,
            ],
        ];
    }

    public function testToolNamesListEveryMappedToolWithoutPermissionCheck(): void
    {
        $checker = $this->createChecker([self::GRANTED_TOOL], []);

        self::assertSame([self::GRANTED_TOOL, self::DENIED_TOOL, self::SECOND_GRANTED_TOOL], $checker->getToolNames());
    }

    public function testWriteToolNamesListOnlyMappedToolsWithoutReadOnlyHint(): void
    {
        $checker = $this->createChecker([self::GRANTED_TOOL, self::SECOND_GRANTED_TOOL], []);

        self::assertSame([self::DENIED_TOOL], $checker->getWriteToolNames());
    }

    public function testPermittedWriteToolNamesListOnlyWriteToolsWithGrantedToolPermission(): void
    {
        $checker = $this->createChecker([self::SECOND_GRANTED_TOOL], []);

        self::assertSame([self::GRANTED_TOOL], $checker->getPermittedWriteToolNames());
    }

    /**
     * @dataProvider toolScopeProvider
     *
     * @param list<string> $expectedGrantedTools
     */
    public function testToolScopeNarrowsGrantedTools(?McpToolScopeProviderInterface $toolScopeProvider, array $expectedGrantedTools): void
    {
        $checker = $this->createChecker(
            readOnlyToolNames: [self::GRANTED_TOOL, self::SECOND_GRANTED_TOOL, self::DENIED_TOOL],
            grantedWriteTools: [],
            toolScopeProvider: $toolScopeProvider,
        );

        $grantedTools = array_values(array_filter(
            [self::GRANTED_TOOL, self::SECOND_GRANTED_TOOL, self::DENIED_TOOL, self::UNMAPPED_TOOL],
            $checker->isToolGranted(...),
        ));

        self::assertSame($expectedGrantedTools, $grantedTools);
    }

    /**
     * @return array<string, array{McpToolScopeProviderInterface|null, list<string>}>
     */
    public static function toolScopeProvider(): array
    {
        return [
            'no scope provider keeps every permitted tool' => [null, [self::GRANTED_TOOL, self::SECOND_GRANTED_TOOL]],
            'null scope keeps every permitted tool' => [
                self::createToolScopeProvider(null),
                [self::GRANTED_TOOL, self::SECOND_GRANTED_TOOL],
            ],
            'scope hides the other permitted tools' => [
                self::createToolScopeProvider([self::SECOND_GRANTED_TOOL]),
                [self::SECOND_GRANTED_TOOL],
            ],
            'scope does not grant a tool without its permission' => [
                self::createToolScopeProvider([self::DENIED_TOOL, self::UNMAPPED_TOOL]),
                [],
            ],
        ];
    }

    public function testToolScopeDeniesWriteToolGrantedByTheToken(): void
    {
        $checker = $this->createChecker(
            readOnlyToolNames: [self::SECOND_GRANTED_TOOL],
            grantedWriteTools: [self::GRANTED_TOOL],
            toolScopeProvider: self::createToolScopeProvider([self::SECOND_GRANTED_TOOL]),
        );

        self::assertFalse($checker->isToolPermissionGranted(self::GRANTED_TOOL));
        self::assertFalse($checker->isToolGranted(self::GRANTED_TOOL));
    }

    public function testPermittedToolNamesIgnoreToolScope(): void
    {
        $checker = $this->createChecker(
            readOnlyToolNames: [],
            grantedWriteTools: [],
            toolScopeProvider: self::createToolScopeProvider([self::DENIED_TOOL]),
        );

        self::assertSame([self::GRANTED_TOOL, self::SECOND_GRANTED_TOOL], $checker->getPermittedToolNames());
        self::assertSame([self::GRANTED_TOOL, self::SECOND_GRANTED_TOOL], $checker->getPermittedWriteToolNames());
    }

    /**
     * @param list<string>|null $allowedToolNames
     */
    private static function createToolScopeProvider(?array $allowedToolNames): McpToolScopeProviderInterface
    {
        return new readonly class($allowedToolNames) implements McpToolScopeProviderInterface {
            /**
             * @param list<string>|null $allowedToolNames
             */
            public function __construct(
                private ?array $allowedToolNames,
            ) {
            }

            public function provideAllowedToolNames(): ?array
            {
                return $this->allowedToolNames;
            }
        };
    }

    /**
     * @param list<string> $readOnlyToolNames
     * @param list<string> $grantedWriteTools
     */
    private function createChecker(
        array $readOnlyToolNames,
        array $grantedWriteTools,
        ?McpToolScopeProviderInterface $toolScopeProvider = null,
    ): McpToolAccessChecker {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute, mixed $subject = null): bool => match ($attribute) {
                self::GRANTED_PERMISSION => true,
                McpToolAccessChecker::WRITE_PERMISSION => in_array(needle: $subject, haystack: $grantedWriteTools, strict: true),
                default => false,
            });

        return new McpToolAccessChecker(
            toolPermissions: [
                self::GRANTED_TOOL => self::GRANTED_PERMISSION,
                self::DENIED_TOOL => self::DENIED_PERMISSION,
                self::SECOND_GRANTED_TOOL => self::GRANTED_PERMISSION,
            ],
            security: $security,
            readOnlyToolNames: $readOnlyToolNames,
            toolScopeProvider: $toolScopeProvider,
        );
    }
}
