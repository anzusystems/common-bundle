<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Document;

use AnzuSystems\Contracts\Document\Attributes\PersistedName;
use AnzuSystems\Contracts\Document\Interfaces\DocumentInterface;
use AnzuSystems\Contracts\Document\Traits\DocumentTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;

final class Log implements DocumentInterface
{
    use DocumentTrait;

    #[Serialize]
    private string $message;

    #[Serialize]
    private DateTimeImmutable $datetime;

    #[PersistedName('level_name')]
    #[Serialize(persistedName: 'level_name')]
    private string $levelName;

    #[Serialize]
    private LogContext $context;

    public function __construct()
    {
        $this
            ->setMessage('')
            ->setLevelName('')
            ->setDatetime(new DateTimeImmutable())
            ->setContext(new LogContext())
        ;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getDatetime(): DateTimeImmutable
    {
        return $this->datetime;
    }

    public function setDatetime(DateTimeImmutable $datetime): self
    {
        $this->datetime = $datetime;

        return $this;
    }

    public function getLevelName(): string
    {
        return $this->levelName;
    }

    public function setLevelName(string $levelName): self
    {
        $this->levelName = $levelName;

        return $this;
    }

    public function getContext(): LogContext
    {
        return $this->context;
    }

    public function setContext(LogContext $context): self
    {
        $this->context = $context;

        return $this;
    }
}
