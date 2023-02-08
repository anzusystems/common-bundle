<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job;

use AnzuSystems\CommonBundle\Domain\Job\Processor\JobProcessorInterface;
use AnzuSystems\CommonBundle\Repository\JobRepository;
use LogicException;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class JobProcessor
{
    public function __construct(
        private readonly JobRepository $jobRepo,
        private readonly ServiceLocator $processorsLocator,
    ) {
    }

    /**
     * @throws LogicException
     */
    public function process(): void
    {
        $job = $this->jobRepo->findOneProcessableJob();
        if (null === $job) {
            return;
        }

        if (false === $this->processorsLocator->has($job::class)) {
            throw new LogicException(sprintf('Not found a job processor for "%s"!', $job::class));
        }

        /** @var JobProcessorInterface $processor */
        $processor = $this->processorsLocator->get($job::class);
        $processor->process($job);
    }
}
