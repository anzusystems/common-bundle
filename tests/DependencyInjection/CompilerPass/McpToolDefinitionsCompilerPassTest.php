<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpToolDefinitionsCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\McpToolDefinitions;
use AnzuSystems\CommonBundle\Mcp\McpWriteRateLimiter;
use AnzuSystems\CommonBundle\Mcp\Model\McpToolSignature;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpAppLogSearchResponse;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAppLogsTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Model\Request\ExtraFieldExampleWriteRequest;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ConflictingExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePermissionTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleToolCategory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWritePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWriteTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExplicitGrantExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExtraFieldExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExtraFieldExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\InvalidCategoryExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\InvalidNameExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\NoHashInputExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\NoHashInputExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\NoInputExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\OtherCategoryExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\OtherInputExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\PermissionExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\PlainWriteExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\PlainWriteExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\RateLimitedExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\RateLimitedExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\RateLimitedReadExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ReadToolPreviewExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ResultExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\SecondExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\WithoutInputWriteExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\WritablePreviewExampleTool;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpToolDefinitionsCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'example';
    private const string PERMISSION_ARGUMENT = '$permission';
    private const string READ_ONLY_ARGUMENT = '$readOnly';
    private const string CATEGORY_ARGUMENT = '$category';
    private const string PREVIEW_OF_ARGUMENT = '$previewOf';
    private const string PREVIEW_TOOL_ARGUMENT = '$previewTool';
    private const string RATE_LIMITER_ARGUMENT = '$rateLimiter';
    private const string SIGNATURE_ARGUMENT = '$signature';
    private const string EXPLICIT_GRANT_ARGUMENT = '$explicitGrant';
    private const string TOOL_PERMISSION = 'app_log_read';
    private const string RATE_LIMITER_ID = 'limiter.example_write';
    private const string TOOL_TAG_METHOD = 'method';
    private const string READ_TOOL_METHOD = 'listExamples';
    private const string TOOL_METHOD_SEPARATOR = '::';
    private const string WRITE_TOOL_METHOD = 'createExample';

    public function testCollectsOnlyReadOnlyToolNamesForTheAccessChecker(): void
    {
        $container = $this->createMcpContainer();
        $container->register(SearchAppLogsTool::class)
            ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG);
        $container->register(ExampleTool::class)
            ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD])
            ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::WRITE_TOOL_METHOD]);

        new McpToolDefinitionsCompilerPass()
            ->process($container);

        self::assertSame(
            [SearchAppLogsTool::NAME, self::READ_TOOL_METHOD],
            array_filter($this->getDefinitionField($container, self::READ_ONLY_ARGUMENT)) |> array_keys(...),
        );
    }

    public function testCollectsToolCategoriesAndPreviewsForTheAccessChecker(): void
    {
        $container = $this->createMcpContainer();
        $this->registerTools($container, [
            SearchAppLogsTool::class,
            ExampleWriteTool::class,
            ExampleWritePreviewTool::class,
        ]);

        new McpToolDefinitionsCompilerPass()
            ->process($container);

        self::assertSame(
            [
                SearchAppLogsTool::NAME => AsMcpTool::CATEGORY_DIAGNOSTICS,
                ExampleWriteTool::NAME => ExampleToolCategory::EXAMPLES,
                ExampleWritePreviewTool::NAME => ExampleToolCategory::EXAMPLES,
            ],
            $this->getDefinitionField($container, self::CATEGORY_ARGUMENT),
        );
        self::assertSame(
            [
                SearchAppLogsTool::NAME => null,
                ExampleWriteTool::NAME => null,
                ExampleWritePreviewTool::NAME => ExampleWriteTool::NAME,
            ],
            $this->getDefinitionField($container, self::PREVIEW_OF_ARGUMENT),
        );
        self::assertSame(
            [
                SearchAppLogsTool::NAME => null,
                ExampleWriteTool::NAME => ExampleWritePreviewTool::NAME,
                ExampleWritePreviewTool::NAME => null,
            ],
            $this->getDefinitionField($container, self::PREVIEW_TOOL_ARGUMENT),
        );
        self::assertSame(
            [SearchAppLogsTool::NAME, ExampleWritePreviewTool::NAME],
            array_filter($this->getDefinitionField($container, self::READ_ONLY_ARGUMENT)) |> array_keys(...),
        );
    }

    public function testToolPermissionsComeFromTheAttributesAndTheLogToolsParameter(): void
    {
        $container = $this->createMcpContainer();
        $this->registerTools($container, [SearchAppLogsTool::class, ExamplePermissionTool::class]);

        new McpToolDefinitionsCompilerPass()
            ->process($container);

        self::assertSame(
            [
                SearchAppLogsTool::NAME => self::TOOL_PERMISSION,
                ExamplePermissionTool::NAME => ExamplePermissionTool::PERMISSION,
            ],
            $this->getDefinitionField($container, self::PERMISSION_ARGUMENT),
        );
    }

    public function testExplicitGrantReachesItsDefinition(): void
    {
        $container = $this->createMcpContainer();
        $this->registerTools($container, [ExamplePermissionTool::class, ExplicitGrantExampleTool::class]);

        new McpToolDefinitionsCompilerPass()
            ->process($container);

        self::assertSame(
            [ExamplePermissionTool::NAME => false, ExplicitGrantExampleTool::NAME => true],
            $this->getDefinitionField($container, self::EXPLICIT_GRANT_ARGUMENT),
        );
    }

    public function testSignatureOfAnAsMcpToolReachesItsDefinition(): void
    {
        $container = $this->createMcpContainer();
        $this->registerTools($container, [SearchAppLogsTool::class]);

        new McpToolDefinitionsCompilerPass()
            ->process($container);

        $signature = $this->getDefinitionField($container, self::SIGNATURE_ARGUMENT)[SearchAppLogsTool::NAME];
        self::assertInstanceOf(Definition::class, $signature);
        self::assertSame(McpToolSignature::class, $signature->getClass());
        self::assertSame(
            [
                '$inputParameterName' => 'request',
                '$inputClass' => SearchAppLogsRequest::class,
                '$outputClass' => McpAppLogSearchResponse::class,
            ],
            $signature->getArguments(),
        );
    }

    public function testPlainMcpToolHasNoSignature(): void
    {
        $container = $this->createMcpContainer();
        $container->register(ExampleTool::class)
            ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);

        new McpToolDefinitionsCompilerPass()
            ->process($container);

        self::assertNull($this->getDefinitionField($container, self::SIGNATURE_ARGUMENT)[self::READ_TOOL_METHOD]);
    }

    public function testToolRateLimitersReachTheirDefinitions(): void
    {
        $container = $this->createMcpContainer();
        $container->register(self::RATE_LIMITER_ID);
        $this->registerTools($container, [RateLimitedExampleTool::class, RateLimitedExamplePreviewTool::class]);

        new McpToolDefinitionsCompilerPass()
            ->process($container);

        self::assertSame(
            RateLimitedExampleTool::RATE_LIMITER,
            $this->getDefinitionField($container, self::RATE_LIMITER_ARGUMENT)[RateLimitedExampleTool::NAME],
        );
        $locator = $container->getDefinition(McpWriteRateLimiter::class)
            ->getArgument('$rateLimiters');
        self::assertInstanceOf(Reference::class, $locator);
        self::assertSame(
            [RateLimitedExampleTool::RATE_LIMITER],
            $container->getDefinition((string) $locator)
                ->getArgument(0) |> array_keys(...),
        );
    }

    /**
     * @dataProvider invalidToolMetadataProvider
     *
     * @param list<class-string> $toolClasses
     */
    public function testInvalidToolMetadataFailsAtCompileTime(
        array $toolClasses,
        string $expectedMessage,
    ): void {
        $container = $this->createMcpContainer();
        $this->registerTools($container, $toolClasses);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        new McpToolDefinitionsCompilerPass()
            ->process($container);
    }

    /**
     * @return array<string, array{list<class-string>, string}>
     */
    public static function invalidToolMetadataProvider(): array
    {
        return [
            'category that is not camelCase' => [
                [InvalidCategoryExampleTool::class],
                McpConfigurationException::createInvalidCategory(category: InvalidCategoryExampleTool::CATEGORY, toolName: InvalidCategoryExampleTool::NAME, pattern: McpToolDefinitionsCompilerPass::CATEGORY_PATTERN)->getMessage(),
            ],
            'preview of an unknown tool' => [
                [ExamplePreviewTool::class],
                McpConfigurationException::createPreviewOfUnknownTool(ExamplePreviewTool::NAME, ExampleWithoutInputTool::NAME)->getMessage(),
            ],
            'preview of a read tool' => [
                [ExampleSearchTool::class, ReadToolPreviewExampleTool::class],
                McpConfigurationException::createPreviewOfReadTool(ReadToolPreviewExampleTool::NAME, ExampleSearchTool::NAME)->getMessage(),
            ],
            'preview of a plain McpTool without readOnlyHint' => [
                [PlainWriteExampleTool::class, PlainWriteExamplePreviewTool::class],
                McpConfigurationException::createPreviewOfReadTool(PlainWriteExamplePreviewTool::NAME, PlainWriteExampleTool::NAME)->getMessage(),
            ],
            'preview that is not read only' => [
                [ExampleWithoutInputTool::class, WritablePreviewExampleTool::class],
                McpConfigurationException::createWritablePreview(WritablePreviewExampleTool::NAME, ExampleWithoutInputTool::NAME)->getMessage(),
            ],
            'second preview of one write tool' => [
                [ExampleWithoutInputTool::class, ExamplePreviewTool::class, SecondExamplePreviewTool::class],
                McpConfigurationException::createSecondPreview(
                    ExampleWithoutInputTool::NAME,
                    [ExamplePreviewTool::NAME, SecondExamplePreviewTool::NAME]
                )->getMessage(),
            ],
            'preview with its own permission' => [
                [ExampleWithoutInputTool::class, PermissionExamplePreviewTool::class],
                McpConfigurationException::createPreviewWithPermission(PermissionExamplePreviewTool::NAME, ExampleWithoutInputTool::NAME)->getMessage(),
            ],
            'tool without a permission' => [
                [ExampleSearchTool::class],
                McpConfigurationException::createToolWithoutPermission(ExampleSearchTool::NAME)->getMessage(),
            ],
            'unknown rate limiter' => [
                [RateLimitedExampleTool::class, RateLimitedExamplePreviewTool::class],
                McpConfigurationException::createUnknownRateLimiter(RateLimitedExampleTool::NAME, RateLimitedExampleTool::RATE_LIMITER)->getMessage(),
            ],
            'rate limiter of a read tool' => [
                [RateLimitedReadExampleTool::class],
                McpConfigurationException::createReadToolRateLimiter(RateLimitedReadExampleTool::NAME)->getMessage(),
            ],
            'preview in another category than its write tool' => [
                [ExampleWithoutInputTool::class, OtherCategoryExamplePreviewTool::class],
                McpConfigurationException::createPreviewInOtherCategory(
                    previewToolName: OtherCategoryExamplePreviewTool::NAME,
                    writeToolName: ExampleWithoutInputTool::NAME,
                    category: ExampleToolCategory::EXAMPLES
                )->getMessage(),
            ],
            'write tool without a preview' => [
                [ExampleWriteTool::class],
                McpConfigurationException::createWriteToolWithoutPreview(ExampleWriteTool::NAME)->getMessage(),
            ],
            'preview response without the confirmation message' => [
                [ExampleWithoutInputTool::class, ExamplePreviewTool::class],
                McpConfigurationException::createPreviewResponseWithoutConfirmation(
                    ExamplePreviewTool::NAME,
                    ExampleWithoutInputTool::NAME
                )->getMessage(),
            ],
            'preview response that is a CallToolResult' => [
                [ExampleWriteTool::class, ResultExamplePreviewTool::class],
                McpConfigurationException::createPreviewResponseWithoutConfirmation(
                    ResultExamplePreviewTool::NAME,
                    ExampleWriteTool::NAME
                )->getMessage(),
            ],
            'preview without an input' => [
                [ExampleWriteTool::class, NoInputExamplePreviewTool::class],
                McpConfigurationException::createWriteInputNotPreviewInput(
                    ExampleWriteTool::NAME,
                    NoInputExamplePreviewTool::NAME
                )->getMessage(),
            ],
            'write tool without an input' => [
                [ExampleWithoutInputTool::class, WithoutInputWriteExamplePreviewTool::class],
                McpConfigurationException::createWriteInputNotPreviewInput(
                    ExampleWithoutInputTool::NAME,
                    WithoutInputWriteExamplePreviewTool::NAME
                )->getMessage(),
            ],
            'write input without the preview hash' => [
                [NoHashInputExampleTool::class, NoHashInputExamplePreviewTool::class],
                McpConfigurationException::createWriteInputNotPreviewInput(
                    NoHashInputExampleTool::NAME,
                    NoHashInputExamplePreviewTool::NAME
                )->getMessage(),
            ],
            'write input that does not extend the preview input' => [
                [ExampleWriteTool::class, OtherInputExamplePreviewTool::class],
                McpConfigurationException::createWriteInputNotPreviewInput(
                    ExampleWriteTool::NAME,
                    OtherInputExamplePreviewTool::NAME
                )->getMessage(),
            ],
            'write input with a field its preview input does not have' => [
                [ExtraFieldExampleTool::class, ExtraFieldExamplePreviewTool::class],
                McpConfigurationException::createWriteInputWithExtraFields(
                    toolName: ExtraFieldExampleTool::NAME,
                    previewToolName: ExtraFieldExamplePreviewTool::NAME,
                    fields: [ExtraFieldExampleWriteRequest::EXTRA_FIELD],
                )->getMessage(),
            ],
        ];
    }

    public function testToolNameRegisteredByTwoToolsFailsAtCompileTime(): void
    {
        $container = $this->createMcpContainer();
        $container->register(ExampleTool::class)
            ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);
        $container->register(ConflictingExampleTool::class)
            ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);

        $this->expectExceptionObject(McpConfigurationException::createDuplicateToolName(
            toolName: self::READ_TOOL_METHOD,
            toolMethods: [ExampleTool::class . self::TOOL_METHOD_SEPARATOR . self::READ_TOOL_METHOD, ConflictingExampleTool::class . self::TOOL_METHOD_SEPARATOR . self::READ_TOOL_METHOD],
        ));

        new McpToolDefinitionsCompilerPass()
            ->process($container);
    }

    public function testInvalidToolNameFailsAtCompileTime(): void
    {
        $container = $this->createMcpContainer();
        $container->register(InvalidNameExampleTool::class)
            ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);

        $this->expectExceptionObject(McpConfigurationException::createInvalidToolName(
            toolName: InvalidNameExampleTool::NAME,
            toolMethods: [InvalidNameExampleTool::class . self::TOOL_METHOD_SEPARATOR . self::READ_TOOL_METHOD],
        ));

        new McpToolDefinitionsCompilerPass()
            ->process($container);
    }

    /**
     * @param list<class-string> $toolClasses
     */
    private function registerTools(ContainerBuilder $container, array $toolClasses): void
    {
        foreach ($toolClasses as $toolClass) {
            $container->register($toolClass)
                ->addTag(McpToolDefinitionsCompilerPass::TOOL_TAG);
        }
    }

    /**
     * @return array<string, mixed> tool name => the field of its definition
     */
    private function getDefinitionField(ContainerBuilder $container, string $argument): array
    {
        return array_map(
            static fn (Definition $definition): mixed => $definition->getArgument($argument),
            $container->getDefinition(McpToolDefinitions::class)->getArgument(McpToolDefinitionsCompilerPass::DEFINITIONS_ARGUMENT),
        );
    }

    private function createMcpContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $container->setParameter(AsMcpTool::LOG_TOOLS_PERMISSION_PARAM, self::TOOL_PERMISSION);
        $container->register(McpController::class);
        $container->register(McpWriteRateLimiter::class)
            ->setArgument('$rateLimiters', null);
        $container->register(McpToolDefinitions::class)
            ->setArgument(McpToolDefinitionsCompilerPass::DEFINITIONS_ARGUMENT, []);

        return $container;
    }
}
