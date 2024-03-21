<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Doctrine\Type;

use AnzuSystems\CommonBundle\Model\ValueObject\Geolocation;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;

final class GeolocationType extends AbstractValueObjectType
{
    /**
     * At this time, we use the default SRID 0. For more accurate results, the SRID ID 4326 defined in the WGS84 standard
     * used for GPS can be used. When using SRID 4326, the long/lat order in the POINT definition has changed.
     */
    private const SRID_ID = 0;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'POINT SRID ' . self::SRID_ID;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Geolocation
    {
        if (null === $value) {
            return null;
        }

        $geolocation = sscanf($value, 'POINT(%f %f)');

        if ($geolocation && is_numeric($geolocation[0]) && is_numeric($geolocation[1])) {
            return new Geolocation((float) $geolocation[1], (float) $geolocation[0]);
        }

        throw ValueNotConvertible::new($value, $this->getName());
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        if ($value instanceof Geolocation) {
            $value = sprintf('POINT(%F %F)', $value->getLongitude(), $value->getLatitude());
        }

        return $value;
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_AsText(%s)', $sqlExpr);
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_PointFromText(%s, %d)', $sqlExpr, self::SRID_ID);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::STRING;
    }
}
