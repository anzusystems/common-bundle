<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\Security\McpToolPermission;
use AnzuSystems\CommonBundle\Mcp\Security\Voter\McpToolPermissionVoter;
use AnzuSystems\CommonBundle\Mcp\Tool\GetLogsByContextTool;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAppLogsTool;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAuditLogsTool;
use AnzuSystems\Contracts\Entity\AnzuUser;
use AnzuSystems\Contracts\Security\Grant;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class McpToolPermissionVoterTest extends TestCase
{
    private const string TOOL_NAME = 'search_app_logs';

    public function testForToolBuildsCamelCasePermission(): void
    {
        self::assertSame(McpToolPermission::SEARCH_APP_LOGS, McpToolPermission::forTool(SearchAppLogsTool::NAME));
        self::assertSame(McpToolPermission::SEARCH_AUDIT_LOGS, McpToolPermission::forTool(SearchAuditLogsTool::NAME));
        self::assertSame(McpToolPermission::GET_LOGS_BY_CONTEXT, McpToolPermission::forTool(GetLogsByContextTool::NAME));
        self::assertSame('mcp_tool_getArticles', McpToolPermission::forTool('get_articles'));
        self::assertTrue(McpToolPermission::isToolPermission('mcp_tool_getArticles'));
        self::assertFalse(McpToolPermission::isToolPermission('cms_article_read'));
    }

    /**
     * @param array<string, int> $resolvedPermissions
     *
     * @dataProvider voteProvider
     */
    public function testVote(array $resolvedPermissions, string $attribute, int $expectedVote): void
    {
        $user = $this->createConfiguredMock(AnzuUser::class, ['getResolvedPermissions' => $resolvedPermissions]);
        $token = $this->createConfiguredMock(TokenInterface::class, ['getUser' => $user]);
        $voter = new McpToolPermissionVoter();
        $voter->setSecurity($this->createMock(Security::class));

        self::assertSame($expectedVote, $voter->vote($token, null, [$attribute]));
    }

    /**
     * @return iterable<string, array{0: array<string, int>, 1: string, 2: int}>
     */
    public static function voteProvider(): iterable
    {
        $permission = McpToolPermission::forTool(self::TOOL_NAME);

        yield 'allow grant' => [[$permission => Grant::ALLOW], $permission, VoterInterface::ACCESS_GRANTED];
        yield 'missing permission denies by default' => [[], $permission, VoterInterface::ACCESS_DENIED];
        yield 'foreign permission abstains' => [[], 'cms_article_read', VoterInterface::ACCESS_ABSTAIN];
    }
}
