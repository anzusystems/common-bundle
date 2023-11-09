<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\ApiFilter\ApiParams;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\DummyDto;
use AnzuSystems\CommonBundle\Tests\Data\Model\ValueObject\DummyValueObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValueResolverControllerTest extends AbstractControllerTest
{
    public function testValueObjectConverter(): void
    {
        $uriFactory = static fn (string $value): string => sprintf('/dummy/value-resolver/value-object/%s', $value);

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

    public function testSerializerValueResolver(): void
    {
        $content = (new DummyDto())->setData('test');
        $response = $this->post(
            uri: '/dummy/value-resolver/serializer',
            content: $content,
            deserializationClass: DummyDto::class,
        );
        self::assertResponseIsSuccessful();
        self::assertSame($content->getData(), $response->getData());
    }

    public function testSerializerFail(): void
    {
        self::$client->request(
            Request::METHOD_POST,
            '/dummy/serializer/test',
            content: '{"dummyEnum":"state_invalid"}',
        );
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testApiFilterValueResolver(): void
    {
        $response = $this->get(
            uri: '/dummy/value-resolver/api-filter',
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

    public function testArrayStringValueResolver(): void
    {
        $arrayString = '1,2,3';
        $response = $this->get(
            uri: '/dummy/value-resolver/array-string/' . $arrayString,
        );
        self::assertResponseIsSuccessful();
        self::assertSame(
            expected: array_map('intval', explode(',', $arrayString)),
            actual: $response,
        );
    }
}
