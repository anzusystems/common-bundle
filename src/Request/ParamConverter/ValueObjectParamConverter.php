<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Request\ParamConverter;

use AnzuSystems\Contracts\Model\ValueObject\AbstractValueObject;
use AnzuSystems\Contracts\Model\ValueObject\ValueObjectInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class ValueObjectParamConverter implements ParamConverterInterface
{
    /**
     * @psalm-suppress UnsafeInstantiation
     */
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $name = $configuration->getName();
        /** @var class-string<AbstractValueObject> $class */
        $class = $configuration->getClass();

        $value = new $class($this->getValueFromRequest($request, $name) ?: $class::DEFAULT_VALUE);
        $request->attributes->set($name, $value);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return is_a($configuration->getClass(), ValueObjectInterface::class, true);
    }

    private function getValueFromRequest(Request $request, string $name): string
    {
        if ($request->attributes->has($name)) {
            return (string) $request->attributes->get($name);
        }

        return (string) $request->query->get($name);
    }
}
