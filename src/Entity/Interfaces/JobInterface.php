<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Entity\Interfaces;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use DateTimeImmutable;

interface JobInterface extends IdentifiableInterface
{
    public function getStatus(): JobStatus;

    public function setStatus(JobStatus $status): static;

    public function getStartedAt(): ?DateTimeImmutable;

    public function setStartedAt(?DateTimeImmutable $startedAt): static;

    public function getFinishedAt(): ?DateTimeImmutable;

    public function setFinishedAt(?DateTimeImmutable $finishedAt): static;

    public function getResult(): string;

    public function setResult(string $result): static;
}
