<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class McpToolAccessChecker
{
    public const string WRITE_PERMISSION = 'mcp_tool_write';

    /**
     * @param array<string, string> $toolPermissions
     * @param list<string> $readOnlyToolNames
     * @param array<string, string> $toolCategories tool name => category
     * @param array<string, string> $writeToolNamesByPreview preview tool name => the write tool it previews
     */
    public function __construct(
        private array $toolPermissions,
        private Security $security,
        private array $readOnlyToolNames,
        private ?McpToolScopeProviderInterface $toolScopeProvider = null,
        private array $toolCategories = [],
        private array $writeToolNamesByPreview = [],
    ) {
    }

    public function isToolGranted(string $toolName): bool
    {
        return $this->isToolPermissionGranted($toolName) && $this->isWriteGranted($toolName);
    }

    public function isToolPermissionGranted(string $toolName): bool
    {
        // A preview is granted exactly with its write tool, write grant included, so a token never sees it alone.
        $writeToolName = $this->writeToolNamesByPreview[$toolName] ?? null;
        if (is_string($writeToolName)) {
            return $this->isToolGranted($writeToolName);
        }

        return $this->isInToolScope($toolName) && $this->isPermissionGranted($toolName);
    }

    public function isWriteGranted(string $toolName): bool
    {
        if (false === $this->isWriteTool($toolName)) {
            return true;
        }

        return $this->security->isGranted(self::WRITE_PERMISSION, $toolName);
    }

    /**
     * @return list<string>
     */
    public function getToolNames(): array
    {
        return array_keys($this->toolPermissions);
    }

    /**
     * @return list<string>
     */
    public function getWriteToolNames(): array
    {
        return array_values(array_filter(
            array_keys($this->toolPermissions),
            $this->isWriteTool(...),
        ));
    }

    /**
     * @return list<string>
     */
    public function getPermittedToolNames(): array
    {
        return array_values(array_filter(
            array_keys($this->toolPermissions),
            $this->isPermissionGranted(...),
        ));
    }

    /**
     * @return list<string>
     */
    public function getPermittedWriteToolNames(): array
    {
        return array_values(array_filter(
            $this->getWriteToolNames(),
            $this->isPermissionGranted(...),
        ));
    }

    public function getToolCategory(string $toolName): string
    {
        return $this->toolCategories[$toolName]
            ?? throw new LogicException(sprintf('The MCP tool "%s" has no category.', $toolName));
    }

    public function getPreviewToolName(string $writeToolName): ?string
    {
        $previewToolName = array_search(needle: $writeToolName, haystack: $this->writeToolNamesByPreview, strict: true);

        return is_string($previewToolName) ? $previewToolName : null;
    }

    public function isWriteTool(string $toolName): bool
    {
        return false === in_array(needle: $toolName, haystack: $this->readOnlyToolNames, strict: true);
    }

    private function isInToolScope(string $toolName): bool
    {
        $allowedToolNames = $this->toolScopeProvider?->provideAllowedToolNames();
        if (null === $allowedToolNames) {
            return true;
        }

        return in_array(needle: $toolName, haystack: $allowedToolNames, strict: true);
    }

    private function isPermissionGranted(string $toolName): bool
    {
        $permission = $this->toolPermissions[$toolName] ?? null;
        if (null === $permission) {
            return false;
        }

        return $this->security->isGranted($permission);
    }
}
