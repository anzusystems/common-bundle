<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Handler\Handlers;

use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Handler\Handlers\UuidHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Uid\Uuid;

/**
 * The bundle ObjectHandler deserializes the items of a typed array as objects only, so it cannot build a list of UUIDs.
 */
final class UuidArrayHandler extends AbstractHandler
{
    private const string FORMAT_UUID = 'uuid';

    public function __construct(
        private readonly UuidHandler $uuidHandler,
    ) {
    }

    /**
     * @return list<string>|null
     */
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): ?array
    {
        if (null === $value) {
            return null;
        }
        if (false === is_array($value)) {
            throw new SerializerException('Value must be array.');
        }

        return array_map(static fn (Uuid $uuid): string => $uuid->toRfc4122(), $value) |> array_values(...);
    }

    /**
     * @return list<Uuid>
     */
    public function deserialize(mixed $value, Metadata $metadata): array
    {
        if (null === $value) {
            return [];
        }
        if (false === is_array($value)) {
            throw $this->invalidValue($value, $metadata);
        }

        $uuids = [];
        foreach ($value as $item) {
            $uuid = $this->uuidHandler->deserialize($item, $metadata);
            if (false === $uuid instanceof Uuid) {
                throw $this->invalidValue($item, $metadata);
            }

            $uuids[] = $uuid;
        }

        return $uuids;
    }

    public static function supportsDescribe(string $property, Metadata $metadata): bool
    {
        return is_a(object_or_class: $metadata->customHandler, class: self::class, allow_string: true);
    }

    public function describe(string $property, Metadata $metadata): array
    {
        $describe = parent::describe($property, $metadata);
        $describe['type'] = TypeIdentifier::ARRAY->value;
        $describe['items'] = [
            'type' => TypeIdentifier::STRING->value,
            'format' => self::FORMAT_UUID,
        ];

        return $describe;
    }
}
