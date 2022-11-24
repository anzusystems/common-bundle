<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Handler\Handlers;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class ValueObjectHandler extends AbstractHandler
{
    public static function supportsSerialize(mixed $value): bool
    {
        return $value instanceof ValueObjectInterface;
    }

    /**
     * @param ValueObjectInterface $value
     */
    public function serialize(mixed $value, Metadata $metadata): string
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
}
