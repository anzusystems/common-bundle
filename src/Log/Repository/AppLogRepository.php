<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Repository;

use AnzuSystems\CommonBundle\ApiFilter\ApiQueryMongo;
use AnzuSystems\CommonBundle\Document\Log;
use AnzuSystems\CommonBundle\Repository\Mongo\AbstractAnzuMongoRepository;
use AnzuSystems\CommonBundle\Serializer\Service\BsonConverter;
use AnzuSystems\SerializerBundle\Serializer;
use MongoDB\Collection as MongoCollection;

/**
 * @extends AbstractAnzuMongoRepository<Log>
 */
final class AppLogRepository extends AbstractAnzuMongoRepository
{
    public function __construct(
        MongoCollection $appLogCollection,
        Serializer $serializer,
        protected BsonConverter $bsonConverter,
        protected int $queryMaxTimeMs = ApiQueryMongo::DEFAULT_QUERY_MAX_TIME_MS,
    ) {
        parent::__construct($appLogCollection, $serializer, $this->bsonConverter, $queryMaxTimeMs);
    }

    protected function getDocumentClass(): string
    {
        return Log::class;
    }
}
