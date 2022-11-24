<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Traits;

use AnzuSystems\CommonBundle\Kernel\AnzuKernel;
use AnzuSystems\CommonBundle\Log\Factory\LogContextFactory;
use AnzuSystems\CommonBundle\Model\HttpClient\HttpClientResponse;
use AnzuSystems\Contracts\AnzuApp;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait LoggerAwareRequest
{
    use LoggerAwareTrait;
    use SerializerAwareTrait;

    private LogContextFactory $contextFactory;

    #[Required]
    public function setContextFactory(LogContextFactory $contextFactory): void
    {
        $this->contextFactory = $contextFactory;
    }

    /**
     * @throws JsonException
     */
    protected function loggedRequest(
        HttpClientInterface $client,
        string $message,
        string $url,
        string $method = Request::METHOD_GET,
        array $headers = [],
        array $json = [],
        array $query = [],
        array | string $body = [],
        string $authBearer = '',
        int $timeout = 5,
        bool $logSuccess = true,
        array $notLogErrorResponseCodes = [],
        callable $contentValidator = null,
    ): HttpClientResponse {
        $context = $this->contextFactory->buildForRequest($method, $url, $json, $query, $body, $timeout);
        if (!$this->logger) {
            throw new InvalidArgumentException('Logger is missing.');
        }

        try {
            $options = [];
            if ($timeout) {
                $options['timeout'] = $timeout;
            }
            if ($json) {
                $options['json'] = $json;
            }
            if ($query) {
                $options['query'] = $query;
            }
            if ($body) {
                $options['body'] = $body;
            }
            if ($headers) {
                $options['headers'] = $headers;
            }
            if ($authBearer) {
                $options['auth_bearer'] = $authBearer;
            }
            $options['headers'][AnzuKernel::CONTEXT_IDENTITY_HEADER] = AnzuApp::getContextId();
            $options['headers'][LogContextFactory::REQUEST_ORIGIN_VERSION_HEADER] = AnzuApp::getAppVersionWithSystem();
            $response = $client->request($method, $url, $options);
            $content = $response->getContent(false);

            $context->setResponse($content);
            $context->setHttpStatus($response->getStatusCode());
            if ($response->getStatusCode() > 300) {
                if (empty($notLogErrorResponseCodes)
                    || false === in_array($response->getStatusCode(), $notLogErrorResponseCodes, true)
                ) {
                    $this->logger->error($message, $this->serializer->toArray($context));
                }

                return new HttpClientResponse(statusCode: $response->getStatusCode());
            }
            if ((is_callable($contentValidator) && false === $contentValidator($content)) || $response->getStatusCode() >= 300) {
                $this->logger->error($message, $this->serializer->toArray($context));

                return new HttpClientResponse(statusCode: $response->getStatusCode());
            }
            if ($logSuccess) {
                $this->logger->info($message, $this->serializer->toArray($context));
            }

            return new HttpClientResponse(content: $content, statusCode: $response->getStatusCode());
        } catch (ExceptionInterface $exception) {
            $context->setException($exception::class);
            $context->setError($exception->getMessage());
            $this->logger->error($message, $this->serializer->toArray($context));
        }

        return new HttpClientResponse();
    }
}
