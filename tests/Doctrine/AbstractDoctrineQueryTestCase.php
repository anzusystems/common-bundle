<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Doctrine;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

abstract class AbstractDoctrineQueryTestCase extends AnzuKernelTestCase
{
    protected Query $query;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        $this->query = new Query($entityManager);
    }
}
