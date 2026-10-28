<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class McpToolAccessCheckerTest extends TestCase
{
    private const string GRANTED_TOOL = 'list_sites';
    private const string DENIED_TOOL = 'search_app_logs';
    private const string UNMAPPED_TOOL = 'list_authors';
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

    public function testWriteToolNamesListOnlyMappedToolsWithoutReadOnlyHint(): void
    {
        $checker = $this->createChecker([self::GRANTED_TOOL], []);

        self::assertSame([self::DENIED_TOOL], $checker->getWriteToolNames());
    }

    public function testPermittedWriteToolNamesListOnlyWriteToolsWithGrantedToolPermission(): void
    {
        $checker = $this->createChecker([], []);

        self::assertSame([self::GRANTED_TOOL], $checker->getPermittedWriteToolNames());
    }

    /**
     * @param list<string> $readOnlyToolNames
     * @param list<string> $grantedWriteTools
     */
    private function createChecker(array $readOnlyToolNames, array $grantedWriteTools): McpToolAccessChecker
    {
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
            ],
            security: $security,
            readOnlyToolNames: $readOnlyToolNames,
        );
    }
}
