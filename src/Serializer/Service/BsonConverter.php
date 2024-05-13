<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Service;

use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use AnzuSystems\SerializerBundle\Metadata\MetadataRegistry;
use ArrayObject;
use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONDocument;
use Throwable;

final class BsonConverter
{
    public function __construct(
        private readonly MetadataRegistry $metadataRegistry,
    ) {
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T
     *
     * @throws SerializerException
     */
    public function bsonToObject(BSONDocument $data, string $className): object
    {
        $objectMetadata = $this->metadataRegistry->get($className);
        $object = new $className();
        foreach ($objectMetadata->getAll() as $name => $metadata) {
            $persistedName = $metadata->persistedName ?? $name;
            if (null === $metadata->setter || false === $data->offsetExists($persistedName)) {
                continue;
            }
            $value = $this->convertValue($data[$persistedName], $metadata);
            if (null === $value && false === $metadata->isNullable) {
                continue;
            }
            try {
                $object->{$metadata->setter}($value);
            } catch (Throwable) {
                throw new SerializerException('Unable to deserialize "' . $name . '". Check type.');
            }
        }

        return $object;
    }

    /**
     * @throws SerializerException
     */
    private function convertValue(mixed $value, Metadata $metadata): mixed
    {
        if ($value instanceof UTCDateTime) {
            return DateTimeImmutable::createFromMutable($value->toDateTime());
        }
        if ('array' === $metadata->type && $value instanceof ArrayObject) {
            return $value->getArrayCopy();
        }
        if ($value instanceof BSONDocument && class_exists($metadata->type)) {
            return $this->bsonToObject($value, $metadata->type);
        }
        if ($value instanceof ObjectId) {
            return (string) $value;
        }

        return $value;
    }
}
