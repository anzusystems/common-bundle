<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model;

final readonly class McpLogsByContextResult
{
    /**
     * @param list<array<string, mixed>> $auditLogs
     * @param list<array<string, mixed>> $appLogs
     * @param list<array<string, mixed>> $mcpToolCalls
     */
    public function __construct(
        public array $auditLogs,
        public array $appLogs,
        public array $mcpToolCalls,
    ) {
    }
}
