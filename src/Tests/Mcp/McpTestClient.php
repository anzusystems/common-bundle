<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp;

use AnzuSystems\CommonBundle\Mcp\Attribute\AsMcpTool;
use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use LogicException;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\JsonRpc\MessageInterface;
use Mcp\Server\Transport\StreamableHttpTransport;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

final class McpTestClient
{
    private const string METHOD_INITIALIZE = 'initialize';
    private const string METHOD_TOOLS_CALL = 'tools/call';
    private const string METHOD_TOOLS_LIST = 'tools/list';
    private const string CLIENT_NAME = 'anzu-mcp-test-client';
    private const string CLIENT_VERSION = '1.0.0';
    private const string SERVER_AUTHORIZATION = 'HTTP_AUTHORIZATION';
    private const string SERVER_HOST = 'HTTP_HOST';
    private const string SERVER_ACCEPT = 'HTTP_ACCEPT';
    private const string SERVER_SESSION = 'HTTP_MCP_SESSION_ID';
    private const string SERVER_CONTENT_TYPE = 'CONTENT_TYPE';
    private const string BEARER_FORMAT = 'Bearer %s';
    private const string CONTENT_TYPE_JSON = 'application/json';
    private const string ACCEPT = 'application/json, text/event-stream';
    private const string PREVIEW_HASH_KEY = 'previewHash';
    private const string RESULT_KEY = 'result';
    private const string ERROR_KEY = 'error';
    private const string NAME_KEY = 'name';

    private ?string $sessionId = null;
    private int $lastRequestId = 0;

    public function __construct(
        private readonly KernelBrowser $browser,
        private readonly string $path,
        private readonly string $host,
        private readonly string $token,
    ) {
    }

    /**
     * @param class-string $toolClass
     */
    public static function resolveToolName(string $toolClass): string
    {
        $attribute = new ReflectionClass($toolClass)
            ->getAttributes(AsMcpTool::class) |> array_first(...);
        if (false === $attribute instanceof ReflectionAttribute) {
            throw new LogicException(sprintf('The class "%s" does not declare #[AsMcpTool].', $toolClass));
        }

        return $attribute->newInstance()
            ->name;
    }

    /**
     * @param class-string $toolClass
     * @param array<string, mixed> $arguments
     */
    public function callTool(string $toolClass, array $arguments = []): McpToolResult
    {
        $message = $this->send(self::METHOD_TOOLS_CALL, [
            self::NAME_KEY => self::resolveToolName($toolClass),
            'arguments' => (object) $arguments,
        ]);
        if (isset($message[self::ERROR_KEY])) {
            return new McpToolResult(isError: true, error: (string) $message[self::ERROR_KEY]['message'], data: []);
        }

        $data = $message[self::RESULT_KEY]['structuredContent'] ?? [];

        return new McpToolResult(
            isError: $message[self::RESULT_KEY]['isError'] ?? false,
            error: $data[McpToolReferenceHandler::ERROR_KEY] ?? null,
            data: $data,
        );
    }

    /**
     * @return list<string>
     */
    public function listToolNames(): array
    {
        return array_column($this->send(self::METHOD_TOOLS_LIST)[self::RESULT_KEY]['tools'] ?? [], self::NAME_KEY);
    }

    /**
     * @param class-string $previewToolClass
     * @param array<string, mixed> $arguments
     */
    public function getPreviewHash(string $previewToolClass, array $arguments): string
    {
        $result = $this->callTool($previewToolClass, $arguments);
        $previewHash = $result->data[self::PREVIEW_HASH_KEY] ?? null;
        if (false === is_string($previewHash)) {
            throw new UnexpectedValueException(sprintf('The preview "%s" returned no hash: %s', $previewToolClass, (string) $result->error));
        }

        return $previewHash;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function send(string $method, array $params = []): array
    {
        if (null === $this->sessionId) {
            $this->initialize();
        }

        return $this->post($method, $params)
            ->getContent()
            |> (static fn (string|false $content): array => json_decode(json: (string) $content, associative: true, flags: JSON_THROW_ON_ERROR));
    }

    private function initialize(): void
    {
        $response = $this->post(self::METHOD_INITIALIZE, [
            'protocolVersion' => ProtocolVersion::V2025_03_26->value,
            'capabilities' => (object) [],
            'clientInfo' => [self::NAME_KEY => self::CLIENT_NAME, 'version' => self::CLIENT_VERSION],
        ]);
        $this->sessionId = $response->headers->get(StreamableHttpTransport::SESSION_HEADER);
        if (null === $this->sessionId) {
            throw new UnexpectedValueException(sprintf('The MCP server opened no session: %s', (string) $response->getContent()));
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function post(string $method, array $params): Response
    {
        $server = [
            self::SERVER_AUTHORIZATION => sprintf(self::BEARER_FORMAT, $this->token),
            self::SERVER_HOST => $this->host,
            self::SERVER_ACCEPT => self::ACCEPT,
            self::SERVER_CONTENT_TYPE => self::CONTENT_TYPE_JSON,
        ];
        if (is_string($this->sessionId)) {
            $server[self::SERVER_SESSION] = $this->sessionId;
        }

        $this->browser->request(
            method: Request::METHOD_POST,
            uri: $this->path,
            server: $server,
            content: json_encode(
                ['jsonrpc' => MessageInterface::JSONRPC_VERSION, 'id' => ++$this->lastRequestId, 'method' => $method, 'params' => (object) $params],
                JSON_THROW_ON_ERROR,
            ),
        );
        $response = $this->browser->getResponse();
        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new UnexpectedValueException(sprintf('The MCP endpoint answered %d: %s', $response->getStatusCode(), (string) $response->getContent()));
        }

        return $response;
    }
}
