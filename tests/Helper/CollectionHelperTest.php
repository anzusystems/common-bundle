<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Helper;

use AnzuSystems\CommonBundle\Helper\CollectionHelper;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Traversable;

final class CollectionHelperTest extends TestCase
{
    /**
     * @dataProvider traversableToIdsProvider
     */
    public function testTraversableToIds(
        Traversable $traversable,
        ?Closure $getIdAction,
        array $expectedResult,
    ): void {
        $ids = CollectionHelper::traversableToIds($traversable, $getIdAction);
        self::assertSame($expectedResult, $ids);
    }

    /**
     * @dataProvider colDiffProvider
     */
    public function testColDiff(
        Collection $collectionOne,
        Collection $collectionTwo,
        ?Closure $compareAction,
        Collection $expectedResult,
    ): void {
        $dif = CollectionHelper::colDiff($collectionOne, $collectionTwo, $compareAction);
        self::assertSame($expectedResult->getValues(), $dif->getValues());
    }

    /**
     * @return list<array{ArrayCollection, null|callable, array<int|string|null>}>
     */
    public function traversableToIdsProvider(): array
    {
        return [
            [
                new ArrayCollection([
                    (new User())->setId(1),
                    (new User())->setId(3),
                    (new User())->setId(5),
                ]),
                null,
                [1, 3, 5],
            ],
            [
                new ArrayCollection([
                    (new User())->setId(null),
                    (new User())->setId(3),
                    (new User())->setId(null),
                ]),
                null,
                [null, 3, null],
            ],
            [
                new ArrayCollection([
                    (new User())->setId(1),
                    (new User())->setId(3),
                    (new User())->setId(null),
                ]),
                static fn (User $identifiable): string => $identifiable->getUserIdentifier(),
                ['1', '3', ''],
            ],
        ];
    }

    /**
     * @return list<array{Collection, Collection, null|callable, Collection}>
     */
    public function colDiffProvider(): array
    {
        $one = (new User())->setId(1);
        $tree = (new User())->setId(3);
        $five = (new User())->setId(5);
        $six = (new User())->setId(6);
        $nine = (new User())->setId(9);

        return [
            [
                new ArrayCollection([$one, $tree, $five]),
                new ArrayCollection([$one, $tree, $six]),
                null,
                new ArrayCollection([$five]),
            ],
            [
                new ArrayCollection([$one, $nine, $tree, $six]),
                new ArrayCollection([$one, $tree, $five]),
                null,
                new ArrayCollection([$nine, $six]),
            ],
            [
                new ArrayCollection([$one, $nine, $tree, $six]),
                new ArrayCollection([$one, $tree, $five]),
                static function (
                    User $identifiableOne,
                    User $identifiableTwo,
                ): int {
                    return $identifiableOne->getUserIdentifier() <=> $identifiableTwo->getUserIdentifier();
                },
                new ArrayCollection([$nine, $six]),
            ],
        ];
    }
}
