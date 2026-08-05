<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Repository;

use AnzuSystems\CommonBundle\ApiFilter\ApiQueryMongo;
use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\CommonBundle\Serializer\Service\BsonConverter;
use AnzuSystems\SerializerBundle\Serializer;
use DateTimeImmutable;
use MongoDB\BSON\Regex;
use MongoDB\Collection as MongoCollection;

final class AuditLogRepository extends AbstractLogRepository
{
    private const int ERROR_HTTP_STATUS_MIN = 400;
    private const string FIELD_CONTEXT_USER_ID = 'context.userId';
    private const string FIELD_CONTEXT_PATH = 'context.path';
    private const string FIELD_CONTEXT_RESOURCE_NAME = 'context.resourceName';
    private const string FIELD_CONTEXT_HTTP_STATUS = 'context.httpStatus';
    private const string FIELD_CONTEXT_ERROR = 'context.error';
    private const string FIELD_CONTEXT_EXCEPTION = 'context.exception';
    private const string EMPTY_STRING = '';

    public function __construct(
        MongoCollection $auditLogCollection,
        Serializer $serializer,
        protected BsonConverter $bsonConverter,
        protected int $queryMaxTimeMs = ApiQueryMongo::DEFAULT_QUERY_MAX_TIME_MS,
    ) {
        parent::__construct($auditLogCollection, $serializer, $this->bsonConverter, $queryMaxTimeMs);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findLatest(
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        ?int $userId,
        ?string $pathContains,
        ?string $resourceName,
        ?string $contextId,
        bool $onlyErrors,
        int $limit,
    ): array {
        $match = $this->createDatetimeWindowMatch($from, $until);
        if (is_int($userId)) {
            $match[self::FIELD_CONTEXT_USER_ID] = $userId;
        }
        if (is_string($pathContains) && StringHelper::isNotEmpty($pathContains)) {
            $match[self::FIELD_CONTEXT_PATH] = new Regex(preg_quote($pathContains), self::REGEX_FLAG_CASE_INSENSITIVE);
        }
        if (is_string($resourceName) && StringHelper::isNotEmpty($resourceName)) {
            $match[self::FIELD_CONTEXT_RESOURCE_NAME] = $resourceName;
        }
        if (is_string($contextId) && StringHelper::isNotEmpty($contextId)) {
            $match[self::FIELD_CONTEXT_CONTEXT_ID] = $contextId;
        }
        if ($onlyErrors) {
            $match[self::MONGO_OR] = [
                [self::FIELD_CONTEXT_HTTP_STATUS => [self::MONGO_GTE => self::ERROR_HTTP_STATUS_MIN]],
                [self::FIELD_CONTEXT_ERROR => [self::MONGO_EXISTS => true, self::MONGO_NE => self::EMPTY_STRING]],
                [self::FIELD_CONTEXT_EXCEPTION => [self::MONGO_EXISTS => true, self::MONGO_NE => self::EMPTY_STRING]],
            ];
        }

        return $this->findLatestRawDocuments($match, $limit);
    }
}
