<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER)]
interface ExceptionHandlerInterface
{
    public function getErrorResponse(Throwable $exception): JsonResponse;

    /**
     * @template T of Throwable
     *
     * @return array<class-string<T>>
     */
    public function getSupportedExceptionClasses(): array;
}
