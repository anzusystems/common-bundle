<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\Contracts\AnzuApp;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

final readonly class McpLogger
{
    public const string COLLECTION_NAME = 'mcpLogs';
    public const string DATETIME_FIELD = 'datetime';
    public const string LEVEL_NAME_FIELD = 'levelName';
    public const string TOOL_FIELD = 'tool';
    public const string PARAMS_FIELD = 'params';
    public const string USER_ID_FIELD = 'userId';
    public const string CLIENT_FIELD = 'client';
    public const string CONTEXT_ID_FIELD = 'contextId';
    public const string DURATION_MS_FIELD = 'durationMs';
    public const string ERROR_FIELD = 'error';
    public const string HINT_FIELD = 'hint';

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
    public function log(string $tool, array $params, ?int $userId, int $durationMs, ?string $error, ?string $client): void
    {
        $this->mcpLogCollection->insertOne([
            self::DATETIME_FIELD => new UTCDateTime(AnzuApp::date()),
            self::LEVEL_NAME_FIELD => $this->resolveLevelName($error),
            self::TOOL_FIELD => $tool,
            self::PARAMS_FIELD => $params,
            self::USER_ID_FIELD => $userId,
            self::CLIENT_FIELD => $client,
            self::CONTEXT_ID_FIELD => AnzuApp::getContextId(),
            self::DURATION_MS_FIELD => $durationMs,
            self::ERROR_FIELD => $error,
            self::HINT_FIELD => self::HINT,
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
