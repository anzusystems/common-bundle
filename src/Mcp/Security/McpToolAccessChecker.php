<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use Symfony\Bundle\SecurityBundle\Security;

final readonly class McpToolAccessChecker
{
    /**
     * @param array<string, string> $toolPermissions
     */
    public function __construct(
        private array $toolPermissions,
        private Security $security,
    ) {
    }

    public function isToolGranted(string $toolName): bool
    {
        $permission = $this->toolPermissions[$toolName] ?? null;
        if (null === $permission) {
            return false;
        }

        return $this->security->isGranted($permission);
    }
}
