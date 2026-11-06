<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpToolSchemaCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Factory\McpOutputDescriptionFactory;
use AnzuSystems\CommonBundle\Mcp\Factory\McpSchemaFactory;
use AnzuSystems\CommonBundle\Mcp\Model\Decorator\McpAuditLogDecorator;
use AnzuSystems\CommonBundle\Mcp\Model\Request\SearchAppLogsRequest;
use AnzuSystems\CommonBundle\Mcp\Model\Response\McpLogsByContextResponse;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleContextTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleWithoutInputTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\InvalidParameterExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\InvalidReturnExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\PlainMcpToolExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\RateLimitedExampleTool;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\SharedRateLimitedExampleTool;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\RequestContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class McpToolSchemaCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'core_cms';
    private const string ADD_TOOL_METHOD = 'addTool';
    private const string INVOKE_METHOD = '__invoke';
    private const string DESCRIPTION = 'Example tool.';
    private const array PARAMETER_SCHEMA = [
        'type' => 'object',
        'properties' => [],
    ];
    private const int DESCRIPTION_ARGUMENT = 3;
    private const int INPUT_SCHEMA_ARGUMENT = 5;

    /**
     * @dataProvider validSignatureProvider
     *
     * @param class-string $toolClass
     * @param class-string|null $expectedInputClass
     * @param class-string|null $expectedOutputClass
     */
    public function testInputSchemaAndReturnsComeFromTheInvokeSignature(
        string $toolClass,
        ?string $expectedInputClass,
        ?string $expectedOutputClass,
    ): void {
        $container = $this->createContainer($toolClass);

        new McpToolSchemaCompilerPass()
            ->process($container);

        $schemaFactory = new McpSchemaFactory();
        $arguments = $this->getAddToolArguments($container);
        self::assertSame(
            null === $expectedInputClass ? self::PARAMETER_SCHEMA : $schemaFactory->createForClass($expectedInputClass),
            $arguments[self::INPUT_SCHEMA_ARGUMENT],
        );
        self::assertSame(
            null === $expectedOutputClass
                ? self::DESCRIPTION
                : self::DESCRIPTION . PHP_EOL . PHP_EOL . new McpOutputDescriptionFactory($schemaFactory)->createForClass($expectedOutputClass),
            $arguments[self::DESCRIPTION_ARGUMENT],
        );
    }

    /**
     * @return array<string, array{class-string, class-string|null, class-string|null}>
     */
    public static function validSignatureProvider(): array
    {
        return [
            'input class, response class' => [ExampleSearchTool::class, SearchAppLogsRequest::class, McpLogsByContextResponse::class],
            'no input, response class' => [ExampleWithoutInputTool::class, null, McpLogsByContextResponse::class],
            'input class and request context, own call tool result' => [ExampleContextTool::class, SearchAppLogsRequest::class, null],
        ];
    }

    public function testWriteLimitIsAddedToTheDescription(): void
    {
        $container = $this->createContainer(RateLimitedExampleTool::class);

        new McpToolSchemaCompilerPass()
            ->process($container);

        self::assertStringContainsString(
            'Writes through this tool have a limit per user.',
            $this->getAddToolArguments($container)[self::DESCRIPTION_ARGUMENT],
        );
    }

    public function testToolsSharingALimiterNameEachOther(): void
    {
        $container = $this->createContainer(RateLimitedExampleTool::class);
        $container->getDefinition(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME))
            ->addMethodCall(self::ADD_TOOL_METHOD, [
                [SharedRateLimitedExampleTool::class, self::INVOKE_METHOD],
                null,
                null,
                self::DESCRIPTION,
                null,
                self::PARAMETER_SCHEMA,
                null,
                null,
                null,
            ]);

        new McpToolSchemaCompilerPass()
            ->process($container);

        self::assertStringContainsString(
            sprintf('Writes through %s, %s share one limit per user.', RateLimitedExampleTool::NAME, SharedRateLimitedExampleTool::NAME),
            $this->getAddToolArguments($container)[self::DESCRIPTION_ARGUMENT],
        );
    }

    public function testToolWithoutAsMcpToolKeepsItsDescriptionAndSchema(): void
    {
        $container = $this->createContainer(PlainMcpToolExampleTool::class);

        new McpToolSchemaCompilerPass()
            ->process($container);

        $arguments = $this->getAddToolArguments($container);
        self::assertSame(self::DESCRIPTION, $arguments[self::DESCRIPTION_ARGUMENT]);
        self::assertSame(self::PARAMETER_SCHEMA, $arguments[self::INPUT_SCHEMA_ARGUMENT]);
    }

    public function testDescribedClassesAreResourcesOfTheContainer(): void
    {
        $container = $this->createContainer(ExampleSearchTool::class);

        new McpToolSchemaCompilerPass()
            ->process($container);

        foreach ([SearchAppLogsRequest::class, McpLogsByContextResponse::class, McpAuditLogDecorator::class] as $class) {
            self::assertContainsEquals(
                new FileResource((string) new ReflectionClass($class)->getFileName()),
                $container->getResources(),
                $class,
            );
        }
    }

    /**
     * @dataProvider invalidSignatureProvider
     *
     * @param class-string $toolClass
     */
    public function testInvalidInvokeSignatureFailsAtCompileTime(string $toolClass, McpConfigurationException $expectedException): void
    {
        $container = $this->createContainer($toolClass);

        $this->expectExceptionObject($expectedException);

        new McpToolSchemaCompilerPass()
            ->process($container);
    }

    /**
     * @return array<string, array{class-string, McpConfigurationException}>
     */
    public static function invalidSignatureProvider(): array
    {
        return [
            'parameter that is not the input class' => [
                InvalidParameterExampleTool::class,
                McpConfigurationException::createInvalidToolParameter(
                    class: InvalidParameterExampleTool::class,
                    method: self::INVOKE_METHOD,
                    contextClass: RequestContext::class,
                    parameter: 'siteId',
                ),
            ],
            'array return type' => [
                InvalidReturnExampleTool::class,
                McpConfigurationException::createInvalidToolReturn(
                    class: InvalidReturnExampleTool::class,
                    method: self::INVOKE_METHOD,
                    resultClass: CallToolResult::class,
                    type: 'array',
                ),
            ],
        ];
    }

    /**
     * @param class-string $toolClass
     */
    private function createContainer(string $toolClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $container->setDefinition(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME), new Definition())
            ->addMethodCall(self::ADD_TOOL_METHOD, [
                [$toolClass, self::INVOKE_METHOD],
                null,
                null,
                self::DESCRIPTION,
                null,
                self::PARAMETER_SCHEMA,
                null,
                null,
                null,
            ]);

        return $container;
    }

    /**
     * @return array<int, mixed>
     */
    private function getAddToolArguments(ContainerBuilder $container): array
    {
        $methodCalls = $container->getDefinition(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME))
            ->getMethodCalls();

        return $methodCalls[0][1];
    }
}
