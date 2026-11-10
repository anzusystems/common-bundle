<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Security;

use AnzuSystems\CommonBundle\Mcp\McpPromptDefinitions;

final readonly class McpPromptAccessChecker
{
    public function __construct(
        private McpPromptDefinitions $promptDefinitions,
        private McpToolAccessChecker $toolAccessChecker,
    ) {
    }

    public function isPromptGranted(string $promptName): bool
    {
        return array_all(
            $this->promptDefinitions->getToolNames($promptName),
            $this->toolAccessChecker->isToolGranted(...),
        );
    }
}
