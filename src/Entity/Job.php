<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use AnzuSystems\Contracts\Entity\Traits\IdentityTrait;
use AnzuSystems\Contracts\Entity\Traits\TimeTrackingTrait;
use AnzuSystems\Contracts\Entity\Traits\UserTrackingTrait;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discriminator', type: 'string')]
#[ORM\Index(fields: ['status', 'scheduledAt', 'priority'], name: 'IDX_job')]
abstract class Job implements UserTrackingInterface, TimeTrackingInterface, JobInterface
{
    use IdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;
    
    public const PRIORITY_LOW = 0;

    /**
     * Time after which the job can be processed.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Serialize]
    protected DateTimeImmutable $scheduledAt;

    /**
     * Priority of the job.
     */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Serialize]
    protected int $priority = self::PRIORITY_LOW;

    /**
     * Status of job.
     */
    #[ORM\Column(enumType: JobStatus::class)]
    #[Serialize]
    protected JobStatus $status = JobStatus::Default;

    /**
     * Start date of job.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    protected ?DateTimeImmutable $startedAt = null;

    /**
     * Finish date of job.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    protected ?DateTimeImmutable $finishedAt = null;

    /**
     * In case of batch processing, it might contain a needle from which should the batch processing continue.
     */
    #[ORM\Column(type: Types::STRING)]
    #[Serialize]
    protected string $lastBatchProcessedRecord = '';

    /**
     * In case of batch processing, it counts total number of batch processed iterations.
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Serialize]
    protected int $batchProcessedIterationCount = 0;

    /**
     * Optional result data.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    protected string $result = '';

    public function __construct()
    {
        $this->setScheduledAt(AnzuApp::getAppDate());
    }

    public function getScheduledAt(): DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getStatus(): JobStatus
    {
        return $this->status;
    }

    public function setStatus(JobStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getLastBatchProcessedRecord(): string
    {
        return $this->lastBatchProcessedRecord;
    }

    public function setLastBatchProcessedRecord(string $lastBatchProcessedRecord): self
    {
        $this->lastBatchProcessedRecord = $lastBatchProcessedRecord;

        return $this;
    }

    public function getBatchProcessedIterationCount(): int
    {
        return $this->batchProcessedIterationCount;
    }

    public function setBatchProcessedIterationCount(int $batchProcessedIterationCount): self
    {
        $this->batchProcessedIterationCount = $batchProcessedIterationCount;

        return $this;
    }

    public function increaseBatchProcessedIterationCount(): self
    {
        ++$this->batchProcessedIterationCount;

        return $this;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;

        return $this;
    }
}
