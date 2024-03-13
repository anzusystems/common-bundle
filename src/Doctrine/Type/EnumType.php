<?php

namespace AnzuSystems\CommonBundle\Doctrine\Type;

use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class EnumType extends Type
{
    public const TYPE = 'enumType';

    /**
     * @throws ConversionException
     * @throws Exception
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        /** @var class-string<EnumInterface>|null $enumType */
        $enumType = $column['enumType'] ?? null;
        if (null === $enumType || false === array_key_exists('enumType', $column)) {
            throw new Exception('Missing "enumType" option for enum type column.');
        }

        return sprintf('ENUM(\'%s\')', implode('\',\'', $enumType::values()));
    }

    public function getName(): string
    {
        return self::TYPE;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
