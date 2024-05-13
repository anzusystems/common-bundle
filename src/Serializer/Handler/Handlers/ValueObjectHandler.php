<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Handler\Handlers;

use AnzuSystems\Contracts\Model\ValueObject\AbstractValueObject;
use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;
use Symfony\Component\PropertyInfo\Type;

final class ValueObjectHandler extends AbstractHandler
{
    public static function supportsSerialize(mixed $value): bool
    {
        return $value instanceof ValueObjectInterface;
    }

    /**
     * @param ValueObjectInterface $value
     */
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): string
    {
        return $value->toString();
    }

    public static function supportsDeserialize(mixed $value, string $type): bool
    {
        return is_a($type, ValueObjectInterface::class, true);
    }

    public function deserialize(mixed $value, Metadata $metadata): object
    {
        return new $metadata->type($value);
    }

    public static function supportsDescribe(string $property, Metadata $metadata): bool
    {
        return is_a($metadata->type, AbstractValueObject::class, true);
    }

    public function describe(string $property, Metadata $metadata): array
    {
        $description = parent::describe($property, $metadata);

        /** @var AbstractValueObject $valueObjectClass */
        $valueObjectClass = $metadata->type;
        $description['enum'] = $valueObjectClass::AVAILABLE_VALUES;
        $description['type'] = Type::BUILTIN_TYPE_STRING;
        $description['default'] = $valueObjectClass::DEFAULT_VALUE;

        return $description;
    }
}
