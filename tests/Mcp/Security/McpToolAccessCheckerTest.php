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

    public function testMappedToolFollowsPermissionAndUnmappedToolIsDenied(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute): bool => self::GRANTED_PERMISSION === $attribute);
        $checker = new McpToolAccessChecker(
            [
                self::GRANTED_TOOL => self::GRANTED_PERMISSION,
                self::DENIED_TOOL => self::DENIED_PERMISSION,
            ],
            $security,
        );

        self::assertTrue($checker->isToolGranted(self::GRANTED_TOOL));
        self::assertFalse($checker->isToolGranted(self::DENIED_TOOL));
        self::assertFalse($checker->isToolGranted(self::UNMAPPED_TOOL));
    }
}
