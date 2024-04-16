<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use Throwable;

final class JobErrorEvent extends JobEvent
{
    private Throwable $error;

    public function __construct(JobInterface $job, Throwable $error)
    {
        parent::__construct($job);

        $this->error = $error;
    }

    public function getError(): Throwable
    {
        return $this->error;
    }

    public function setError(Throwable $error): self
    {
        $this->error = $error;

        return $this;
    }
}
