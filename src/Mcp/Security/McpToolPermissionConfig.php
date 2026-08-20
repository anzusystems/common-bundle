<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Security\PermissionConfig;
use Symfony\Component\String\UnicodeString;

final class McpToolPermissionConfig
{
    private const string SUBJECT_TITLE = 'MCP tools';
    private const string LOCALE_EN = 'en';
    private const string TOOL_NAME_WORD_SEPARATOR = '_';
    private const string LABEL_WORD_SEPARATOR = ' ';

    /**
     * @param list<string> $toolNames
     *
     * @return array<string, array<string, mixed>>
     */
    public static function forTools(array $toolNames): array
    {
        $actions = [];
        $actionTranslations = [];
        foreach ($toolNames as $toolName) {
            $action = McpToolPermission::toAction($toolName);
            $actions[$action] = [];
            $actionTranslations[$action] = [self::LOCALE_EN => self::toLabel($toolName)];
        }

        return [
            PermissionConfig::PRM_CONFIG => [
                McpToolPermission::SUBJECT => $actions,
            ],
            PermissionConfig::PRM_TRANSLATION => [
                'subjects' => [
                    McpToolPermission::SUBJECT => [self::LOCALE_EN => self::SUBJECT_TITLE],
                ],
                'actions' => $actionTranslations,
            ],
        ];
    }

    private static function toLabel(string $toolName): string
    {
        return new UnicodeString($toolName)
            ->replace(self::TOOL_NAME_WORD_SEPARATOR, self::LABEL_WORD_SEPARATOR)
            ->title()
            ->toString();
    }
}
