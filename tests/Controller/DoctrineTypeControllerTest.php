<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Controller;

use AnzuSystems\CommonBundle\Tests\Data\Entity\Example;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

final class DoctrineTypeControllerTest extends AbstractControllerTest
{
    /**
     * @throws JsonException
     */
    public function testEnumType(): void
    {
        self::$client->request(
            method: Request::METHOD_GET,
            uri: sprintf('/dummy/doctrine/type/enum/%d', Example::EXAMPLE_INSTANCE_ID)
        );
        self::assertResponseIsSuccessful();
        $example = $this->deserializeResponse(Example::class);
        self::assertSame(Example::EXAMPLE_INSTANCE_ID, $example->getId());
        self::assertSame('test', $example->getName());
        self::assertSame(DummyEnum::StateThree->toString(), $example->getDummyEnum()->toString());
    }
}
