<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Helper;

use AnzuSystems\CommonBundle\Helper\UuidHelper;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Entity\User;
use AnzuSystems\Contracts\Entity\Interfaces\IdentifiableInterface;

final class UuidHelperTest extends AnzuKernelTestCase
{
    /**
     * @dataProvider anzuIdProvider
     */
    public function testGetAnzuId(
        string $expectedResult,
        string $resourceName,
        string $system,
        int $id,
        int $groupId = 0,
    ): void {
        self::assertSame($expectedResult, UuidHelper::getAnzuId($resourceName, $system, $id, $groupId));
    }

    /**
     * @dataProvider anzuIdIdentifiableProvider
     */
    public function testGetAnzuIdByIdentifiable(string $expectedResult, IdentifiableInterface $identifiable): void
    {
        self::assertSame($expectedResult, UuidHelper::getAnzuIdByIdentifiable($identifiable));
    }

    /**
     * @return list<array{string, string, string, int, int}>
     */
    public function anzuIdProvider(): array
    {
        return [
            [
                'article0-core-0000-0000-000000000010',
                'article',
                'core',
                10,
                0,
            ],
            [
                'article0-core-0000-0001-000000000001',
                'article',
                'core',
                1,
                1,
            ],
            [
                'topic000-foru-0000-9999-000999999999',
                'topic',
                'forum',
                999_999_999,
                999_999_999,
            ],
            [
                'image000-dam0-0000-0021-000000000100',
                'image',
                'dam',
                100,
                21,
            ],
        ];
    }

    public function anzuIdIdentifiableProvider(): array
    {
        return [
            [
                'user0000-test-0000-0000-000000000010',
                (new User())
                    ->setId(10)
            ],
            [
                'user0000-test-0000-0000-000999999999',
                (new User())
                    ->setId(999_999_999)
            ],
        ];
    }
}
