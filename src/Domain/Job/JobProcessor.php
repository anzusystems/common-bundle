<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\Processor\JobProcessorInterface;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use LogicException;
use Symfony\Contracts\Service\ServiceProviderInterface;

final readonly class JobProcessor
{
    public function __construct(
        private ServiceProviderInterface $processorProvider,
    ) {
    }

    /**
     * @throws LogicException
     */
    public function process(JobInterface $job): bool
    {
        if (false === $this->processorProvider->has($job::class)) {
            throw new LogicException(sprintf('Not found a job processor for "%s"!', $job::class));
        }

        /** @var JobProcessorInterface $processor */
        $processor = $this->processorProvider->get($job::class);

        return $processor->process($job);
    }
}
