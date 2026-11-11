<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class McpToolAccessChecker
{
    public function __construct(
        private McpToolDefinitions $toolDefinitions,
        private Security $security,
        private ?McpToolScopeProviderInterface $toolScopeProvider = null,
    ) {
    }

    public function isToolGranted(string $toolName): bool
    {
        return $this->isToolGrantedWithoutWriteScope($toolName) && $this->isWriteGranted($toolName);
    }

    public function isToolGrantedWithoutWriteScope(string $toolName): bool
    {
        $writeToolName = $this->toolDefinitions->find($toolName)?->previewOf;
        if (is_string($writeToolName)) {
            return $this->isToolGranted($writeToolName);
        }

        return $this->isInReadScope($toolName) && $this->isPermissionGranted($toolName);
    }

    public function isWriteGranted(string $toolName): bool
    {
        if (false === $this->isWriteTool($toolName)) {
            return true;
        }

        return in_array(
            needle: $toolName,
            haystack: $this->resolveToolScope()
                ->writeToolNames,
            strict: true,
        );
    }

    /**
     * @return list<string>
     */
    public function getPermittedToolNames(): array
    {
        return array_filter($this->toolDefinitions->getToolNames(), $this->isPermissionGranted(...)) |> array_values(...);
    }

    /**
     * @return list<string>
     */
    public function getPermittedToolNamesForUser(UserInterface $user): array
    {
        return array_filter(
            $this->toolDefinitions->getToolNames(),
            fn (string $toolName): bool => $this->isPermissionGrantedForUser($toolName, $user),
        ) |> array_values(...);
    }

    public function isWriteTool(string $toolName): bool
    {
        return true !== $this->toolDefinitions->find($toolName)?->readOnly;
    }

    private function resolveToolScope(): McpToolScope
    {
        return $this->toolScopeProvider?->provideToolScope() ?? new McpToolScope();
    }

    private function isInReadScope(string $toolName): bool
    {
        if ($this->isWriteTool($toolName)) {
            return true;
        }
        $toolScope = $this->resolveToolScope();
        if (true === $this->toolDefinitions->find($toolName)?->explicitGrant) {
            return in_array(needle: $toolName, haystack: $toolScope->explicitToolNames, strict: true);
        }

        return null === $toolScope->readToolNames
            || in_array(needle: $toolName, haystack: $toolScope->readToolNames, strict: true);
    }

    private function isPermissionGranted(string $toolName): bool
    {
        $permission = $this->toolDefinitions->find($toolName)?->permission;
        if (null === $permission) {
            return false;
        }

        return $this->security->isGranted($permission);
    }

    private function isPermissionGrantedForUser(string $toolName, UserInterface $user): bool
    {
        $permission = $this->toolDefinitions->find($toolName)?->permission;
        if (null === $permission) {
            return false;
        }

        return $this->security->isGrantedForUser($user, $permission);
    }
}
