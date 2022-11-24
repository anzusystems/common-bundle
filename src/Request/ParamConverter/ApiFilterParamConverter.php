<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Request\ParamConverter;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class ApiFilterParamConverter implements ParamConverterInterface
{
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $apiParams = (new ApiParams())->setFromRequest($request);
        $request->attributes->set($configuration->getName(), $apiParams);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return ApiParams::class === $configuration->getClass();
    }
}
