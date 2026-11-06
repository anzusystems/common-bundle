<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Entity;

use AnzuSystems\CommonBundle\Entity\Job;
use AnzuSystems\CommonBundle\Entity\JobUserDataDelete;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final class JobSchemaTest extends AnzuKernelTestCase
{
    private const string SCHEDULED_AT_COLUMN = 'scheduled_at';

    public function testScheduledAtDefaultNeedsNoSchemaChange(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaChanges = new SchemaTool($entityManager)
            ->getUpdateSchemaSql([
                $entityManager->getClassMetadata(Job::class),
                $entityManager->getClassMetadata(JobUserDataDelete::class),
            ]);

        self::assertEmpty(array_filter(
            $schemaChanges,
            static fn (string $sql): bool => str_contains($sql, self::SCHEDULED_AT_COLUMN),
        ));
    }
}
