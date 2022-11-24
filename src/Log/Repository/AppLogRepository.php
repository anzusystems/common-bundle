<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Repository;

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
    ) {
        parent::__construct($appLogCollection, $serializer, $this->bsonConverter);
    }

    protected function getDocumentClass(): string
    {
        return Log::class;
    }
}
