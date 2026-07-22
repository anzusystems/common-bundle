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

final class JournalLogRepository extends AbstractLogRepository
{
    public const string FIELD_LEVEL_NAME = 'level_name';

    private const string FIELD_MESSAGE = 'message';

    public function __construct(
        MongoCollection $journalLogCollection,
        Serializer $serializer,
        protected BsonConverter $bsonConverter,
        protected int $queryMaxTimeMs = ApiQueryMongo::DEFAULT_QUERY_MAX_TIME_MS,
    ) {
        parent::__construct($journalLogCollection, $serializer, $this->bsonConverter, $queryMaxTimeMs);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findLatest(
        DateTimeImmutable $from,
        DateTimeImmutable $until,
        ?string $levelName,
        ?string $messageContains,
        ?string $contextId,
        int $limit,
    ): array {
        $match = $this->createDatetimeWindowMatch($from, $until);
        if (is_string($levelName) && StringHelper::isNotEmpty($levelName)) {
            $match[self::FIELD_LEVEL_NAME] = $levelName;
        }
        if (is_string($messageContains) && StringHelper::isNotEmpty($messageContains)) {
            $match[self::FIELD_MESSAGE] = new Regex(preg_quote($messageContains), self::REGEX_FLAG_CASE_INSENSITIVE);
        }
        if (is_string($contextId) && StringHelper::isNotEmpty($contextId)) {
            $match[self::FIELD_CONTEXT_CONTEXT_ID] = $contextId;
        }

        return $this->findLatestRawDocuments($match, $limit);
    }
}
