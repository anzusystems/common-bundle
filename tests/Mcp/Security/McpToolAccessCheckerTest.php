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
        $checker = $this->createChecker([self::DENIED_TOOL, self::UNMAPPED_TOOL], true);

        self::assertFalse($checker->isToolGranted(self::DENIED_TOOL));
        self::assertFalse($checker->isToolGranted(self::UNMAPPED_TOOL));
    }

    /**
     * @dataProvider writeToolProvider
     *
     * @param list<string> $readOnlyToolNames
     */
    public function testWriteToolRequiresTheWritePermission(
        array $readOnlyToolNames,
        bool $writeGranted,
        bool $expectedWriteTool,
        bool $expectedGranted,
    ): void {
        $checker = $this->createChecker($readOnlyToolNames, $writeGranted);

        self::assertSame($expectedWriteTool, $checker->isWriteTool(self::GRANTED_TOOL));
        self::assertSame($expectedGranted, $checker->isWriteGranted(self::GRANTED_TOOL));
        self::assertSame($expectedGranted, $checker->isToolGranted(self::GRANTED_TOOL));
    }

    /**
     * @return array<string, array{list<string>, bool, bool, bool}>
     */
    public static function writeToolProvider(): array
    {
        return [
            'read tool needs no write permission' => [[self::GRANTED_TOOL], false, false, true],
            'write tool is granted with the write permission' => [[], true, true, true],
            'tool missing from the read-only names is a write tool' => [[], false, true, false],
        ];
    }

    /**
     * @param list<string> $readOnlyToolNames
     */
    private function createChecker(array $readOnlyToolNames, bool $writeGranted): McpToolAccessChecker
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => match ($attribute) {
                self::GRANTED_PERMISSION => true,
                McpToolAccessChecker::WRITE_PERMISSION => $writeGranted,
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
