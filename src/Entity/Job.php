<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Entity;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use AnzuSystems\CommonBundle\Model\Enum\JobStatus;
use AnzuSystems\CommonBundle\Repository\JobRepository;
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
#[ORM\InheritanceType('SINGLE_TABLE')]
abstract class Job implements UserTrackingInterface, TimeTrackingInterface, JobInterface
{
    use IdentityTrait;
    use TimeTrackingTrait;
    use UserTrackingTrait;

    /**
     * Status of job.
     */
    #[ORM\Column(enumType: JobStatus::class)]
    #[Serialize]
    protected JobStatus $status;

    /**
     * Start date of job.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    protected ?DateTimeImmutable $startedAt;

    /**
     * Finish date of job.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Serialize]
    protected ?DateTimeImmutable $finishedAt;

    /**
     * Optional result data.
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Serialize]
    protected string $result;

    public function __construct()
    {
        $this->setStatus(JobStatus::Default);
        $this->setResult('');
        $this->setStartedAt(null);
        $this->setFinishedAt(null);
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