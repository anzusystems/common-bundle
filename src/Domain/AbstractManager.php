<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Domain;

use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Traits\EntityManagerAwareTrait;
use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use AnzuSystems\Contracts\Entity\Interfaces\TimeTrackingInterface;
use AnzuSystems\Contracts\Entity\Interfaces\UserTrackingInterface;
use Closure;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Entity persistence management.
 */
abstract class AbstractManager
{
    use EntityManagerAwareTrait;

    private CurrentAnzuUserProvider $currentAnzuUserProvider;

    #[Required]
    public function setCurrentAnzuUserProvider(CurrentAnzuUserProvider $currentAnzuUserProvider): void
    {
        $this->currentAnzuUserProvider = $currentAnzuUserProvider;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Flush only if needed.
     *
     * i.e. in batch processing flush after 100 entities.
     */
    public function flush(bool $flush = true): void
    {
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function beginTransaction(): void
    {
        $this->entityManager->beginTransaction();
    }

    public function commit(): void
    {
        $this->entityManager->commit();
    }

    public function rollback(): void
    {
        $this->entityManager->rollback();
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }

    public function trackCreation(object $object): void
    {
        if ($object instanceof TimeTrackingInterface) {
            $object->setCreatedAt(new DateTimeImmutable());
            $object->setModifiedAt(new DateTimeImmutable());
        }
        if ($object instanceof UserTrackingInterface) {
            $object->setCreatedBy($this->currentAnzuUserProvider->getCurrentUser());
            $object->setModifiedBy($this->currentAnzuUserProvider->getCurrentUser());
        }
    }

    public function trackModification(object $object): void
    {
        if ($object instanceof TimeTrackingInterface) {
            $object->setModifiedAt(new DateTimeImmutable());
        }
        if ($object instanceof UserTrackingInterface) {
            $object->setModifiedBy($this->currentAnzuUserProvider->getCurrentUser());
        }
    }

    /**
     * @param $oldCollection - old collection to be updated
     * @param $newCollection - new collection with changed items
     * @param $updateElementFn - run this function on each item that is in both old and new collections
     * @param $addElementFn - run this function on each item that is in new but not in old collection
     * @param $removeElementFn - run this function on each item that is in old but not in new collection
     * @param $colDiffFn - this function must return spaceship operator comparison result
     */
    public function colUpdate(
        Collection $oldCollection,
        Collection $newCollection,
        ?Closure $updateElementFn = null,
        ?Closure $addElementFn = null,
        ?Closure $removeElementFn = null,
        ?Closure $colDiffFn = null,
    ): static {
        // Remove removed.
        $removeElementFn ??= static fn (Collection $oldCollection, BaseIdentifiableInterface $del): bool => $oldCollection->removeElement($del);
        foreach (CollectionHelper::colDiff($oldCollection, $newCollection, $colDiffFn) as $del) {
            $removeElementFn($oldCollection, $del);
        }

        // Update updated.
        if ($updateElementFn) {
            $elementCompareFn = $colDiffFn ?? static fn (
                BaseIdentifiableInterface $entityOne,
                BaseIdentifiableInterface $entityTwo
            ): int => $entityOne->getId() <=> $entityTwo->getId();
            foreach ($oldCollection as $upd) {
                /** @var BaseIdentifiableInterface $newUpd */
                $newUpd = $newCollection->filter(
                    fn (BaseIdentifiableInterface $newElement): bool => 0 === $elementCompareFn($upd, $newElement)
                )->first();
                $updateElementFn($upd, $newUpd);
            }
        }

        // Add new.
        $addElementFn ??= static fn (Collection $oldCollection, BaseIdentifiableInterface $add): mixed => $oldCollection->add($add);
        foreach (CollectionHelper::colDiff($newCollection, $oldCollection, $colDiffFn) as $add) {
            $addElementFn($oldCollection, $add);
        }

        return $this;
    }
}
