<?php

namespace AnzuSystems\CommonBundle\Doctrine\Type;

use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

abstract class AbstractEnumType extends Type
{
    /**
     * @return class-string<EnumInterface>
     */
    abstract public function getEnumClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        /** @var class-string<EnumInterface> $enumClass */
        $enumClass = $this->getEnumClass();

        return sprintf('ENUM(\'%s\')', implode('\',\'', $enumClass::values()));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EnumInterface
    {
        if (null === $value) {
            return null;
        }

        if (false === is_string($value)) {
            throw ValueNotConvertible::new($value, self::class);
        }
        /** @var class-string<EnumInterface> $enumClass */
        $enumClass = $this->getEnumClass();

        return $enumClass::from($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (false === ($value instanceof EnumInterface)) {
            throw ValueNotConvertible::new($value, self::class);
        }

        return $value->toString();
    }
}
