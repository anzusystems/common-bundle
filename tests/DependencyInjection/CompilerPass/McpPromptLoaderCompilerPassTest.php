<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpCompilerPass;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\McpPromptLoaderCompilerPass;
use AnzuSystems\CommonBundle\Mcp\Exception\McpConfigurationException;
use AnzuSystems\CommonBundle\Mcp\Loader\McpPromptLoader;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpToolSignatureResolver;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\ArgumentsExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\FieldlessArgumentExamplePrompt;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Prompt\PlainExamplePrompt;
use Mcp\Capability\Completion\ListCompletionProvider;
use Mcp\Capability\Registry;
use Mcp\Schema\Icon;
use Mcp\Schema\PromptArgument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class McpPromptLoaderCompilerPassTest extends TestCase
{
    private const string SERVER_NAME = 'example';
    private const string TITLE = 'Arguments example';
    private const string DESCRIPTION = 'Lists the examples of a topic.';
    private const string ICON_SRC = 'https://example.com/icon.png';
    private const array META = ['category' => 'examples'];
    private const string LOADER_SERVICE = 'prompt_loader';

    public function testAsMcpPromptMovesFromAddPromptToTheLoader(): void
    {
        $container = $this->createContainer([ArgumentsExamplePrompt::class, PlainExamplePrompt::class]);

        new McpPromptLoaderCompilerPass()
            ->process($container);

        $methodCalls = $this->getBuilder($container)
            ->getMethodCalls();
        self::assertSame(
            [McpPromptLoaderCompilerPass::ADD_PROMPT_METHOD, McpPromptLoaderCompilerPass::ADD_LOADER_METHOD],
            array_column($methodCalls, 0),
        );
        self::assertSame([PlainExamplePrompt::class, McpToolSignatureResolver::INVOKE_METHOD], $methodCalls[0][1][0]);
    }

    public function testLoaderRegistersArgumentsFromMcpFields(): void
    {
        $container = $this->createContainer([ArgumentsExamplePrompt::class]);

        new McpPromptLoaderCompilerPass()
            ->process($container);

        $registry = new Registry();
        $this->createLoader($container)
            ->load($registry);
        $reference = $registry->getPrompt(ArgumentsExamplePrompt::NAME);
        self::assertEquals(
            [
                new PromptArgument(name: 'topic', description: 'Name or id of the topic.', required: true),
                new PromptArgument(
                    name: 'limit',
                    description: sprintf('Number of examples, at most %d.', ArgumentsExamplePrompt::LIMIT_MAX),
                    required: false,
                ),
            ],
            $reference->prompt->arguments,
        );
        self::assertSame(self::TITLE, $reference->prompt->title);
        self::assertSame(self::DESCRIPTION, $reference->prompt->description);
        self::assertEquals([new Icon(self::ICON_SRC)], $reference->prompt->icons);
        self::assertSame(self::META, $reference->prompt->meta);
        self::assertSame([ArgumentsExamplePrompt::class, McpToolSignatureResolver::INVOKE_METHOD], $reference->handler);
        self::assertEquals(
            ['limit' => new ListCompletionProvider(ArgumentsExamplePrompt::LIMIT_COMPLETIONS)],
            $reference->completionProviders,
        );
    }

    public function testArgumentWithoutMcpFieldFailsAtCompileTime(): void
    {
        $container = $this->createContainer([FieldlessArgumentExamplePrompt::class]);

        $this->expectExceptionObject(McpConfigurationException::createPromptArgumentWithoutField(
            FieldlessArgumentExamplePrompt::NAME,
            FieldlessArgumentExamplePrompt::ARGUMENT_NAME,
        ));

        new McpPromptLoaderCompilerPass()
            ->process($container);
    }

    /**
     * @param list<class-string> $promptClasses
     */
    private function createContainer(array $promptClasses): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(McpCompilerPass::SERVER_NAME_PARAM, self::SERVER_NAME);
        $builder = $container->setDefinition(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME), new Definition());
        foreach ($promptClasses as $promptClass) {
            $builder->addMethodCall(McpPromptLoaderCompilerPass::ADD_PROMPT_METHOD, [
                [$promptClass, McpToolSignatureResolver::INVOKE_METHOD],
                constant($promptClass . '::NAME'),
                self::TITLE,
                self::DESCRIPTION,
                [new Definition(Icon::class, [self::ICON_SRC, null, null])],
                self::META,
            ]);
        }

        return $container;
    }

    private function getBuilder(ContainerBuilder $container): Definition
    {
        return $container->getDefinition(sprintf(McpCompilerPass::BUILDER_ID_FORMAT, self::SERVER_NAME));
    }

    private function createLoader(ContainerBuilder $container): McpPromptLoader
    {
        $loaderCall = array_last($this->getBuilder($container)->getMethodCalls());
        self::assertSame(McpPromptLoaderCompilerPass::ADD_LOADER_METHOD, $loaderCall[0]);
        $loaderContainer = new ContainerBuilder();
        $loaderContainer->setDefinition(self::LOADER_SERVICE, $loaderCall[1][0])
            ->setPublic(true);
        $loaderContainer->compile();
        $loader = $loaderContainer->get(self::LOADER_SERVICE);
        self::assertInstanceOf(McpPromptLoader::class, $loader);

        return $loader;
    }
}
