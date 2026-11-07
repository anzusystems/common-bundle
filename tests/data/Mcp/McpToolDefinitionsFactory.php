<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Mcp;

use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;

final class McpToolDefinitionsFactory
{
    /**
     * @param array<string, string> $toolPermissions
     * @param list<string> $readOnlyToolNames
     * @param array<string, string> $toolCategories
     * @param array<string, string> $writeToolNamesByPreview
     * @param array<string, string> $toolRateLimiters
     * @param array<string, McpToolSignature> $toolSignatures
     * @param list<string> $explicitGrantToolNames
     */
    public static function create(
        array $toolPermissions = [],
        array $readOnlyToolNames = [],
        array $toolCategories = [],
        array $writeToolNamesByPreview = [],
        array $toolRateLimiters = [],
        array $toolSignatures = [],
        array $explicitGrantToolNames = [],
    ): McpToolDefinitions {
        $previewToolNames = array_flip($writeToolNamesByPreview);
        $toolNames = array_unique([
            ...array_keys($toolPermissions),
            ...$readOnlyToolNames,
            ...array_keys($toolCategories),
            ...array_keys($writeToolNamesByPreview),
            ...array_keys($toolRateLimiters),
            ...array_keys($toolSignatures),
        ]);
        $definitions = [];
        foreach ($toolNames as $toolName) {
            $definitions[$toolName] = new McpToolDefinition(
                permission: $toolPermissions[$toolName] ?? null,
                readOnly: in_array(needle: $toolName, haystack: $readOnlyToolNames, strict: true),
                category: $toolCategories[$toolName] ?? null,
                previewOf: $writeToolNamesByPreview[$toolName] ?? null,
                previewTool: $previewToolNames[$toolName] ?? null,
                rateLimiter: $toolRateLimiters[$toolName] ?? null,
                signature: $toolSignatures[$toolName] ?? null,
                explicitGrant: in_array(needle: $toolName, haystack: $explicitGrantToolNames, strict: true),
            );
        }

        return new McpToolDefinitions($definitions);
    }
}
