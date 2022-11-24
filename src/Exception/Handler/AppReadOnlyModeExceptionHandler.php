<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Exception\AppReadOnlyModeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

final class AppReadOnlyModeExceptionHandler implements ExceptionHandlerInterface
{
    private const ERROR = AppReadOnlyModeException::MESSAGE;

    /**
     * @param AccessDeniedException|AccessDeniedHttpException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'detail' => $exception->getMessage(),
                'contextId' => AnzuApp::getContextId(),
            ],
            JsonResponse::HTTP_SERVICE_UNAVAILABLE
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [AppReadOnlyModeException::class];
    }
}
