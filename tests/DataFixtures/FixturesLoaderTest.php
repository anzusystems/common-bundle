<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DataFixtures;

use AnzuSystems\CommonBundle\DataFixtures\Fixtures\AbstractFixtures;
use AnzuSystems\CommonBundle\DataFixtures\FixturesLoader;
use AnzuSystems\CommonBundle\DataFixtures\Interfaces\FixturesInterface;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;

final class FixturesLoaderTest extends AnzuKernelTestCase
{
    private const int FIRST_EXAMPLE_ID = 1_001;
    private const int SECOND_EXAMPLE_ID = 1_002;
    private const string NAME = 'example';
    private const string CHANGED_NAME = 'changed';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        $this->entityManager->clear();
    }

    public function testLoadedEntitiesAreSavedAndBecomeReadOnly(): void
    {
        $this->load(
            readOnlyAfterLoad: true,
            second: function (EntityManagerInterface $entityManager): void {
                $entityManager->persist($this->createExample(self::SECOND_EXAMPLE_ID));
                $entityManager->flush();
            },
        );

        $first = $this->entityManager->find(Example::class, self::FIRST_EXAMPLE_ID);
        self::assertInstanceOf(Example::class, $first);
        self::assertTrue($this->entityManager->getUnitOfWork()->isReadOnly($first));
        self::assertSame(self::NAME, $this->fetchName(self::FIRST_EXAMPLE_ID));
        self::assertSame(self::NAME, $this->fetchName(self::SECOND_EXAMPLE_ID));
    }

    /**
     * @dataProvider changeOfAnEarlierEntityDataProvider
     *
     * @param list<class-string> $updatedEntityClasses
     */
    public function testChangeOfAnEarlierEntity(bool $readOnlyAfterLoad, array $updatedEntityClasses, string $expectedName): void
    {
        $this->load(
            readOnlyAfterLoad: $readOnlyAfterLoad,
            second: static function (EntityManagerInterface $entityManager): void {
                $entityManager->find(Example::class, self::FIRST_EXAMPLE_ID)?->setName(self::CHANGED_NAME);
                $entityManager->flush();
            },
            secondUpdatedEntityClasses: $updatedEntityClasses,
        );

        self::assertSame($expectedName, $this->fetchName(self::FIRST_EXAMPLE_ID));
    }

    public static function changeOfAnEarlierEntityDataProvider(): iterable
    {
        yield 'saved without read-only after load' => [false, [], self::CHANGED_NAME];
        yield 'dropped when the entity is read-only' => [true, [], self::NAME];
        yield 'saved when the changing fixture declares the class' => [true, [Example::class], self::CHANGED_NAME];
    }

    /**
     * The first fixture leaves its entity unflushed, the loader has to save it before making it read-only.
     *
     * @param Closure(EntityManagerInterface): void $second
     * @param list<class-string> $secondUpdatedEntityClasses
     */
    private function load(bool $readOnlyAfterLoad, Closure $second, array $secondUpdatedEntityClasses = []): void
    {
        $first = function (EntityManagerInterface $entityManager): void {
            $entityManager->persist($this->createExample(self::FIRST_EXAMPLE_ID));
        };
        $loader = new FixturesLoader(
            fixtures: [
                $this->createFixtures($first, []),
                $this->createFixtures($second, $secondUpdatedEntityClasses),
            ],
            env: FixturesInterface::DEFAULT_FIXTURES_ENVIRONMENT,
            entityManager: $this->entityManager,
            readOnlyAfterLoad: $readOnlyAfterLoad,
        );
        $loader->load(new NullOutput());
    }

    /**
     * @param Closure(EntityManagerInterface): void $loadCallback
     * @param list<class-string> $updatedEntityClasses
     */
    private function createFixtures(Closure $loadCallback, array $updatedEntityClasses): AbstractFixtures
    {
        $fixtures = new class($loadCallback, $updatedEntityClasses) extends AbstractFixtures {
            public function __construct(
                private readonly Closure $loadCallback,
                private readonly array $updatedEntityClasses,
            ) {
            }

            public static function getIndexKey(): string
            {
                return Example::class;
            }

            public function getUpdatedEntityClasses(): array
            {
                return $this->updatedEntityClasses;
            }

            public function load(ProgressBar $progressBar): void
            {
                ($this->loadCallback)($this->entityManager);
            }
        };
        $fixtures->setEntityManager($this->entityManager);

        return $fixtures;
    }

    private function createExample(int $id): Example
    {
        return (new Example())
            ->setId($id)
            ->setName(self::NAME)
        ;
    }

    private function fetchName(int $id): mixed
    {
        return $this->entityManager->getConnection()
            ->fetchOne('SELECT name FROM example WHERE id = ?', [$id]);
    }
}
