<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Model\Decorator;

use AnzuSystems\CommonBundle\Helper\StringHelper;
use AnzuSystems\SerializerBundle\Attributes\Serialize;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;

abstract class AbstractMcpLogDecorator
{
    public const int FIELD_TRUNCATE_LENGTH = 2_000;
    public const string TRUNCATED_SUFFIX = '…(truncated)';

    private const string FIELD_DATETIME = 'datetime';
    private const string FIELD_CONTEXT = 'context';

    #[Serialize]
    public ?string $datetime {
        get {
            $datetime = $this->document[self::FIELD_DATETIME] ?? null;

            return $datetime instanceof UTCDateTime
                ? $datetime->toDateTime()
                    ->format(DateTimeInterface::ATOM)
                : null;
        }
    }

    /**
     * @var array<array-key, mixed>
     */
    protected array $context {
        get => $this->readArray($this->document, self::FIELD_CONTEXT);
    }

    /**
     * @param array<string, mixed> $document
     */
    public function __construct(
        protected readonly array $document,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function readString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && StringHelper::isNotEmpty($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function readInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected function readArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function readTruncated(array $data, string $key): ?string
    {
        $value = $this->readString($data, $key);
        if (null === $value || mb_strlen($value) <= self::FIELD_TRUNCATE_LENGTH) {
            return $value;
        }

        return mb_substr(string: $value, start: 0, length: self::FIELD_TRUNCATE_LENGTH) . self::TRUNCATED_SUFFIX;
    }
}
