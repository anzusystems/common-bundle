<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Repository;

use AnzuSystems\CommonBundle\Document\Log;
use AnzuSystems\CommonBundle\Helper\MongoHelper;
use AnzuSystems\CommonBundle\Repository\Mongo\AbstractAnzuMongoRepository;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;

/**
 * @extends AbstractAnzuMongoRepository<Log>
 */
abstract class AbstractLogRepository extends AbstractAnzuMongoRepository
{
    protected const string FIELD_DATETIME = 'datetime';
    protected const string FIELD_CONTEXT_CONTEXT_ID = 'context.contextId';
    protected const string REGEX_FLAG_CASE_INSENSITIVE = 'i';
    protected const string MONGO_GTE = '$gte';
    protected const string MONGO_LTE = '$lte';
    protected const string MONGO_NE = '$ne';
    protected const string MONGO_OR = '$or';
    protected const string MONGO_EXISTS = '$exists';

    private const int LIMIT_MIN = 1;
    private const string ID_LOWER_BOUND_SLACK = '-1 minute';
    private const string ID_UPPER_BOUND_SLACK = '+1 day';
    private const array RAW_ARRAY_TYPE_MAP = [
        'root' => 'array',
        'document' => 'array',
        'array' => 'array',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function findLatestByContextId(string $contextId, DateTimeImmutable $from, int $limit): array
    {
        return $this->findLatestRawDocuments([
            self::FIELD_CONTEXT_CONTEXT_ID => $contextId,
            self::FIELD_DATETIME => [
                self::MONGO_GTE => new UTCDateTime($from),
            ],
            MongoHelper::FIELD_ID => [
                self::MONGO_GTE => MongoHelper::minObjectIdFor($from->modify(self::ID_LOWER_BOUND_SLACK)),
            ],
        ], $limit);
    }

    protected function getDocumentClass(): string
    {
        return Log::class;
    }

    /**
     * @param array<string, mixed> $match
     *
     * @return list<array<string, mixed>>
     */
    protected function findLatestRawDocuments(array $match, int $limit): array
    {
        $documents = $this->collection->find($match, [
            'sort' => [
                MongoHelper::FIELD_ID => MongoHelper::SORT_DESC,
            ],
            'limit' => max(self::LIMIT_MIN, $limit),
            'maxTimeMS' => $this->queryMaxTimeMs,
            'typeMap' => self::RAW_ARRAY_TYPE_MAP,
        ]);

        return MongoHelper::sortNewestFirst(array_values($documents->toArray()), self::FIELD_DATETIME);
    }

    /**
     * @return array<string, mixed>
     */
    protected function createDatetimeWindowMatch(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return [
            self::FIELD_DATETIME => [
                self::MONGO_GTE => new UTCDateTime($from),
                self::MONGO_LTE => new UTCDateTime($until),
            ],
            MongoHelper::FIELD_ID => [
                self::MONGO_GTE => MongoHelper::minObjectIdFor($from->modify(self::ID_LOWER_BOUND_SLACK)),
                self::MONGO_LTE => MongoHelper::maxObjectIdFor($until->modify(self::ID_UPPER_BOUND_SLACK)),
            ],
        ];
    }
}
