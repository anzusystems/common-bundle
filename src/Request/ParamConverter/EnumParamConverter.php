<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Request\ParamConverter;

use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class EnumParamConverter implements ParamConverterInterface
{
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $class = $configuration->getClass();
        if (is_a($class, EnumInterface::class, true)) {
            $enum = $class::tryFrom($this->getValueFromRequest($request, $configuration->getName()));
            if ($enum instanceof EnumInterface) {
                $request->attributes->set($configuration->getName(), $enum);

                return true;
            }
        }

        return false;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return is_a($configuration->getClass(), EnumInterface::class, true);
    }

    private function getValueFromRequest(Request $request, string $name): string
    {
        if ($request->attributes->has($name)) {
            return (string) $request->attributes->get($name);
        }

        return (string) $request->query->get($name);
    }
}
