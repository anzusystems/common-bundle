<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Repository\Mongo;

use AnzuSystems\CommonBundle\ApiFilter\ApiInfiniteResponseList;
use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\ApiFilter\ApiQueryMongo;
use AnzuSystems\CommonBundle\Document\Log;
use AnzuSystems\CommonBundle\Serializer\Service\BsonConverter;
use AnzuSystems\Contracts\Document\Interfaces\DocumentInterface;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection as MongoCollection;
use MongoDB\Model\BSONDocument;

/**
 * @template T of DocumentInterface
 */
abstract class AbstractAnzuMongoRepository
{
    public function __construct(
        protected MongoCollection $collection,
        protected Serializer $serializer,
        protected BsonConverter $bsonConverter,
    ) {
    }

    /**
     * @return ApiInfiniteResponseList<T>
     *
     * @throws SerializerException
     */
    public function findByApiParams(ApiParams $apiParams): ApiInfiniteResponseList
    {
        $apiQueryMongo = new ApiQueryMongo($apiParams, $this->getDocumentClass(), true);
        $response = new ApiInfiniteResponseList();
        /** @var BSONDocument[] $documents */
        $documents = $this->collection->find(
            $apiQueryMongo->getFilter(),
            $apiQueryMongo->getOptions()
        )->toArray();
        $data = array_map(
            fn (BSONDocument $doc): Log => $this->bsonConverter->bsonToObject($doc, Log::class),
            $documents
        );
        $totalCount = $apiParams->getLimit() + $apiParams->getOffset() + 1;
        if (empty($data)) {
            $totalCount = 0;
        }

        return $response
            ->setHasNextPage(
                count($data) > $apiParams->getLimit()
            )
            ->setTotalCount($totalCount)
            ->setData(
                array_slice($data, 0, $apiParams->getLimit())
            )
        ;
    }

    /**
     * @throws SerializerException
     */
    public function find(string $id): ?DocumentInterface
    {
        $document = $this->collection->findOne(['_id' => new ObjectId($id)]);
        if ($document instanceof BSONDocument) {
            return $this->bsonConverter->bsonToObject($document, Log::class);
        }

        return null;
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getDocumentClass(): string;
}
