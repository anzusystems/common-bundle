<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Log\Factory;

use AnzuSystems\CommonBundle\Document\LogContext;
use AnzuSystems\CommonBundle\Domain\User\CurrentAnzuUserProvider;
use AnzuSystems\CommonBundle\Log\Model\LogDto;
use AnzuSystems\Contracts\AnzuApp;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use JsonException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LogContextFactory
{
    public const string REQUEST_ORIGIN_VERSION_HEADER = 'X-App-Version';

    public function __construct(
        private readonly CurrentAnzuUserProvider $userProvider,
        private readonly Serializer $serializer,
    ) {
    }

    public function buildBaseContext(): LogContext
    {
        return (new LogContext())
            ->setAppSystem(AnzuApp::getAppSystem())
            ->setAppVersion(AnzuApp::getAppVersion())
            ->setContextId(AnzuApp::getContextId())
        ;
    }

    /**
     * @throws JsonException
     */
    public function buildForRequest(
        string $method,
        string $url,
        ?array $json,
        ?array $query,
        array | string $body,
        ?int $timeout
    ): LogContext {
        $content = $body ?: $json;
        if (is_array($content)) {
            $content = json_encode($json, JSON_THROW_ON_ERROR);
        }
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        return $this->buildBaseContext()
            ->setContent((string) $content)
            ->setMethod($method)
            ->setPath($url)
            ->setTimeout($timeout ?? 0)
        ;
    }

    public function buildFromRequest(Request $request, Response $response = null): LogContext
    {
        return $this->buildBaseContext()
            ->setRequestOriginAppVersion((string) $request->headers->get(self::REQUEST_ORIGIN_VERSION_HEADER))
            ->setPath($request->getPathInfo())
            ->setMethod($request->getMethod())
            ->setUserId((int) $this->userProvider->getCurrentUser()->getId())
            ->setParams($request->attributes->get('_route_params', []))
            ->setContent((string) $request->getContent())
            ->setIp((string) $request->getClientIp())
            ->setResponse((string) $response?->getContent())
            ->setHttpStatus((int) $response?->getStatusCode())
        ;
    }

    /**
     * @throws SerializerException
     */
    public function buildFromRequestToArray(Request $request, Response $response = null): array
    {
        $context = $this->serializer->toArray(
            $this->buildFromRequest($request, $response)
        );

        if (is_array($context)) {
            return $context;
        }

        return [];
    }

    /**
     * @throws SerializerException
     */
    public function buildFromConsoleErrorEventToArray(ConsoleErrorEvent $event): array
    {
        $context = $this->serializer->toArray(
            $this->buildFromConsoleErrorEvent($event)
        );

        if (is_array($context)) {
            return $context;
        }

        return [];
    }

    public function buildFromConsoleErrorEvent(ConsoleErrorEvent $event): LogContext
    {
        return $this->buildBaseContext()
            ->setContent((string) $event->getError())
            ->setPath((string) $event->getCommand()?->getName())
            ->setParams(['args' => $event->getInput()->getArguments(), 'opts' => $event->getInput()->getOptions()])
            ->setUserId((int) $this->userProvider->getCurrentUser()->getId())
            ->setHttpStatus($event->getExitCode())
        ;
    }

    public function buildCustomFromRequest(Request $request, LogDto $logDto): LogContext
    {
        return $this->buildBaseContext()
            ->setContextId($logDto->getContextId())
            ->setAppSystem($logDto->getAppSystem())
            ->setContent($logDto->getContent())
            ->setPath($logDto->getPath())
            ->setAppVersion((string) $request->headers->get(self::REQUEST_ORIGIN_VERSION_HEADER))
            ->setUserId((int) $this->userProvider->getCurrentUser()->getId())
            ->setIp((string) $request->getClientIp())
        ;
    }
}
