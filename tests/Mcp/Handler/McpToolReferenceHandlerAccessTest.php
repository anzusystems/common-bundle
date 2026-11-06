<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Handler;

use AnzuSystems\CommonBundle\Mcp\Exception\McpToolAccessRejectedException;
use AnzuSystems\CommonBundle\Mcp\Exception\McpWriteRejectedException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Log\McpLogger;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolErrorResolver;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Result\CallToolResult;
use RuntimeException;

final class McpToolReferenceHandlerAccessTest extends AbstractMcpToolReferenceHandlerTestCase
{
    private const string READ_TOOL_METHOD = 'listExamples';
    private const string PREVIEWED_WRITE_TOOL_NAME = 'create_example';

    public function testToolWithoutAsMcpToolIsDeniedBecauseItHasNoPermission(): void
    {
        $handler = $this->createHandler(tool: new ExampleTool());
        $reference = new ToolReference(
            $this->createSdkTool(self::READ_TOOL_METHOD),
            [ExampleTool::class, self::READ_TOOL_METHOD],
        );

        $result = $handler->handle($reference, $this->createArguments(self::READ_TOOL_METHOD, []));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertSame(
            McpToolAccessRejectedException::createNotGranted(self::READ_TOOL_METHOD)->getMessage(),
            $result->structuredContent[McpToolReferenceHandler::ERROR_KEY],
        );
    }

    /**
     * @dataProvider deniedToolProvider
     */
    public function testDeniedToolIsReturnedAsToolErrorWithoutRunningTheTool(
        bool $toolGranted,
        bool $writeGranted,
        string $expectedError,
    ): void {
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static fn (): McpLogsByContextResponse => throw new RuntimeException('must not run'),
            toolGranted: $toolGranted,
            readOnlyTool: false,
            writeGranted: $writeGranted,
        );

        $result = $this->callToolWithoutInput($handler);

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
        self::assertSame(McpLogger::LEVEL_NAME_ERROR, $this->insertedDocuments[0]['levelName']);
    }

    /**
     * @return array<string, array{bool, bool, string}>
     */
    public static function deniedToolProvider(): array
    {
        return [
            'missing tool permission' => [
                false,
                true,
                McpToolAccessRejectedException::createNotGranted(ExampleWithoutInputTool::NAME)->getMessage(),
            ],
            'write tool not granted to the request' => [
                true,
                false,
                McpWriteRejectedException::createNotGranted(ExampleWithoutInputTool::NAME)->getMessage(),
            ],
        ];
    }

    /**
     * @dataProvider previewProvider
     */
    public function testPreviewRunsAsAReadToolOnlyWithItsWriteGrant(
        bool $writeGranted,
        int $expectedRuns,
        ?string $expectedError,
    ): void {
        $runs = 0;
        $handler = $this->createHandlerWithoutInputTool(
            toolHandler: static function () use (&$runs): McpLogsByContextResponse {
                ++$runs;

                return self::createResponse();
            },
            writeGranted: $writeGranted,
            previewOf: self::PREVIEWED_WRITE_TOOL_NAME,
        );

        $this->initAppReadOnlyMode(true);
        try {
            $result = $this->callToolWithoutInput($handler);
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertSame($expectedRuns, $runs);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY] ?? null);
    }

    /**
     * @return array<string, array{bool, int, string|null}>
     */
    public static function previewProvider(): array
    {
        return [
            'write grant runs the preview also in read-only mode' => [true, 1, null],
            'missing write grant denies the preview like a tool the token may not use' => [
                false,
                0,
                McpToolAccessRejectedException::createNotGranted(ExampleWithoutInputTool::NAME)->getMessage(),
            ],
        ];
    }

    /**
     * @dataProvider checkOrderProvider
     */
    public function testWriteGrantIsCheckedBeforeValidationAndValidationBeforeReadOnlyMode(
        bool $writeGranted,
        string $expectedError,
    ): void {
        $handler = $this->createHandler(
            tool: new ExampleSearchTool(static fn (): McpLogsByContextResponse => throw new RuntimeException('must not run')),
            readOnlyTool: false,
            writeGranted: $writeGranted,
        );

        $this->initAppReadOnlyMode(true);
        try {
            $result = $this->callTool(handler: $handler, toolClass: ExampleSearchTool::class, toolName: ExampleSearchTool::NAME, limit: self::LIMIT_INVALID);
        } finally {
            $this->initAppReadOnlyMode(false);
        }

        self::assertTrue($result->isError);
        self::assertSame($expectedError, $result->structuredContent[McpToolReferenceHandler::ERROR_KEY]);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function checkOrderProvider(): array
    {
        return [
            'write grant before validation' => [
                false,
                McpWriteRejectedException::createNotGranted(ExampleSearchTool::NAME)->getMessage(),
            ],
            'validation before read-only mode' => [
                true,
                McpToolErrorResolver::INVALID_ARGUMENTS_PREFIX . self::LIMIT_VIOLATION,
            ],
        ];
    }
}
