<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp;

final readonly class McpPromptDefinitions
{
    /**
     * @param array<string, list<string>> $toolNamesByPrompt prompt name => the tools it names, built by McpPromptDefinitionsCompilerPass
     */
    public function __construct(
        private array $toolNamesByPrompt = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function getToolNames(string $promptName): array
    {
        return $this->toolNamesByPrompt[$promptName] ?? [];
    }
}
