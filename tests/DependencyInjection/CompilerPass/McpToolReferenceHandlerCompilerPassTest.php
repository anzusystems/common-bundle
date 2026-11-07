<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpToolReferenceHandlerCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleTool;
use Mcp\Capability\Registry\ReferenceHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class McpToolReferenceHandlerCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'example';
    private const string HANDLER_LOCATOR_ID = '.service_locator.example';
    private const string INNER_ARGUMENT = '$inner';
    private const string SET_CONTAINER_METHOD = 'setContainer';
    private const string SET_REFERENCE_HANDLER_METHOD = 'setReferenceHandler';
    private const string APP_RENDERER_ID = 'mcp.app.renderer';
    private const string APP_TEMPLATE = 'mcp/example.html.twig';
    private const string READ_TOOL_METHOD = 'listExamples';
    private const string APP_TOOL_KEY_SEPARATOR = '::';

    public function testWrapsTheSdkHandlerOnTheHandlerLocatorWhenTheServerHasNoReferenceHandler(): void
    {
        $container = $this->createContainer();

        new McpToolReferenceHandlerCompilerPass()
            ->process($container);

        $inner = $container->getDefinition(McpToolReferenceHandler::class)->getArgument(self::INNER_ARGUMENT);
        self::assertInstanceOf(Definition::class, $inner);
        self::assertSame(ReferenceHandler::class, $inner->getClass());
        self::assertSame(self::HANDLER_LOCATOR_ID, (string) $inner->getArgument(0));
        $setReferenceHandlerCalls = $this->findMethodCalls($this->getBuilder($container), self::SET_REFERENCE_HANDLER_METHOD);
        self::assertCount(1, $setReferenceHandlerCalls);
        self::assertSame(McpToolReferenceHandler::class, (string) $setReferenceHandlerCalls[0][0]);
    }

    public function testGoesBetweenTheMcpAppHandlerAndItsSdkHandler(): void
    {
        $container = $this->createContainer();
        $sdkHandler = new Definition(ReferenceHandler::class, [new Reference(self::HANDLER_LOCATOR_ID)]);
        $appHandlerId = sprintf(McpToolReferenceHandlerCompilerPass::APP_REFERENCE_HANDLER_ID_FORMAT, self::SERVER_NAME);
        $container->register($appHandlerId)
            ->setArguments([$sdkHandler, new Reference(self::APP_RENDERER_ID), []]);
        $this->getBuilder($container)
            ->addMethodCall(self::SET_REFERENCE_HANDLER_METHOD, [new Reference($appHandlerId)]);

        new McpToolReferenceHandlerCompilerPass()
            ->process($container);

        self::assertSame($sdkHandler, $container->getDefinition(McpToolReferenceHandler::class)->getArgument(self::INNER_ARGUMENT));
        self::assertSame(McpToolReferenceHandler::class, (string) $container->getDefinition($appHandlerId)->getArgument(0));
        $setReferenceHandlerCalls = $this->findMethodCalls($this->getBuilder($container), self::SET_REFERENCE_HANDLER_METHOD);
        self::assertCount(1, $setReferenceHandlerCalls);
        self::assertSame($appHandlerId, (string) $setReferenceHandlerCalls[0][0]);
    }

    public function testAsMcpToolWithAnMcpAppTemplateFailsAtCompileTime(): void
    {
        $container = $this->createContainer();
        $container->register(ExampleSearchTool::class);
        $container->setParameter(McpToolReferenceHandlerCompilerPass::APP_TOOL_TEMPLATES_PARAM, [
            self::SERVER_NAME => [ExampleSearchTool::class . self::APP_TOOL_KEY_SEPARATOR . McpToolSignatureResolver::INVOKE_METHOD => self::APP_TEMPLATE],
        ]);

        $this->expectExceptionObject(McpConfigurationException::createAppTemplateOnAsMcpTool(ExampleSearchTool::class, ExampleSearchTool::class . self::APP_TOOL_KEY_SEPARATOR . McpToolSignatureResolver::INVOKE_METHOD));

        new McpToolReferenceHandlerCompilerPass()
            ->process($container);
    }

    public function testMcpAppTemplateOfAToolWithoutAsMcpToolIsAllowed(): void
    {
        $container = $this->createContainer();
        $container->register(ExampleTool::class);
        $container->setParameter(McpToolReferenceHandlerCompilerPass::APP_TOOL_TEMPLATES_PARAM, [
            self::SERVER_NAME => [ExampleTool::class . self::APP_TOOL_KEY_SEPARATOR . self::READ_TOOL_METHOD => self::APP_TEMPLATE],
        ]);

        new McpToolReferenceHandlerCompilerPass()
            ->process($container);

        self::assertCount(1, $this->findMethodCalls($this->getBuilder($container), self::SET_REFERENCE_HANDLER_METHOD));
    }

    public function testForeignReferenceHandlerFailsAtCompileTime(): void
    {
        $container = $this->createContainer();
        $this->getBuilder($container)
            ->addMethodCall(self::SET_REFERENCE_HANDLER_METHOD, [new Reference(self::APP_RENDERER_ID)]);

        $this->expectExceptionObject(McpConfigurationException::createForeignReferenceHandler(self::SERVER_NAME));

        new McpToolReferenceHandlerCompilerPass()
            ->process($container);
    }

    public function testDoesNothingWhenMcpIsDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->register(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME));

        new McpToolReferenceHandlerCompilerPass()
            ->process($container);

        self::assertSame([], $this->getBuilder($container)->getMethodCalls());
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $container->register(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME))
            ->addMethodCall(self::SET_CONTAINER_METHOD, [new Reference(self::HANDLER_LOCATOR_ID)]);
        $container->register(McpToolReferenceHandler::class)
            ->setArgument(self::INNER_ARGUMENT, null);

        return $container;
    }

    private function getBuilder(ContainerBuilder $container): Definition
    {
        return $container->getDefinition(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME));
    }

    /**
     * @return list<array<int, mixed>>
     */
    private function findMethodCalls(Definition $definition, string $method): array
    {
        $arguments = [];
        foreach ($definition->getMethodCalls() as [$calledMethod, $calledArguments]) {
            if ($method === $calledMethod) {
                $arguments[] = $calledArguments;
            }
        }

        return $arguments;
    }
}
