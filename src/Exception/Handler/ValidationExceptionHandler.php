<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\CommonBundle\Exception\ValidationException;
use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class ValidationExceptionHandler implements ExceptionHandlerInterface
{
    public const ERROR = ValidationException::ERROR_MESSAGE;

    /**
     * @param ValidationException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'fields' => $exception->getFormattedErrors(),
                'contextId' => AnzuApp::getContextId(),
            ],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [ValidationException::class];
    }
}
