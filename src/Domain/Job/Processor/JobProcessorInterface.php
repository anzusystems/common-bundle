<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain\Job\Processor;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\Entity\Interfaces\JobInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_JOB_PROCESSOR)]
interface JobProcessorInterface
{
    /**
     * @return class-string<JobInterface>
     */
    public static function getSupportedJob(): string;

    public function process(JobInterface $job): void;
}
