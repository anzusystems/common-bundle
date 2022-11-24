<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class DefaultExceptionHandler implements ExceptionHandlerInterface
{
    public const ERROR = 'unknown_error';

    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'detail' => $exception->getMessage(),
                'contextId' => AnzuApp::getContextId(),
            ],
            JsonResponse::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [Throwable::class];
    }
}
