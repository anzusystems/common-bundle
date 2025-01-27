<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event\Listener;

use AnzuSystems\CommonBundle\Exception\Handler\ExceptionHandlerInterface;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Throwable;
use Traversable;

final class ExceptionListener
{
    /**
     * @param Traversable<class-string<ExceptionHandlerInterface>, ExceptionHandlerInterface> $exceptionHandlers
     */
    public function __construct(
        private readonly Traversable $exceptionHandlers,
        private readonly ExceptionHandlerInterface $defaultExceptionHandler,
        private readonly LoggerInterface $appLogger,
        private readonly array $ignoredExceptions = [],
        private readonly ?LogContextFactory $logContextFactory = null,
        private readonly array $onlyUriMatch = [],
    ) {
    }

    /**
     * @throws SerializerException
     */
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $handler = $this->getHandler($exception);

        if ($this->isRequestMatchForAllowedHandling($event->getRequest())) {
            $event->setResponse(
                $handler->getErrorResponse($exception)
            );
            $event->allowCustomResponseCode();
        }

        if (in_array($exception::class, $this->ignoredExceptions, true)) {
            return;
        }

        $context = [];
        if ($this->logContextFactory instanceof LogContextFactory) {
            $context = $this->logContextFactory->buildFromRequestToArray(
                $event->getRequest(),
                $event->getResponse()
            );
        }

        if ($this->defaultExceptionHandler::class === $handler::class) {
            $this->appLogger->critical((string) $exception, $context);

            return;
        }

        $this->appLogger->error((string) $exception, $context);
    }

    private function getHandler(Throwable $throwable): ExceptionHandlerInterface
    {
        $handlers = iterator_to_array($this->exceptionHandlers);

        foreach ($handlers as $exceptionHandler) {
            foreach ($exceptionHandler->getSupportedExceptionClasses() as $supportedExceptionClass) {
                if (is_a($throwable, $supportedExceptionClass, true)) {
                    return $exceptionHandler;
                }
            }
        }

        return $this->defaultExceptionHandler;
    }

    private function isRequestMatchForAllowedHandling(Request $request): bool
    {
        if (empty($this->onlyUriMatch)) {
            return true;
        }

        foreach ($this->onlyUriMatch as $onlyUriMatch) {
            if (preg_match(sprintf('~%s~i', $onlyUriMatch), $request->getPathInfo())) {
                return true;
            }
        }

        return false;
    }
}
