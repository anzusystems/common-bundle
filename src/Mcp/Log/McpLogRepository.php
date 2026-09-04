<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Log;

use AnzuSystems\CommonBundle\ApiFilter\ApiQueryMongo;
use AnzuSystems\CommonBundle\Helper\MongoHelper;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

final readonly class McpLogRepository
{
    private const string FIELD_DATETIME = 'datetime';
    private const string FIELD_CONTEXT_ID = 'contextId';
    private const string MONGO_GTE = '$gte';
    private const int LIMIT_MIN = 1;
    private const string ID_LOWER_BOUND_SLACK = '-1 minute';
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
            MongoHelper::FIELD_ID => [
                self::MONGO_GTE => MongoHelper::minObjectIdFor($from->modify(self::ID_LOWER_BOUND_SLACK)),
            ],
        ], [
            'sort' => [
                MongoHelper::FIELD_ID => MongoHelper::SORT_DESC,
            ],
            'limit' => max(self::LIMIT_MIN, $limit),
            'maxTimeMS' => $this->queryMaxTimeMs,
            'typeMap' => self::RAW_ARRAY_TYPE_MAP,
        ]);

        return MongoHelper::sortNewestFirst(array_values($documents->toArray()), self::FIELD_DATETIME);
    }
}
