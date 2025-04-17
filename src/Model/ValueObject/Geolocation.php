<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\ValueObject;

use AnzuSystems\CommonBundle\Helper\MathHelper;
use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use DomainException;

final class Geolocation implements ValueObjectInterface
{
    private float $latitude;
    private float $longitude;

    public function __construct(float $latitude = 0, float $longitude = 0)
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new DomainException('Invalid geolocation coordinates.');
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function __toString(): string
    {
        return (string) $this->getLatitude() . ',' . (string) $this->getLongitude();
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function is(string $value): bool
    {
        return $value === $this->toString();
    }

    /**
     * @param Geolocation $valueObject
     */
    public function equals(ValueObjectInterface $valueObject): bool
    {
        return MathHelper::floatEquals($valueObject->getLatitude(), $this->getLatitude())
            && MathHelper::floatEquals($valueObject->getLongitude(), $this->getLongitude())
        ;
    }

    public function toString(): string
    {
        return (string) $this;
    }

    public function getValue(): int
    {
        throw new DomainException('Use latitude or longitude as value.');
    }

    public function isNot(string $value): bool
    {
        throw new DomainException('Method "isNot" not supported for geolocation.');
    }

    public function in(array $values): bool
    {
        throw new DomainException('Method "in" not supported for geolocation.');
    }
}
