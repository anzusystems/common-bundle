<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Serializer;

use AnzuSystems\CommonBundle\Serializer\Service\BsonConverter;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use AnzuSystems\CommonBundle\Tests\Data\Model\DataObject\BsonDocumentDto;
use MongoDB\Model\BSONDocument;

final class BsonConverterTest extends AnzuKernelTestCase
{
    public function testFillsPropertiesWithAndWithoutSetterMethod(): void
    {
        /** @var BsonConverter $bsonConverter */
        $bsonConverter = self::getContainer()->get(BsonConverter::class);

        $document = $bsonConverter->bsonToObject(
            new BSONDocument([
                'publicProperty' => 'a',
                'privateSetProperty' => 'b',
                'withSetter' => 'c',
            ]),
            BsonDocumentDto::class,
        );

        self::assertSame('a', $document->publicProperty);
        self::assertSame('b', $document->privateSetProperty);
        self::assertSame('C', $document->getWithSetter());
    }
}
