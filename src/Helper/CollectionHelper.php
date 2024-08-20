<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Helper;

use AnzuSystems\Contracts\Entity\Interfaces\BaseIdentifiableInterface;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\PropertyInfo\Type;
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
        return array_values(
            array_map(
                $getIdAction ?: static fn (BaseIdentifiableInterface $identifiable): mixed => $identifiable->getId(),
                iterator_to_array($traversable)
            )
        );
    }

    public static function arrayStringToArray(string $array, string $type = Type::BUILTIN_TYPE_INT): array
    {
        return array_map(
            fn (string $item): string|int|float => match ($type) {
                Type::BUILTIN_TYPE_INT => (int) $item,
                Type::BUILTIN_TYPE_FLOAT => (float) $item,
                default => trim($item),
            },
            explode(',', $array)
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

    /**
     * @template T
     * @template TKey of array-key
     *
     * @param Collection<TKey, T> $collection
     *
     * @return Collection<string, T>
     */
    public static function getIndexedByUuid(Collection $collection): Collection
    {
        $indexedCol = new ArrayCollection();
        foreach ($collection as $item) {
            $indexedCol->set($item->getId()->toRfc4122(), $item);
        }

        return $indexedCol;
    }

    /**
     * @template TItem
     *
     * @param array<array-key, TItem> $items
     *
     * @return ArrayCollection<array-key, TItem>
     */
    public static function newCollection(array $items): ArrayCollection
    {
        /** @var ArrayCollection<array-key, TItem> $new */
        $new = new ArrayCollection($items);

        return $new;
    }
}
