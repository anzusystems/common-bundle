<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class DefaultExceptionHandler implements ExceptionHandlerInterface
{
    public const ERROR = 'unknown_error';

    public function __construct(
        private readonly bool $debug = false
    ) {
    }

    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'detail' => $this->debug ? $exception->getMessage() : 'An unexpected error occurred',
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
