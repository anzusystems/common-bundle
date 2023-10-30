<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\HttpClient;

use Symfony\Component\HttpFoundation\Response;

final class HttpClientResponse
{
    private const STATUS_REDIRECTION_FROM = Response::HTTP_MULTIPLE_CHOICES;
    private const STATUS_CLIENT_ERROR_FROM = Response::HTTP_BAD_REQUEST;
    private const STATUS_SERVER_ERROR_FROM = Response::HTTP_INTERNAL_SERVER_ERROR;

    public function __construct(
        private readonly string $content = '',
        private readonly int $statusCode = 0
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function hasError(): bool
    {
        return 0 === $this->statusCode || $this->statusCode >= self::STATUS_REDIRECTION_FROM;
    }

    public function hasClientError(): bool
    {
        return $this->statusCode >= self::STATUS_CLIENT_ERROR_FROM && $this->statusCode < self::STATUS_SERVER_ERROR_FROM;
    }

    public function hasServerError(): bool
    {
        return $this->statusCode >= self::STATUS_SERVER_ERROR_FROM;
    }
}
