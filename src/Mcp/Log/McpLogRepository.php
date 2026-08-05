<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\CommonBundle\ApiFilter\ApiQueryMongo;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

final readonly class McpLogRepository
{
    private const string FIELD_DATETIME = 'datetime';
    private const string FIELD_CONTEXT_ID = 'contextId';
    private const string MONGO_GTE = '$gte';
    private const int LIMIT_MIN = 1;
    private const int SORT_DESC = -1;
    private const array RAW_ARRAY_TYPE_MAP = [
        'root' => 'array',
        'document' => 'array',
        'array' => 'array',
    ];

    public function __construct(
        private Collection $mcpLogCollection,
        private int $queryMaxTimeMs = ApiQueryMongo::DEFAULT_QUERY_MAX_TIME_MS,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findLatestByContextId(string $contextId, DateTimeImmutable $from, int $limit): array
    {
        $documents = $this->mcpLogCollection->find([
            self::FIELD_CONTEXT_ID => $contextId,
            self::FIELD_DATETIME => [
                self::MONGO_GTE => new UTCDateTime($from),
            ],
        ], [
            'sort' => [
                self::FIELD_DATETIME => self::SORT_DESC,
            ],
            'limit' => max(self::LIMIT_MIN, $limit),
            'maxTimeMS' => $this->queryMaxTimeMs,
            'typeMap' => self::RAW_ARRAY_TYPE_MAP,
        ]);

        return array_values($documents->toArray());
    }
}
