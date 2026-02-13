<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Handler\Handlers;

use AnzuSystems\CommonBundle\Model\ValueObject\Geolocation;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Helper\SerializerHelper;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use OpenApi\Annotations\Property;
use Symfony\Component\TypeInfo\TypeIdentifier;

final class GeolocationHandler extends AbstractHandler
{
    private const string LAT = 'lat';
    private const string LON = 'lon';

    public static function supportsSerialize(mixed $value): bool
    {
        return $value instanceof Geolocation;
    }

    /**
     * @param Geolocation $value
     */
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): array
    {
        return [
            self::LAT => $value->getLatitude(),
            self::LON => $value->getLongitude(),
        ];
    }

    public static function supportsDeserialize(mixed $value, string $type): bool
    {
        return is_a($type, Geolocation::class, true) && is_array($value);
    }

    /**
     * @param array $value
     *
     * @throws SerializerException
     */
    public function deserialize(mixed $value, Metadata $metadata): Geolocation
    {
        if (
            isset($value[self::LAT]) &&
            is_numeric($value[self::LAT]) &&
            isset($value[self::LON]) &&
            is_numeric($value[self::LON])
        ) {
            return new Geolocation(
                (float) $value[self::LAT],
                (float) $value[self::LON],
            );
        }

        throw new SerializerException('Invalid geolocation format');
    }

    public static function getPriority(): int
    {
        return ValueObjectHandler::getPriority() + 1;
    }

    public static function supportsDescribe(string $property, Metadata $metadata): bool
    {
        return is_a($metadata->type, Geolocation::class, true);
    }

    public function describe(string $property, Metadata $metadata): array
    {
        $description = parent::describe($property, $metadata);
        $description['type'] = TypeIdentifier::OBJECT->value;
        $description['title'] = SerializerHelper::getClassBaseName(Geolocation::class);
        $description['properties'] = [
            new Property([
                'property' => self::LON,
                'title' => 'Longitude',
                'type' => 'number',
                'format' => TypeIdentifier::FLOAT->value,
                'minimum' => -180,
                'maximum' => 180,
            ]),
            new Property([
                'property' => self::LAT,
                'title' => 'Latitude',
                'type' => 'number',
                'format' => TypeIdentifier::FLOAT->value,
                'minimum' => -90,
                'maximum' => 90,
            ]),
        ];

        return $description;
    }
}
