<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAppLogsTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ConflictingExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleErrorResultTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleToolCategory;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\InvalidCategoryExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\InvalidNameExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\OtherCategoryExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ReadToolPreviewExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\SecondExamplePreviewTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\WritablePreviewExampleTool;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class McpCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'core_cms';
    private const string CONTROLLER_ID = 'mcp.server.core_cms.controller';
    private const string READ_ONLY_TOOL_NAMES_ARGUMENT = '$readOnlyToolNames';
    private const string TOOL_CATEGORIES_ARGUMENT = '$toolCategories';
    private const string WRITE_TOOL_NAMES_BY_PREVIEW_ARGUMENT = '$writeToolNamesByPreview';
    private const string TOOL_PERMISSIONS_ARGUMENT = '$toolPermissions';
    private const string TOOL_PERMISSION = 'cms_example_read';
    private const string UNKNOWN_TOOL_NAME = 'unknown_tool';
    private const string TOOL_TAG_METHOD = 'method';
    private const string READ_TOOL_METHOD = 'listExamples';
    private const string WRITE_TOOL_METHOD = 'createExample';

    public function testDoesNothingWhenMcpIsDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->register(self::CONTROLLER_ID, stdClass::class);

        new McpCompilerPass()
            ->process($container);

        self::assertFalse($container->hasAlias(self::CONTROLLER_ID));
        self::assertSame(stdClass::class, $container->getDefinition(self::CONTROLLER_ID)->getClass());
    }

    public function testOverridesTheMcpBundleControllerOfTheConfiguredServer(): void
    {
        $container = $this->createMcpContainer();
        $container->register(self::CONTROLLER_ID, stdClass::class);

        new McpCompilerPass()
            ->process($container);

        self::assertTrue($container->hasAlias(self::CONTROLLER_ID));
        self::assertSame(McpController::class, (string) $container->getAlias(self::CONTROLLER_ID));
        self::assertTrue($container->getAlias(self::CONTROLLER_ID)->isPublic());
    }

    public function testCollectsOnlyReadOnlyToolNamesForTheAccessChecker(): void
    {
        $container = $this->createMcpContainer([
            SearchAppLogsTool::NAME => self::TOOL_PERMISSION,
        ]);
        $container->register(SearchAppLogsTool::class)
            ->addTag(McpCompilerPass::TOOL_TAG);
        $container->register(ExampleTool::class)
            ->addTag(McpCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD])
            ->addTag(McpCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::WRITE_TOOL_METHOD]);

        new McpCompilerPass()
            ->process($container);

        self::assertSame(
            [SearchAppLogsTool::NAME, self::READ_TOOL_METHOD],
            $container->getDefinition(McpToolAccessChecker::class)->getArgument(self::READ_ONLY_TOOL_NAMES_ARGUMENT),
        );
    }

    public function testCollectsToolCategoriesAndPreviewsForTheAccessChecker(): void
    {
        $container = $this->createMcpContainer([
            SearchAppLogsTool::NAME => self::TOOL_PERMISSION,
            ExampleWithoutInputTool::NAME => self::TOOL_PERMISSION,
            ExampleErrorResultTool::NAME => self::TOOL_PERMISSION,
        ]);
        $this->registerTools($container, [
            SearchAppLogsTool::class,
            ExampleWithoutInputTool::class,
            ExamplePreviewTool::class,
            ExampleErrorResultTool::class,
        ]);

        new McpCompilerPass()
            ->process($container);

        $accessCheckerDefinition = $container->getDefinition(McpToolAccessChecker::class);
        self::assertSame(
            [
                SearchAppLogsTool::NAME => AsMcpTool::CATEGORY_DIAGNOSTICS,
                ExampleWithoutInputTool::NAME => ExampleToolCategory::EXAMPLES,
                ExamplePreviewTool::NAME => ExampleToolCategory::EXAMPLES,
                ExampleErrorResultTool::NAME => ExampleToolCategory::EXAMPLES,
            ],
            $accessCheckerDefinition->getArgument(self::TOOL_CATEGORIES_ARGUMENT),
        );
        self::assertSame(
            [ExamplePreviewTool::NAME => ExampleWithoutInputTool::NAME],
            $accessCheckerDefinition->getArgument(self::WRITE_TOOL_NAMES_BY_PREVIEW_ARGUMENT),
        );
        self::assertSame(
            [SearchAppLogsTool::NAME, ExamplePreviewTool::NAME],
            $accessCheckerDefinition->getArgument(self::READ_ONLY_TOOL_NAMES_ARGUMENT),
        );
    }

    public function testMappedToolWithoutAsMcpToolFailsAtCompileTime(): void
    {
        $container = $this->createMcpContainer([
            self::WRITE_TOOL_METHOD => self::TOOL_PERMISSION,
        ]);
        $container->register(ExampleTool::class)
            ->addTag(McpCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::WRITE_TOOL_METHOD]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(McpCompilerPass::TOOL_WITHOUT_CATEGORY_MESSAGE, self::WRITE_TOOL_METHOD));

        new McpCompilerPass()
            ->process($container);
    }

    /**
     * @dataProvider invalidToolMetadataProvider
     *
     * @param list<class-string> $toolClasses
     * @param array<string, string> $toolPermissions
     */
    public function testInvalidToolMetadataFailsAtCompileTime(
        array $toolClasses,
        array $toolPermissions,
        string $expectedMessage,
    ): void {
        $container = $this->createMcpContainer($toolPermissions);
        $this->registerTools($container, $toolClasses);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        new McpCompilerPass()
            ->process($container);
    }

    /**
     * @return array<string, array{list<class-string>, array<string, string>, string}>
     */
    public static function invalidToolMetadataProvider(): array
    {
        return [
            'category that is not camelCase' => [
                [InvalidCategoryExampleTool::class],
                [],
                sprintf(McpCompilerPass::INVALID_CATEGORY_MESSAGE, 'example.tools', InvalidCategoryExampleTool::NAME),
            ],
            'preview of an unknown tool' => [
                [ExamplePreviewTool::class],
                [],
                sprintf(McpCompilerPass::PREVIEW_OF_UNKNOWN_TOOL_MESSAGE, ExamplePreviewTool::NAME, ExampleWithoutInputTool::NAME),
            ],
            'preview of a read tool' => [
                [ExampleSearchTool::class, ReadToolPreviewExampleTool::class],
                [],
                sprintf(McpCompilerPass::PREVIEW_OF_READ_TOOL_MESSAGE, ReadToolPreviewExampleTool::NAME, ExampleSearchTool::NAME),
            ],
            'preview that is not read only' => [
                [ExampleWithoutInputTool::class, WritablePreviewExampleTool::class],
                [],
                sprintf(McpCompilerPass::WRITABLE_PREVIEW_MESSAGE, WritablePreviewExampleTool::NAME, ExampleWithoutInputTool::NAME),
            ],
            'second preview of one write tool' => [
                [ExampleWithoutInputTool::class, ExamplePreviewTool::class, SecondExamplePreviewTool::class],
                [],
                sprintf(
                    McpCompilerPass::SECOND_PREVIEW_MESSAGE,
                    ExampleWithoutInputTool::NAME,
                    implode(', ', [ExamplePreviewTool::NAME, SecondExamplePreviewTool::NAME]),
                ),
            ],
            'preview mapped in the tool permissions' => [
                [ExampleWithoutInputTool::class, ExamplePreviewTool::class],
                [
                    ExampleWithoutInputTool::NAME => self::TOOL_PERMISSION,
                    ExamplePreviewTool::NAME => self::TOOL_PERMISSION,
                ],
                sprintf(McpCompilerPass::MAPPED_PREVIEW_MESSAGE, ExamplePreviewTool::NAME, ExampleWithoutInputTool::NAME),
            ],
            'preview in another category than its write tool' => [
                [ExampleWithoutInputTool::class, OtherCategoryExamplePreviewTool::class],
                [],
                sprintf(
                    McpCompilerPass::PREVIEW_IN_OTHER_CATEGORY_MESSAGE,
                    OtherCategoryExamplePreviewTool::NAME,
                    ExampleWithoutInputTool::NAME,
                    ExampleToolCategory::EXAMPLES,
                ),
            ],
        ];
    }

    public function testToolNameRegisteredByTwoToolsFailsAtCompileTime(): void
    {
        $container = $this->createMcpContainer();
        $container->register(ExampleTool::class)
            ->addTag(McpCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);
        $container->register(ConflictingExampleTool::class)
            ->addTag(McpCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('The MCP tool name "%s" is registered by more than one tool', self::READ_TOOL_METHOD));

        new McpCompilerPass()
            ->process($container);
    }

    public function testInvalidToolNameFailsAtCompileTime(): void
    {
        $container = $this->createMcpContainer();
        $container->register(InvalidNameExampleTool::class)
            ->addTag(McpCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('The MCP tool name "%s" of', InvalidNameExampleTool::NAME));

        new McpCompilerPass()
            ->process($container);
    }

    public function testToolPermissionOfAnUnknownToolFailsAtCompileTime(): void
    {
        $container = $this->createMcpContainer([
            self::READ_TOOL_METHOD => self::TOOL_PERMISSION,
            self::UNKNOWN_TOOL_NAME => self::TOOL_PERMISSION,
        ]);
        $container->register(ExampleTool::class)
            ->addTag(McpCompilerPass::TOOL_TAG, [self::TOOL_TAG_METHOD => self::READ_TOOL_METHOD]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('maps unknown MCP tools: "%s".', self::UNKNOWN_TOOL_NAME));

        new McpCompilerPass()
            ->process($container);
    }

    public function testServerNameThatIsNotAStringFailsAtCompileTime(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, [self::SERVER_NAME]);
        $container->register(McpController::class);

        $this->expectException(LogicException::class);

        new McpCompilerPass()
            ->process($container);
    }

    /**
     * @param list<class-string> $toolClasses
     */
    private function registerTools(ContainerBuilder $container, array $toolClasses): void
    {
        foreach ($toolClasses as $toolClass) {
            $container->register($toolClass)
                ->addTag(McpCompilerPass::TOOL_TAG);
        }
    }

    /**
     * @param array<string, string> $toolPermissions
     */
    private function createMcpContainer(array $toolPermissions = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $container->register(McpController::class);
        $container->register(McpToolAccessChecker::class)
            ->setArgument(self::TOOL_PERMISSIONS_ARGUMENT, $toolPermissions)
            ->setArgument(self::READ_ONLY_TOOL_NAMES_ARGUMENT, [])
            ->setArgument(self::TOOL_CATEGORIES_ARGUMENT, [])
            ->setArgument(self::WRITE_TOOL_NAMES_BY_PREVIEW_ARGUMENT, []);

        return $container;
    }
}
