<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use AnzuSystems\CommonBundle\Mcp\Security\McpToolAccessChecker;
use AnzuSystems\CommonBundle\Mcp\Tool\SearchAppLogsTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ConflictingExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleTool;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class McpCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'core_cms';
    private const string CONTROLLER_ID = 'mcp.server.core_cms.controller';
    private const string READ_ONLY_TOOL_NAMES_ARGUMENT = '$readOnlyToolNames';
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
            self::WRITE_TOOL_METHOD => self::TOOL_PERMISSION,
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
     * @param array<string, string> $toolPermissions
     */
    private function createMcpContainer(array $toolPermissions = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $container->register(McpController::class);
        $container->register(McpToolAccessChecker::class)
            ->setArgument(self::TOOL_PERMISSIONS_ARGUMENT, $toolPermissions)
            ->setArgument(self::READ_ONLY_TOOL_NAMES_ARGUMENT, []);

        return $container;
    }
}
