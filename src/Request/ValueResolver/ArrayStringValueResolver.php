<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Request\ValueResolver;

use AnzuSystems\CommonBundle\Model\Attributes\ArrayStringParam;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Routing\Exception\InvalidArgumentException;

final class ArrayStringValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(ArrayStringParam::class)[0] ?? null;
        if ($attribute instanceof ArrayStringParam) {
            return $this->resolveArrayStringParam($argument->getName(), $attribute, $request);
        }

        return [];
    }

    private function resolveArrayStringParam(string $paramName, ArrayStringParam $arrayStringParam, Request $request): iterable
    {
        $items = array_map(
            static fn (string $id): string => trim($id),
            explode($arrayStringParam->separator, (string) $request->attributes->get($paramName))
        );
        $itemsCount = count($items);

        if (is_int($arrayStringParam->itemsLimit) && $itemsCount > $arrayStringParam->itemsLimit) {
            throw new InvalidArgumentException('invalid_array_string_count');
        }
        if (is_callable($arrayStringParam->itemNormalizer)) {
            $items = array_map($arrayStringParam->itemNormalizer, $items);
        }

        return [$items];
    }
}
