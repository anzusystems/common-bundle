<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

final class AccessDeniedExceptionHandler implements ExceptionHandlerInterface
{
    private const string ERROR = 'access_denied';

    public function __construct(
        private readonly bool $debug = false
    ) {
    }

    /**
     * @param AccessDeniedException|AccessDeniedHttpException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'detail' => $this->debug ? $exception->getMessage() : 'Access denied',
                'contextId' => AnzuApp::getContextId(),
            ],
            JsonResponse::HTTP_FORBIDDEN
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [AccessDeniedException::class, AccessDeniedHttpException::class];
    }
}
