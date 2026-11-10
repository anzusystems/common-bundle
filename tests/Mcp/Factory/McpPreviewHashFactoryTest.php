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
    private const string OTHER_TITLE = 'Other';
    private const string SECRET = 'secret';
    private const string OTHER_SECRET = 'other';
    private const string FIRST_DATE = '2026-01-01T00:00:00+00:00';
    private const string SECOND_DATE = '2026-01-02T00:00:00+00:00';

    public function testHashOfThePreviewInputAndOfTheWriteInputIsTheSame(): void
    {
        $factory = $this->createFactory();
        $writeInput = new ExampleWriteRequest();
        $writeInput->title = self::TITLE;
        $writeInput->previewHash = 'sent by the client';

        self::assertSame(
            $factory->create(writeToolName: self::TOOL, input: $this->createPreviewInput(self::TITLE), currentState: null),
            $factory->create(writeToolName: self::TOOL, input: $writeInput, currentState: null),
        );
    }

    /**
     * @dataProvider hashComponentProvider
     */
    public function testEachComponentChangesTheHash(string $tool, string $title, ?string $stateTitle, string $secret): void
    {
        $hash = $this->createFactory()
            ->create(writeToolName: self::TOOL, input: $this->createPreviewInput(self::TITLE), currentState: null);

        $changedHash = new McpPreviewHashFactory(McpSerializerFactory::create(), $secret)->create(
            writeToolName: $tool,
            input: $this->createPreviewInput($title),
            currentState: is_string($stateTitle) ? $this->createPreviewInput($stateTitle) : null,
        );

        self::assertNotSame($hash, $changedHash);
    }

    /**
     * @return array<string, array{string, string, ?string, string}>
     */
    public static function hashComponentProvider(): array
    {
        return [
            'tool' => [self::OTHER_TOOL, self::TITLE, null, self::SECRET],
            'input' => [self::TOOL, self::OTHER_TITLE, null, self::SECRET],
            'state' => [self::TOOL, self::TITLE, self::TITLE, self::SECRET],
            'secret' => [self::TOOL, self::TITLE, null, self::OTHER_SECRET],
        ];
    }

    public function testHashMatchesThePreviewHashPattern(): void
    {
        $hash = $this->createFactory()
            ->create(writeToolName: self::TOOL, input: $this->createPreviewInput(self::TITLE), currentState: null);

        self::assertMatchesRegularExpression(McpPreviewHashFactory::PREVIEW_HASH_PATTERN, $hash);
    }

    public function testVolatileFieldsDoNotChangeTheStateHashAtAnyDepth(): void
    {
        $factory = $this->createFactory();

        self::assertSame(
            $factory->createStateHash(new ExampleState(id: 1, modifiedAt: self::FIRST_DATE, item: new ExampleStateItem(1, self::TITLE))),
            $factory->createStateHash(new ExampleState(id: 2, modifiedAt: self::SECOND_DATE, item: new ExampleStateItem(null, self::TITLE))),
        );
        self::assertNotSame(
            $factory->createStateHash(new ExampleState(id: 1, modifiedAt: self::FIRST_DATE, item: new ExampleStateItem(1, self::TITLE))),
            $factory->createStateHash(new ExampleState(id: 1, modifiedAt: self::FIRST_DATE, item: new ExampleStateItem(1, self::OTHER_TITLE))),
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
