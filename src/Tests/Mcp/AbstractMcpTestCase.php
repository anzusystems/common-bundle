<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolArgumentsInputException;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Tests\AnzuWebTestCase;
use AnzuSystems\CommonBundle\Validator\Constraints\McpExistingId;

abstract class AbstractMcpTestCase extends AnzuWebTestCase
{
    /**
     * The mcp/sdk schema validator words these errors and has no constants for them.
     */
    private const string ABOVE_MAXIMUM_FORMAT = "Property '/%s': Number must be less than or equal to %d";
    private const string TOO_MANY_ITEMS_FORMAT = "Property '/%s': Array should have at most %d items, %d found";

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

    protected static function assertToolArgumentAboveMaximum(string $field, int $maximum, McpToolResult $result): void
    {
        self::assertTrue($result->isError);
        self::assertStringContainsString(sprintf(self::ABOVE_MAXIMUM_FORMAT, $field, $maximum), (string) $result->error);
    }

    /**
     * The call sent one item more than the maximum.
     */
    protected static function assertToolArgumentHasTooManyItems(string $field, int $maximum, McpToolResult $result): void
    {
        self::assertTrue($result->isError);
        self::assertStringContainsString(self::createTooManyItemsError($field, $maximum), (string) $result->error);
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

    protected static function assertToolWarning(string $expectedWarning, McpToolResult $result): void
    {
        self::assertContains($expectedWarning, $result->data['warnings'] ?? []);
    }

    protected static function assertToolNextStep(string $expectedNextStep, McpToolResult $result): void
    {
        self::assertContains($expectedNextStep, $result->data['nextSteps'] ?? []);
    }

    protected static function createInvalidArgumentError(string $field, string $message): string
    {
        return McpToolArgumentsInputException::createInvalidArgument(propertyPath: $field, message: $message)->getMessage();
    }

    protected static function createTooManyItemsError(string $field, int $maximum): string
    {
        return sprintf(self::TOO_MANY_ITEMS_FORMAT, $field, $maximum, $maximum + 1);
    }

    protected static function createUnknownIdError(string $field, int $id, string $resolverTool): string
    {
        return self::createInvalidArgumentError($field, strtr(McpExistingId::MESSAGE, [
            McpExistingId::ID_PLACEHOLDER => (string) $id,
            McpExistingId::RESOLVER_TOOL_PLACEHOLDER => $resolverTool,
        ]));
    }

    /**
     * @param list<int> $ids
     */
    protected static function createUnknownIdsError(string $field, array $ids, string $resolverTool): string
    {
        return self::createInvalidArgumentError($field, strtr(McpExistingId::LIST_MESSAGE, [
            McpExistingId::IDS_PLACEHOLDER => implode(McpExistingId::ID_SEPARATOR, array_map(static fn (int $id): string => sprintf(McpExistingId::ID_FORMAT, $id), $ids)),
            McpExistingId::RESOLVER_TOOL_PLACEHOLDER => $resolverTool,
        ]));
    }
}
