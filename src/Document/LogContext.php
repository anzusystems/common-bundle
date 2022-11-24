<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Document;

use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class LogContext
{
    #[Serialize]
    private string $appSystem;

    #[Serialize]
    private string $appVersion;

    #[Serialize]
    private string $requestOriginAppVersion;

    #[Serialize]
    private string $method;

    #[Serialize]
    private string $path;

    #[Serialize]
    private string $contextId;

    #[Serialize]
    private int $userId;

    #[Serialize]
    private array $params;

    #[Serialize]
    private string $content;

    #[Serialize]
    private string $ip;

    #[Serialize]
    private string $response;

    #[Serialize]
    private int $httpStatus;

    #[Serialize]
    private int $timeout;

    #[Serialize]
    private string $exception;

    #[Serialize]
    private string $error;

    public function __construct()
    {
        $this
            ->setAppSystem('')
            ->setAppVersion('')
            ->setRequestOriginAppVersion('')
            ->setPath('')
            ->setMethod('')
            ->setContextId('')
            ->setUserId(0)
            ->setIp('')
            ->setContent('')
            ->setParams([])
            ->setResponse('')
            ->setHttpStatus(0)
            ->setTimeout(0)
            ->setError('')
            ->setException('')
        ;
    }

    public function getAppSystem(): string
    {
        return $this->appSystem;
    }

    public function setAppSystem(string $appSystem): self
    {
        $this->appSystem = $appSystem;

        return $this;
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    public function setAppVersion(string $appVersion): self
    {
        $this->appVersion = $appVersion;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getRequestOriginAppVersion(): string
    {
        return $this->requestOriginAppVersion;
    }

    public function setRequestOriginAppVersion(string $requestOriginAppVersion): self
    {
        $this->requestOriginAppVersion = $requestOriginAppVersion;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }

    public function setContextId(string $contextId): self
    {
        $this->contextId = $contextId;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getResponse(): string
    {
        return $this->response;
    }

    public function setResponse(string $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;

        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getException(): string
    {
        return $this->exception;
    }

    public function setException(string $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function setError(string $error): self
    {
        $this->error = $error;

        return $this;
    }
}
