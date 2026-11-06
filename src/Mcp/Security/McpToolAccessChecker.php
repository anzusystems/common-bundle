<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolDefinition;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

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
        return $this->isToolPermissionGranted($toolName) && $this->isWriteGranted($toolName);
    }

    public function isToolPermissionGranted(string $toolName): bool
    {
        // A preview is granted exactly with its write tool, so a token never sees it alone.
        $writeToolName = $this->toolDefinitions->find($toolName)?->previewOf;
        if (is_string($writeToolName)) {
            return $this->isToolGranted($writeToolName);
        }

        return $this->isInReadScope($toolName) && $this->isPermissionGranted($toolName);
    }

    public function isInputGranted(string $toolName, object $input): bool
    {
        $inputPermission = $this->toolDefinitions->find($toolName)?->inputPermission;

        return null === $inputPermission || $this->security->isGranted($inputPermission, $input);
    }

    public function isWriteGranted(string $toolName): bool
    {
        if (false === $this->isWriteTool($toolName)) {
            return true;
        }

        return in_array(
            needle: $toolName,
            haystack: $this->toolScopeProvider?->provideToolScope()
                ->writeToolNames ?? [],
            strict: true,
        );
    }

    /**
     * @return list<string>
     */
    public function getToolNames(): array
    {
        return array_filter(
            $this->toolDefinitions->all(),
            static fn (McpToolDefinition $definition): bool => is_string($definition->permission),
        ) |> array_keys(...);
    }

    /**
     * @return list<string>
     */
    public function getWriteToolNames(): array
    {
        return array_filter($this->getToolNames(), $this->isWriteTool(...)) |> array_values(...);
    }

    /**
     * @return list<string>
     */
    public function getPermittedToolNames(): array
    {
        return array_filter($this->getToolNames(), $this->isPermissionGranted(...)) |> array_values(...);
    }

    /**
     * @return list<string>
     */
    public function getPermittedWriteToolNames(): array
    {
        return array_filter($this->getWriteToolNames(), $this->isPermissionGranted(...)) |> array_values(...);
    }

    public function getToolCategory(string $toolName): string
    {
        return $this->toolDefinitions->find($toolName)
            ->category
                        ?? throw new LogicException(sprintf('The MCP tool "%s" has no category.', $toolName));
    }

    public function getPreviewToolName(string $writeToolName): ?string
    {
        return $this->toolDefinitions->find($writeToolName)?->previewTool;
    }

    public function isWriteTool(string $toolName): bool
    {
        return true !== $this->toolDefinitions->find($toolName)?->readOnly;
    }

    private function isInReadScope(string $toolName): bool
    {
        if ($this->isWriteTool($toolName)) {
            return true;
        }
        $readToolNames = $this->toolScopeProvider?->provideToolScope()?->readToolNames;
        if (null === $readToolNames) {
            return true;
        }

        return in_array(needle: $toolName, haystack: $readToolNames, strict: true);
    }

    private function isPermissionGranted(string $toolName): bool
    {
        $permission = $this->toolDefinitions->find($toolName)?->permission;
        if (null === $permission) {
            return false;
        }

        return $this->security->isGranted($permission);
    }
}
