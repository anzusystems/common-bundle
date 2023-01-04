<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Request\ValueResolver;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ApiFilterParamValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (ApiParams::class === $argument->getType()) {
            return [(new ApiParams())->setFromRequest($request)];
        }

        return [];
    }
}
