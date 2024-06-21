<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class NotFoundExceptionHandler implements ExceptionHandlerInterface
{
    public const string ERROR = 'not_found';

    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'detail' => $exception->getMessage(),
                'contextId' => AnzuApp::getContextId(),
            ],
            JsonResponse::HTTP_NOT_FOUND
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [NotFoundHttpException::class];
    }
}
