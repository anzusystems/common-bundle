<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use LogicException;
use Mcp\Schema\ServerCapabilities;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class McpCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'core_cms';
    private const string CONTROLLER_ID = 'mcp.server.core_cms.controller';
    private const string BUILDER_ID = 'mcp.server.core_cms.builder';
    private const string TAGGED_SERVICE_ID = 'tagged_service';
    private const string TOOL_PERMISSION = 'cms_example_read';

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

    /**
     * @dataProvider serverCapabilitiesProvider
     */
    public function testServerBuilderGetsCapabilitiesLifetimeAndNoShim(?string $tag, bool $expectedResources, bool $expectedPrompts): void
    {
        $container = $this->createMcpContainer();
        $container->register(self::BUILDER_ID);
        if (is_string($tag)) {
            $container->register(self::TAGGED_SERVICE_ID, stdClass::class)
                ->addTag($tag);
        }

        new McpCompilerPass()
            ->process($container);

        $methodCalls = $container->getDefinition(self::BUILDER_ID)
            ->getMethodCalls();
        self::assertSame(
            ['setCapabilities', 'setSubscriptionLifetime', 'withoutInputRequiredShim'],
            array_column($methodCalls, 0),
        );
        $capabilities = $methodCalls[0][1][0];
        self::assertInstanceOf(Definition::class, $capabilities);
        self::assertSame(ServerCapabilities::class, $capabilities->getClass());
        self::assertSame(
            [
                '$tools' => true,
                '$toolsListChanged' => false,
                '$resources' => $expectedResources,
                '$resourcesSubscribe' => false,
                '$resourcesListChanged' => false,
                '$prompts' => $expectedPrompts,
                '$promptsListChanged' => false,
                '$logging' => true,
                '$completions' => true,
            ],
            $capabilities->getArguments(),
        );
        self::assertSame([McpCompilerPass::SUBSCRIPTION_LIFETIME_SECONDS], $methodCalls[1][1]);
        self::assertGreaterThan(0, McpCompilerPass::SUBSCRIPTION_LIFETIME_SECONDS);
    }

    /**
     * @return array<string, array{string|null, bool, bool}>
     */
    public static function serverCapabilitiesProvider(): array
    {
        return [
            'no prompts and no resources' => [null, false, false],
            'prompt' => ['mcp.prompt', false, true],
            'resource' => ['mcp.resource', true, false],
            'resource template' => ['mcp.resource_template', true, false],
        ];
    }

    public function testPassDoesNotTouchAMissingBuilder(): void
    {
        $container = $this->createMcpContainer();

        new McpCompilerPass()
            ->process($container);

        self::assertFalse($container->hasDefinition(self::BUILDER_ID));
        self::assertTrue($container->hasAlias(self::CONTROLLER_ID));
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

    private function createMcpContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $container->setParameter(AsMcpTool::LOG_TOOLS_PERMISSION_PARAM, self::TOOL_PERMISSION);
        $container->register(McpController::class);

        return $container;
    }

}
