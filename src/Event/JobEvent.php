<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use Symfony\Contracts\EventDispatcher\Event;

class JobEvent extends Event
{
    protected JobInterface $job;

    public function __construct(JobInterface $job)
    {
        $this->job = $job;
    }

    public function getJob(): JobInterface
    {
        return $this->job;
    }
}
