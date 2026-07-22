<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\Contracts\AnzuApp;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

final readonly class McpLogger
{
    public const string COLLECTION_NAME = 'mcpLogs';

    public const string LEVEL_NAME_INFO = 'info';
    public const string LEVEL_NAME_ERROR = 'error';
    public const string HINT = 'search contextId across services';

    public function __construct(
        private Collection $mcpLogCollection,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public function log(string $tool, array $params, ?int $userId, int $durationMs, ?string $error): void
    {
        $this->mcpLogCollection->insertOne([
            'datetime' => new UTCDateTime(AnzuApp::date()),
            'levelName' => $this->resolveLevelName($error),
            'tool' => $tool,
            'params' => $params,
            'userId' => $userId,
            'contextId' => AnzuApp::getContextId(),
            'durationMs' => $durationMs,
            'error' => $error,
            'hint' => self::HINT,
        ]);
    }

    private function resolveLevelName(?string $error): string
    {
        if (null === $error) {
            return self::LEVEL_NAME_INFO;
        }

        return self::LEVEL_NAME_ERROR;
    }
}
