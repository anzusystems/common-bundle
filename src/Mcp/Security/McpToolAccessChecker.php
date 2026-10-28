<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use Symfony\Bundle\SecurityBundle\Security;

final readonly class McpToolAccessChecker
{
    public const string WRITE_PERMISSION = 'mcp_tool_write';

    /**
     * @param array<string, string> $toolPermissions
     * @param list<string> $readOnlyToolNames
     */
    public function __construct(
        private array $toolPermissions,
        private Security $security,
        private array $readOnlyToolNames,
    ) {
    }

    public function isToolGranted(string $toolName): bool
    {
        return $this->isToolPermissionGranted($toolName) && $this->isWriteGranted($toolName);
    }

    public function isToolPermissionGranted(string $toolName): bool
    {
        $permission = $this->toolPermissions[$toolName] ?? null;
        if (null === $permission) {
            return false;
        }

        return $this->security->isGranted($permission);
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
    public function getPermittedWriteToolNames(): array
    {
        return array_values(array_filter(
            $this->getWriteToolNames(),
            $this->isToolPermissionGranted(...),
        ));
    }

    public function isWriteTool(string $toolName): bool
    {
        return false === in_array(needle: $toolName, haystack: $this->readOnlyToolNames, strict: true);
    }
}
