<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CommonBundle\DataFixtures\Interfaces\FixturesInterface;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_DATA_FIXTURE)]
final class ExampleFixtures extends AbstractFixtures
{
    public static function getIndexKey(): string
    {
        return Example::class;
    }

    public function getEnvironments(): array
    {
        return parent::getEnvironments() + ['test'];
    }

    public function useCustomId(): bool
    {
        return true;
    }

    public function load(ProgressBar $progressBar): void
    {
        foreach ($progressBar->iterate($this->getData()) as $example) {
            $this->entityManager->persist($example);
            $this->entityManager->flush();
        }
    }

    /**
     * @return Generator<int, Example>
     */
    private function getData(): Generator
    {
        yield (new Example())
            ->setId(Example::EXAMPLE_INSTANCE_ID)
            ->setName('test')
            ->setDummyEnum(DummyEnum::StateThree)
        ;
    }
}
