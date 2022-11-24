<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject\DummyValueObject;
use AnzuSystems\Contracts\Model\Enum\EnumInterface;
use Symfony\Component\HttpFoundation\Request;

final class ParamConverterControllerTest extends AbstractControllerTest
{
    public function testValueObjectConverter(): void
    {
        $uriFactory = static fn (string $value): string => sprintf('/dummy/param-converter/value-object/%s', $value);

        $expectedValue = DummyValueObject::ANZU;
        self::$client->request(method: Request::METHOD_GET, uri: $uriFactory($expectedValue));
        self::assertResponseIsSuccessful();
        $actual = self::$client->getResponse()->getContent();
        self::assertSame($expectedValue, $actual);

        $expectedValue = DummyValueObject::TEST;
        self::$client->request(method: Request::METHOD_GET, uri: $uriFactory($expectedValue));
        self::assertResponseIsSuccessful();
        self::assertSame($expectedValue, self::$client->getResponse()->getContent());
    }

    public function tesEnumConverter(): void
    {
        $uriFactory = static fn (EnumInterface $value): string => sprintf('/dummy/param-converter/enum/%s', $value->toString());

        $expectedValue = DummyEnum::StateThree;
        self::$client->request(method: Request::METHOD_GET, uri: $uriFactory($expectedValue));
        self::assertResponseIsSuccessful();
        self::assertSame($expectedValue->toString(), self::$client->getResponse()->getContent());

        $expectedValue = DummyEnum::Default;
        self::$client->request(method: Request::METHOD_GET, uri: $uriFactory($expectedValue));
        self::assertResponseIsSuccessful();
        self::assertSame($expectedValue->toString(), self::$client->getResponse()->getContent());
    }

    public function testSerializerConverter(): void
    {
        $content = (new DummyDto())->setData('test');
        $response = $this->post(
            uri: '/dummy/param-converter/serializer',
            content: $content,
            deserializationClass: DummyDto::class,
        );
        self::assertResponseIsSuccessful();
        self::assertSame($content->getData(), $response->getData());
    }

    public function testApiFilterConverter(): void
    {
        $response = $this->get(
            uri: '/dummy/param-converter/api-filter',
            deserializationClass: ApiParams::class,
            params: [
                'limit' => 50,
                'offset' => 10,
                'bigTable' => false,
                'filter_' . ApiParams::FILTER_EQ => ['test' => 'rest'],
                'order' => ['id' => 'desc'],
            ],
        );
        self::assertResponseIsSuccessful();
        self::assertSame(50, $response->getLimit());
        self::assertSame(10, $response->getOffset());
        self::assertFalse($response->isBigTable());
        self::assertSame([ApiParams::FILTER_EQ => ['test' => 'rest']], $response->getFilter());
        self::assertSame(['id' => 'desc'], $response->getOrder());
    }
}
