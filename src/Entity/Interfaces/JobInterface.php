<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Entity\Interfaces;

use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use DateTimeImmutable;

interface JobInterface extends IdentifiableInterface, UserTrackingInterface, TimeTrackingInterface
{
    public function getStatus(): JobStatus;

    public function setStatus(JobStatus $status): static;

    public function getStartedAt(): ?DateTimeImmutable;

    public function setStartedAt(?DateTimeImmutable $startedAt): static;

    public function getFinishedAt(): ?DateTimeImmutable;

    public function setFinishedAt(?DateTimeImmutable $finishedAt): static;

    public function getLastBatchProcessedRecord(): string;

    public function setLastBatchProcessedRecord(string $lastBatchProcessedRecord): self;

    public function getBatchProcessedIterationCount(): int;

    public function setBatchProcessedIterationCount(int $batchProcessedIterationCount): self;

    public function increaseBatchProcessedIterationCount(): self;

    public function getResult(): string;

    public function setResult(string $result): static;
}
