<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Monolog;

use AnzuSystems\CommonBundle\Monolog\LogContextContentProcessor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class LogContextContentProcessorTest extends TestCase
{
    private LogContextContentProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new LogContextContentProcessor();
    }

    public function testDecodesJsonContentIntoArray(): void
    {
        $record = $this->createRecord([
            'appSystem' => 'cms',
            'content' => '{"taskId":123,"executionId":45,"detail":"missing_block_ids"}',
        ]);

        $processed = ($this->processor)($record);

        self::assertSame(
            [
                'taskId' => 123,
                'executionId' => 45,
                'detail' => 'missing_block_ids',
            ],
            $processed->context['content'],
        );
        self::assertSame('cms', $processed->context['appSystem']);
        // the original record stays untouched — only the handler pipeline sees the decoded copy
        self::assertSame('{"taskId":123,"executionId":45,"detail":"missing_block_ids"}', $record->context['content']);
    }

    public function testKeepsRecordWhenContentIsNotDecodableToArray(): void
    {
        $emptyContent = $this->createRecord(['content' => '']);
        $invalidJson = $this->createRecord(['content' => 'not a json']);
        $scalarJson = $this->createRecord(['content' => '"just a string"']);
        $noContent = $this->createRecord(['foo' => 'bar']);
        $arrayContent = $this->createRecord(['content' => ['already' => 'decoded']]);

        self::assertSame($emptyContent, ($this->processor)($emptyContent));
        self::assertSame($invalidJson, ($this->processor)($invalidJson));
        self::assertSame($scalarJson, ($this->processor)($scalarJson));
        self::assertSame($noContent, ($this->processor)($noContent));
        self::assertSame($arrayContent, ($this->processor)($arrayContent));
    }

    private function createRecord(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'journal',
            level: Level::Error,
            message: '[RavenAi] Translate result cannot be applied',
            context: $context,
        );
    }
}
