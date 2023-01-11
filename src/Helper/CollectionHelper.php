<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Traversable;

class CollectionHelper
{
    /**
     * @template T
     *
     * @param null|Closure(T): mixed $getIdAction
     */
    public static function traversableToIds(Traversable $traversable, Closure $getIdAction = null): array
    {
        return array_map(
            $getIdAction ?: static fn (BaseIdentifiableInterface $identifiable): mixed => $identifiable->getId(),
            iterator_to_array($traversable)
        );
    }

    /**
     * @template TKey of array-key
     * @template T
     *
     * @param Collection<TKey, T> $collectionOne
     * @param Collection<TKey, T> $collectionTwo
     * @param null|Closure(T,T): int $colDiffFn
     */
    public static function colDiff(
        Collection $collectionOne,
        Collection $collectionTwo,
        Closure $colDiffFn = null
    ): ArrayCollection {
        return new ArrayCollection(
            array_udiff(
                $collectionOne->toArray(),
                $collectionTwo->toArray(),
                $colDiffFn ?: static fn (
                    BaseIdentifiableInterface $entityOne,
                    BaseIdentifiableInterface $entityTwo
                ): int => $entityOne->getId() <=> $entityTwo->getId()
            )
        );
    }
}
