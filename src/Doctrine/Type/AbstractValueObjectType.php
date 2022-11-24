<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Type;

use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

abstract class AbstractValueObjectType extends Type
{
    public function getName(): string
    {
        return substr((string) strrchr(static::class, '\\'), 1);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getVarcharTypeDeclarationSQL($column);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value instanceof ValueObjectInterface) {
            return $value->toString();
        }

        return $value;
    }
}
