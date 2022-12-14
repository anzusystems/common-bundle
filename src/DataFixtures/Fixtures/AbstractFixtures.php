<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\DataFixtures\Fixtures;

use AnzuSystems\CommonBundle\DataFixtures\Interfaces\FixturesInterface;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use InvalidArgumentException;

/**
 * @template E of object
 * @implements FixturesInterface<E>
 */
abstract class AbstractFixtures implements FixturesInterface
{
    use EntityManagerAwareTrait;

    /**
     * @var ArrayCollection<int|string, E>|null
     */
    private ?ArrayCollection $registry;
    private static int $defaultPriority = -1;

    public static function getDependencies(): array
    {
        return [];
    }

    public static function getPriority(): int
    {
        $priority = self::$defaultPriority;
        foreach (static::getDependencies() as $dependency) {
            $priority += $dependency::getPriority();
        }

        return $priority;
    }

    public function getRegistry(): ArrayCollection
    {
        if (false === isset($this->registry)) {
            $this->registry = new ArrayCollection();
        }

        return $this->registry;
    }

    /**
     * @return E
     *
     * @throws InvalidArgumentException
     */
    public function getOneFromRegistry(string | int $key): object
    {
        $object = $this->getRegistry()->get($key);
        if (null === $object) {
            throw new InvalidArgumentException(sprintf('Object key "%s" not found in registry!', $key));
        }

        return $object;
    }

    /**
     * @param E $object
     */
    public function addToRegistry(object $object, string | int $key = null): self
    {
        if ($key) {
            $this->getRegistry()->set($key, $object);

            return $this;
        }
        $this->getRegistry()->add($object);

        return $this;
    }

    /**
     * @param Closure(E):bool $filter
     *
     * @return E|null
     */
    public function findOneRegistryRecord(Closure $filter): ?object
    {
        return $this->getRegistry()->filter($filter)->first() ?: null;
    }

    public function useCustomId(): bool
    {
        return false;
    }

    public function configureAssignedGenerator(): void
    {
        $meta = $this->entityManager->getClassMetadata(static::getIndexKey());
        $meta->setIdGenerator(new AssignedGenerator());
        $meta->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
    }
}
