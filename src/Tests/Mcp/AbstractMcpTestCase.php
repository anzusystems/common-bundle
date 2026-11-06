<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Tests\AnzuWebTestCase;
use AnzuSystems\CommonBundle\Validator\Constraints\McpExistingId;

abstract class AbstractMcpTestCase extends AnzuWebTestCase
{
    private const string VIOLATION_FORMAT = '%s%s: %s';
    private const string ID_FORMAT = '"%d"';
    private const string ID_SEPARATOR = ', ';
    private const string ID_PLACEHOLDER = '{{ id }}';
    private const string IDS_PLACEHOLDER = '{{ ids }}';

    /**
     * @var array<string, McpTestClient>
     */
    private array $mcpClients = [];

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     *
     * @return string the plain token the endpoint authenticates
     */
    abstract protected function createMcpToken(int $userId, array $writeTools, ?array $readTools): string;

    abstract protected function getMcpHost(): string;

    abstract protected function getMcpPath(): string;

    /**
     * @param list<string> $writeTools
     * @param list<string>|null $readTools
     */
    protected function getMcpClient(int $userId, array $writeTools = [], ?array $readTools = null): McpTestClient
    {
        $key = json_encode([$userId, $writeTools, $readTools], JSON_THROW_ON_ERROR);

        return $this->mcpClients[$key] ??= new McpTestClient(
            browser: static::$client,
            path: $this->getMcpPath(),
            host: $this->getMcpHost(),
            token: $this->createMcpToken(userId: $userId, writeTools: $writeTools, readTools: $readTools),
        );
    }

    protected static function assertToolSucceeded(McpToolResult $result): void
    {
        self::assertFalse($result->isError, (string) $result->error);
    }

    protected static function assertToolError(string $expectedError, McpToolResult $result): void
    {
        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->error);
    }

    protected static function assertToolAccessDenied(McpToolResult $result): void
    {
        self::assertToolError(McpToolErrorResolver::ACCESS_DENIED_MESSAGE, $result);
    }

    protected static function assertToolInvalidArgument(string $field, string $expectedMessage, McpToolResult $result): void
    {
        self::assertToolError(self::createInvalidArgumentError($field, $expectedMessage), $result);
    }

    protected static function assertToolUnknownId(string $field, int $id, string $resolverTool, McpToolResult $result): void
    {
        self::assertToolError(self::createUnknownIdError(field: $field, id: $id, resolverTool: $resolverTool), $result);
    }

    /**
     * @param list<int> $ids
     */
    protected static function assertToolUnknownIds(string $field, array $ids, string $resolverTool, McpToolResult $result): void
    {
        self::assertToolError(self::createUnknownIdsError(field: $field, ids: $ids, resolverTool: $resolverTool), $result);
    }

    protected static function createInvalidArgumentError(string $field, string $message): string
    {
        return sprintf(self::VIOLATION_FORMAT, McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX, $field, $message);
    }

    protected static function createUnknownIdError(string $field, int $id, string $resolverTool): string
    {
        return self::createInvalidArgumentError($field, strtr(McpExistingId::MESSAGE, [
            self::ID_PLACEHOLDER => (string) $id,
            McpExistingId::RESOLVER_TOOL_PLACEHOLDER => $resolverTool,
        ]));
    }

    /**
     * @param list<int> $ids
     */
    protected static function createUnknownIdsError(string $field, array $ids, string $resolverTool): string
    {
        return self::createInvalidArgumentError($field, strtr(McpExistingId::LIST_MESSAGE, [
            self::IDS_PLACEHOLDER => implode(self::ID_SEPARATOR, array_map(static fn (int $id): string => sprintf(self::ID_FORMAT, $id), $ids)),
            McpExistingId::RESOLVER_TOOL_PLACEHOLDER => $resolverTool,
        ]));
    }
}
