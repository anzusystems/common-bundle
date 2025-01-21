<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DataFixtures\Interfaces;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template E of object
 */
#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_DATA_FIXTURE)]
interface FixturesInterface
{
    public const string DEFAULT_FIXTURES_ENVIRONMENT = 'dev';

    /**
     * @return class-string<E>
     */
    public static function getIndexKey(): string;

    public static function getPriority(): int;

    public function getEnvironments(): array;

    /**
     * @template F of FixturesInterface
     *
     * @return array<class-string<F>>
     */
    public static function getDependencies(): array;

    public function load(ProgressBar $progressBar): void;

    /**
     * @return ArrayCollection<int|string, E>
     */
    public function getRegistry(): ArrayCollection;

    public function useCustomId(): bool;

    public function configureAssignedGenerator(): void;

    public function disableAssignedGenerator(): void;
}
