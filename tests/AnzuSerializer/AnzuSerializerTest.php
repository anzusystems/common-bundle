<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\AnzuSerializer;

use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\SerializerTestDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject\DummyValueObject;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final class AnzuSerializerTest extends AnzuKernelTestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        /** @var Serializer $serializer */
        $serializer = self::getContainer()->get(Serializer::class);
        $this->serializer = $serializer;
    }

    /**
     * @dataProvider serializeProvider
     * @throws SerializerException
     */
    public function testSerialize(SerializerTestDto $dto, string $expected): void
    {
        $actual = $this->serializer->serialize($dto);
        self::assertEquals($expected, $actual);
    }

    /**
     * @dataProvider serializeProvider
     * @throws SerializerException
     */
    public function testDeserialize(SerializerTestDto $expected, string $payload): void
    {
        $actual = $this->serializer->deserialize($payload, SerializerTestDto::class);
        $this->assertDtoEquals($expected, $actual);
    }

    /**
     * @return list<array{object, string}>
     */
    public function serializeProvider(): array
    {
        $itemsWithKeys = new ArrayCollection();
        $itemsWithKeys->set('A', (new DummyDto())->setData('one'));
        $itemsWithKeys->set('B', (new DummyDto())->setData('two'));
        $itemsWithKeys->set('C', (new DummyDto())->setData('three'));

        $items = new ArrayCollection();
        $items->add((new DummyDto())->setData('one'));
        $items->add((new DummyDto())->setData('two'));
        $items->add((new DummyDto())->setData('three'));

        return [
            [
                (new SerializerTestDto())
                    ->setName('test')
                    ->setPosition(1)
                    ->setDummyDto((new DummyDto())->setData('bla'))
                    ->setDummyValueObject(new DummyValueObject(DummyValueObject::ANZU))
                    ->setDummyEnum(DummyEnum::StateTwo)
                    ->setCreatedAt(new DateTimeImmutable('1986-08-04T23:28:12Z'))
                    ->setCreatedAtFormat1(new DateTimeImmutable('1986-08-04T23:28:12Z'))
                    ->setItems($itemsWithKeys)
                    ->setItemsKeysValues($itemsWithKeys)
                    ->setItemsArray($itemsWithKeys->toArray())
                    ->setItemsArrayKeysValues($itemsWithKeys->toArray()),
                '{'
                    . '"name":"test",'
                    . '"position":1,'
                    . '"dummyDto":{"data":"bla"},'
                    . '"createdAt":"1986-08-04T23:28:12.000000Z",'
                    . '"createdAtFormat1":"04.08.1986 23:28:12",'
                    . '"dummyValueObject":"anzu",'
                    . '"dummyEnum":"state_two",'
                    . '"items":[{"data":"one"},{"data":"two"},{"data":"three"}],'
                    . '"itemsKeysValues":{"A":{"data":"one"},"B":{"data":"two"},"C":{"data":"three"}},'
                    . '"itemsArray":[{"data":"one"},{"data":"two"},{"data":"three"}],'
                    . '"itemsArrayKeysValues":{"A":{"data":"one"},"B":{"data":"two"},"C":{"data":"three"}},'
                    . '"createdAtTimestamp":523582092'
                . '}',
            ], [
                (new SerializerTestDto())
                    ->setName('test')
                    ->setPosition(1)
                    ->setDummyDto((new DummyDto())->setData('bla'))
                    ->setDummyValueObject(new DummyValueObject(DummyValueObject::ANZU))
                    ->setDummyEnum(DummyEnum::StateTwo)
                    ->setCreatedAt(new DateTimeImmutable('1986-08-04T23:28:12Z'))
                    ->setCreatedAtFormat1(new DateTimeImmutable('1986-08-04T23:28:12Z'))
                    ->setItems($items)
                    ->setItemsArray($items->toArray())
                    ->setItemsKeysValues($items)
                    ->setItemsArrayKeysValues($items->toArray()),
                '{'
                    . '"name":"test",'
                    . '"position":1,'
                    . '"dummyDto":{"data":"bla"},'
                    . '"createdAt":"1986-08-04T23:28:12.000000Z",'
                    . '"createdAtFormat1":"04.08.1986 23:28:12",'
                    . '"dummyValueObject":"anzu",'
                    . '"dummyEnum":"state_two",'
                    . '"items":[{"data":"one"},{"data":"two"},{"data":"three"}],'
                    . '"itemsKeysValues":[{"data":"one"},{"data":"two"},{"data":"three"}],'
                    . '"itemsArray":[{"data":"one"},{"data":"two"},{"data":"three"}],'
                    . '"itemsArrayKeysValues":[{"data":"one"},{"data":"two"},{"data":"three"}],'
                    . '"createdAtTimestamp":523582092'
                . '}',
            ],
        ];
    }

    private function assertDtoEquals(SerializerTestDto $expected, SerializerTestDto $actual): void
    {
        self::assertEquals($expected->getName(), $actual->getName());
        self::assertEquals($expected->getPosition(), $actual->getPosition());
        self::assertEquals($expected->getCreatedAt(), $actual->getCreatedAt());
        self::assertEquals($expected->getCreatedAtTimestamp(), $actual->getCreatedAtTimestamp());
        self::assertEquals($expected->getDummyValueObject()->toString(), $actual->getDummyValueObject()->toString());
        self::assertEquals($expected->getDummyEnum()->toString(), $actual->getDummyEnum()->toString());
    }
}
