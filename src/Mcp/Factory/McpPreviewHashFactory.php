<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Mcp\Factory;

use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use JsonException;

/**
 * Signs the arguments and the current state with the app secret, so only a preview call can produce a hash that the
 * write accepts. The volatile fields stay out of the state: saving a record unchanged invalidates no preview.
 */
final readonly class McpPreviewHashFactory
{
    public const string PREVIEW_HASH_PATTERN = '/^[0-9a-f]{64}$/D';
    public const array VOLATILE_FIELDS = ['id', 'adminUrl', 'createdAt', 'modifiedAt'];

    private const string ALGORITHM = 'sha256';
    private const string FIELD_PREVIEW_HASH = 'previewHash';
    private const string KEY_TOOL = 'tool';
    private const string KEY_ARGUMENTS = 'arguments';
    private const string KEY_CURRENT_STATE_HASH = 'currentStateHash';

    public function __construct(
        private Serializer $serializer,
        private string $secret,
    ) {
    }

    /**
     * @throws SerializerException
     * @throws JsonException
     */
    public function create(string $writeToolName, object $input, ?object $currentState): string
    {
        /** @var array<string, mixed> $arguments */
        $arguments = $this->serializer->toArray($input);
        unset($arguments[self::FIELD_PREVIEW_HASH]);

        return [
            self::KEY_TOOL => $writeToolName,
            self::KEY_ARGUMENTS => $this->sortRecursively($arguments),
            self::KEY_CURRENT_STATE_HASH => is_object($currentState) ? $this->createStateHash($currentState) : null,
        ]
            |> (static fn (array $canonical): string => json_encode($canonical, JSON_THROW_ON_ERROR))
            |> (fn (string $json): string => hash_hmac(algo: self::ALGORITHM, data: $json, key: $this->secret));
    }

    /**
     * @throws SerializerException
     * @throws JsonException
     */
    public function createStateHash(object $state): string
    {
        /** @var array<array-key, mixed> $content */
        $content = $this->serializer->toArray($state);

        return $content
            |> $this->dropVolatileFields(...)
            |> $this->sortRecursively(...)
            |> (static fn (array $canonical): string => json_encode($canonical, JSON_THROW_ON_ERROR))
            |> (static fn (string $json): string => hash(self::ALGORITHM, $json));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function dropVolatileFields(array $data): array
    {
        $kept = array_diff_key($data, array_flip(self::VOLATILE_FIELDS));

        return array_map(
            fn (mixed $value): mixed => is_array($value) ? $this->dropVolatileFields($value) : $value,
            $kept,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function sortRecursively(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortRecursively($value);
            }
        }
        if (array_is_list($data)) {
            return $data;
        }
        ksort($data);

        return $data;
    }
}
