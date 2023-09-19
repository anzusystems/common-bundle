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
    private const ERROR = 'serializer_error';

    public function __construct(
        private readonly bool $debug = false
    ) {
    }

    /**
     * @param SerializerException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'detail' => $this->debug ? $exception->getMessage() : 'Serialization error',
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
