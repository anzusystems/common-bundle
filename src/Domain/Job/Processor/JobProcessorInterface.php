<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;

interface JobProcessorInterface
{
    /**
     * @return class-string<JobInterface>
     */
    public static function getSupportedJob(): string;

    public function process(JobInterface $job): void;
}
