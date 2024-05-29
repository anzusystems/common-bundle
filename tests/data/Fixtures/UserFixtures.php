<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Data\Fixtures;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\Contracts\AnzuApp;
use Generator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: AnzuSystemsCommonBundle::TAG_DATA_FIXTURE)]
final class UserFixtures extends AbstractFixtures
{
    public static function getIndexKey(): string
    {
        return User::class;
    }

    public function load(ProgressBar $progressBar): void
    {
        foreach ($progressBar->iterate($this->getData()) as $user) {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
    }

    /**
     * @return Generator<int, User>
     */
    private function getData(): Generator
    {
        $consoleUser = (new User())
            ->setEmail('console@anzusystems.sk')
            ->setId(AnzuApp::getUserIdConsole())
            ->setCreatedAt(AnzuApp::getAppDate())
            ->setModifiedAt(AnzuApp::getAppDate())
        ;
        $consoleUser
            ->setCreatedBy($consoleUser)
            ->setModifiedBy($consoleUser)
        ;

        yield $consoleUser;

        yield (new User())
            ->setEmail('anonymous@anzusystems.sk')
            ->setId(AnzuApp::getUserIdAnonymous())
            ->setCreatedAt(AnzuApp::getAppDate())
            ->setModifiedAt(AnzuApp::getAppDate())
            ->setCreatedBy($consoleUser)
            ->setModifiedBy($consoleUser)
        ;
        yield (new User())
            ->setId(AnzuApp::getUserIdAdmin())
            ->setEmail('admin@anzusystems.sk')
            ->setRoles([User::ROLE_ADMIN])
            ->setCreatedAt(AnzuApp::getAppDate())
            ->setModifiedAt(AnzuApp::getAppDate())
            ->setCreatedBy($consoleUser)
            ->setModifiedBy($consoleUser)
        ;
    }
}
