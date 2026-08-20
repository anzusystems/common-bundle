<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use Symfony\Component\String\UnicodeString;

final class McpToolPermission
{
    public const string SUBJECT = 'mcp_tool';
    public const string PREFIX = self::SUBJECT . '_';

    public const string SEARCH_APP_LOGS = self::PREFIX . 'searchAppLogs';
    public const string SEARCH_AUDIT_LOGS = self::PREFIX . 'searchAuditLogs';
    public const string GET_LOGS_BY_CONTEXT = self::PREFIX . 'getLogsByContext';

    public static function forTool(string $toolName): string
    {
        return self::PREFIX . self::toAction($toolName);
    }

    public static function toAction(string $toolName): string
    {
        return new UnicodeString($toolName)
            ->camel()
            ->toString();
    }

    public static function isToolPermission(string $permission): bool
    {
        return str_starts_with($permission, self::PREFIX);
    }
}
