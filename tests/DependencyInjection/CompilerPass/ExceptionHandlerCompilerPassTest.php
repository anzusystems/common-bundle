<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\DependencyInjection\CompilerPass;

use AnzuSystems\CommonBundle\AnzuSystemsCommonBundle;
use AnzuSystems\CommonBundle\DependencyInjection\CompilerPass\ExceptionHandlerCompilerPass;
use AnzuSystems\CommonBundle\Event\Listener\ExceptionListener;
use AnzuSystems\CommonBundle\Exception\Handler\AccessDeniedExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\DefaultExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\HttpExceptionHandler;
use AnzuSystems\CommonBundle\Exception\Handler\NotFoundExceptionHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ExceptionHandlerCompilerPassTest extends TestCase
{
    public function testProcessSortsHandlersByPriority(): void
    {
        $container = new ContainerBuilder();

        $listener = new Definition(ExceptionListener::class);
        $listener->setArgument('$exceptionHandlers', new IteratorArgument([]));
        $container->setDefinition(ExceptionListener::class, $listener);

        $httpHandler = new Definition(HttpExceptionHandler::class);
        $httpHandler->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER, ['priority' => -100]);
        $container->setDefinition(HttpExceptionHandler::class, $httpHandler);

        $accessDeniedHandler = new Definition(AccessDeniedExceptionHandler::class);
        $accessDeniedHandler->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
        $container->setDefinition(AccessDeniedExceptionHandler::class, $accessDeniedHandler);

        $notFoundHandler = new Definition(NotFoundExceptionHandler::class);
        $notFoundHandler->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER);
        $container->setDefinition(NotFoundExceptionHandler::class, $notFoundHandler);

        $container->setDefinition(DefaultExceptionHandler::class, new Definition(DefaultExceptionHandler::class));

        (new ExceptionHandlerCompilerPass())->process($container);

        $argument = $container->getDefinition(ExceptionListener::class)->getArgument('$exceptionHandlers');
        self::assertInstanceOf(IteratorArgument::class, $argument);
        self::assertSame(
            [
                AccessDeniedExceptionHandler::class,
                NotFoundExceptionHandler::class,
                HttpExceptionHandler::class,
            ],
            array_map(
                static fn (Reference $reference): string => (string) $reference,
                $argument->getValues()
            )
        );
    }

    public function testProcessSkipsContainerWithoutListener(): void
    {
        $container = new ContainerBuilder();

        (new ExceptionHandlerCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition(ExceptionListener::class));
    }
}
