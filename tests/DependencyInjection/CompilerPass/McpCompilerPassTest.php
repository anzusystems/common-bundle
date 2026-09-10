<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Controller\McpController;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class McpCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'core_cms';
    private const string CONTROLLER_ID = 'mcp.server.core_cms.controller';

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
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $container->register(McpController::class);
        $container->register(self::CONTROLLER_ID, stdClass::class);

        new McpCompilerPass()
            ->process($container);

        self::assertTrue($container->hasAlias(self::CONTROLLER_ID));
        self::assertSame(McpController::class, (string) $container->getAlias(self::CONTROLLER_ID));
        self::assertTrue($container->getAlias(self::CONTROLLER_ID)->isPublic());
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
}
