<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\Contracts\AnzuApp;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

final readonly class McpLogger
{
    public const string COLLECTION_NAME = 'mcpLogs';
    public const string FIELD_DATETIME = 'datetime';
    public const string FIELD_LEVEL_NAME = 'levelName';
    public const string FIELD_TOOL = 'tool';
    public const string FIELD_PARAMS = 'params';
    public const string FIELD_USER_ID = 'userId';
    public const string FIELD_CLIENT = 'client';
    public const string FIELD_CONTEXT_ID = 'contextId';
    public const string FIELD_DURATION_MS = 'durationMs';
    public const string FIELD_ERROR = 'error';
    public const string FIELD_HINT = 'hint';

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
            self::FIELD_DATETIME => new UTCDateTime(AnzuApp::date()),
            self::FIELD_LEVEL_NAME => $this->resolveLevelName($error),
            self::FIELD_TOOL => $tool,
            self::FIELD_PARAMS => $params,
            self::FIELD_USER_ID => $userId,
            self::FIELD_CLIENT => $client,
            self::FIELD_CONTEXT_ID => AnzuApp::getContextId(),
            self::FIELD_DURATION_MS => $durationMs,
            self::FIELD_ERROR => $error,
            self::FIELD_HINT => self::HINT,
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
