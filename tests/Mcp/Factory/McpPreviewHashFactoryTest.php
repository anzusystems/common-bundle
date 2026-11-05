<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Factory;

use AnzuSystems\CommonBundle\Mcp\Factory\McpPreviewHashFactory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\ExampleState;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\ExampleStateItem;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWritePreviewRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExampleWriteRequest;
use AnzuSystems\CommonBundle\Tests\Mcp\McpSerializerFactory;
use PHPUnit\Framework\TestCase;

final class McpPreviewHashFactoryTest extends TestCase
{
    private const string TOOL = 'example_write';
    private const string OTHER_TOOL = 'other_write';
    private const string TITLE = 'Title';
    private const string SECRET = 'secret';
    private const string FIRST_DATE = '2026-01-01T00:00:00+00:00';
    private const string SECOND_DATE = '2026-01-02T00:00:00+00:00';

    public function testHashOfThePreviewInputAndOfTheWriteInputIsTheSame(): void
    {
        $factory = $this->createFactory();
        $writeInput = new ExampleWriteRequest();
        $writeInput->title = self::TITLE;
        $writeInput->previewHash = 'sent by the client';

        self::assertSame(
            $factory->create(self::TOOL, $this->createPreviewInput(self::TITLE), null),
            $factory->create(self::TOOL, $writeInput, null),
        );
    }

    public function testToolArgumentsAndStateAndSecretChangeTheHash(): void
    {
        $factory = $this->createFactory();
        $input = $this->createPreviewInput(self::TITLE);
        $hash = $factory->create(self::TOOL, $input, null);

        self::assertNotSame($hash, $factory->create(self::OTHER_TOOL, $input, null));
        self::assertNotSame($hash, $factory->create(self::TOOL, $this->createPreviewInput('Other'), null));
        self::assertNotSame($hash, $factory->create(self::TOOL, $input, $this->createPreviewInput(self::TITLE)));
        self::assertNotSame($hash, new McpPreviewHashFactory(McpSerializerFactory::create(), 'other')->create(self::TOOL, $input, null));
        self::assertMatchesRegularExpression(McpPreviewHashFactory::PREVIEW_HASH_PATTERN, $hash);
    }

    public function testVolatileFieldsDoNotChangeTheStateHashAtAnyDepth(): void
    {
        $factory = $this->createFactory();

        self::assertSame(
            $factory->createStateHash(new ExampleState(1, self::FIRST_DATE, new ExampleStateItem(1, self::TITLE))),
            $factory->createStateHash(new ExampleState(2, self::SECOND_DATE, new ExampleStateItem(null, self::TITLE))),
        );
        self::assertNotSame(
            $factory->createStateHash(new ExampleState(1, self::FIRST_DATE, new ExampleStateItem(1, self::TITLE))),
            $factory->createStateHash(new ExampleState(1, self::FIRST_DATE, new ExampleStateItem(1, 'Other'))),
        );
    }

    private function createFactory(): McpPreviewHashFactory
    {
        return new McpPreviewHashFactory(McpSerializerFactory::create(), self::SECRET);
    }

    private function createPreviewInput(string $title): ExampleWritePreviewRequest
    {
        $input = new ExampleWritePreviewRequest();
        $input->title = $title;

        return $input;
    }
}
