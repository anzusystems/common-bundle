<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Model;

use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Model\Enum\LogLevel;
use AnzuSystems\SerializerBundle\Attributes\Serialize;

final class LogDto
{
    #[Serialize]
    private LogLevel $level = LogLevel::Default;

    #[Serialize]
    private string $message = '';

    #[Serialize]
    private string $appSystem = '';

    #[Serialize]
    private string $content = '';

    #[Serialize]
    private string $path = '';

    #[Serialize]
    private string $contextId = '';

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function setLevel(LogLevel $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = mb_substr($message, 0, 250);

        return $this;
    }

    public function getAppSystem(): string
    {
        return $this->appSystem;
    }

    public function setAppSystem(string $appSystem): self
    {
        $this->appSystem = mb_substr($appSystem, 0, 50);

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = mb_substr($content, 0, 2_000);

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = mb_substr($path, 0, 500);

        return $this;
    }

    public function getContextId(): string
    {
        return $this->contextId;
    }

    public function setContextId(string $contextId): self
    {
        $this->contextId = uuid_is_valid($contextId) ? $contextId : AnzuApp::getContextId();

        return $this;
    }
}
