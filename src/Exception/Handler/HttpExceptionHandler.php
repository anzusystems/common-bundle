<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Exception\Handler;

use AnzuSystems\Contracts\AnzuApp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class HttpExceptionHandler implements ExceptionHandlerInterface
{
    public const string ERROR = 'http_error';

    public function __construct(
        private readonly bool $debug = false
    ) {
    }

    /**
     * @param HttpException $exception
     */
    public function getErrorResponse(Throwable $exception): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => self::ERROR,
                'detail' => $this->debug ? $exception->getMessage() : 'An error occurred',
                'contextId' => AnzuApp::getContextId(),
            ],
            $exception->getStatusCode()
        );
    }

    public function getSupportedExceptionClasses(): array
    {
        return [HttpException::class];
    }
}
