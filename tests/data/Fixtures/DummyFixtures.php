<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_DATA_FIXTURE)]
final class DummyFixtures extends AbstractFixtures
{
    public static function getIndexKey(): string
    {
        return User::class;
    }

    public function load(ProgressBar $progressBar): void
    {
    }
}
