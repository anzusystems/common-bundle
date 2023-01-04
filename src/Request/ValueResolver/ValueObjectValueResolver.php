<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Request\ValueResolver;

use AnzuSystems\Contracts\Model\ValueObject\AbstractValueObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ValueObjectValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (is_a($argument->getType(), AbstractValueObject::class, true)) {
            $name = $argument->getName();
            /** @var class-string<AbstractValueObject> $class */
            $class = $argument->getType();

            /** @psalm-suppress UnsafeInstantiation */
            return [new $class($this->getValueFromRequest($request, $name) ?: $class::DEFAULT_VALUE)];
        }

        return [];
    }

    private function getValueFromRequest(Request $request, string $name): string
    {
        if ($request->attributes->has($name)) {
            return (string) $request->attributes->get($name);
        }

        return (string) $request->query->get($name);
    }
}
