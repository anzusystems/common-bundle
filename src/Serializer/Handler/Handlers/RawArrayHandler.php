<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Handler\Handlers;

use AnzuSystems\SerializerBundle\Context\SerializationContext;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Handler\Handlers\AbstractHandler;
use AnzuSystems\SerializerBundle\Metadata\Metadata;

final class RawArrayHandler extends AbstractHandler
{
    /**
     * @return array<array-key, mixed>
     */
    public function serialize(mixed $value, Metadata $metadata, SerializationContext $context): array
    {
        if (is_array($value)) {
            return $value;
        }

        throw new SerializerException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function deserialize(mixed $value, Metadata $metadata): array
    {
        if (is_array($value)) {
            return $value;
        }

        throw new SerializerException('Unsupported value for ' . self::class . '::' . __FUNCTION__);
    }
}
