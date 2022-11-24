<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Serializer\Exception;

use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final class SerializerExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @param SerializerException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => $exception->getMessage(),
                'contextId' => AnzuApp::getContextId(),
            ],
            JsonResponse::HTTP_BAD_REQUEST
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [SerializerException::class];
    }
}
