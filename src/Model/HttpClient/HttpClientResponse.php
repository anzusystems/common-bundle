<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Model\HttpClient;

final class HttpClientResponse
{
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
        return 0 === $this->statusCode || $this->statusCode >= 300;
    }
}
