<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Tests\Mcp;

use AnzuSystems\CommonBundle\Mcp\Handler\McpToolReferenceHandler;
use AnzuSystems\CommonBundle\Tests\Data\Mcp\Tool\ExampleSearchTool;
use AnzuSystems\CommonBundle\Tests\Data\Model\Enum\DummyEnum;
use AnzuSystems\CommonBundle\Tests\Mcp\McpTestClient;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

final class McpTestClientTest extends TestCase
{
    private const string PATH = '/api/mcp';
    private const string HOST = 'mcp.example.test';
    private const string TOKEN = 'plain_token';
    private const string SESSION_ID = 'session-1';
    private const string ERROR_MESSAGE = 'The tool failed.';

    /**
     * @var list<array{server: array<string, string>, body: array<string, mixed>}>
     */
    private array $requests = [];

    public function testSessionOpensOnceAndEveryRequestCarriesTheToken(): void
    {
        $client = $this->createClient([
            $this->createInitializeResponse(),
            $this->createResultResponse(['structuredContent' => []]),
            $this->createResultResponse(['structuredContent' => []]),
        ]);

        $client->callTool(ExampleSearchTool::class);
        $client->callTool(ExampleSearchTool::class);

        self::assertSame(['initialize', 'tools/call', 'tools/call'], array_map(static fn (array $request): string => $request['body']['method'], $this->requests));
        self::assertArrayNotHasKey('HTTP_MCP_SESSION_ID', $this->requests[0]['server']);
        self::assertSame(self::SESSION_ID, $this->requests[2]['server']['HTTP_MCP_SESSION_ID']);
        foreach ($this->requests as $request) {
            self::assertSame('Bearer ' . self::TOKEN, $request['server']['HTTP_AUTHORIZATION']);
            self::assertSame(self::HOST, $request['server']['HTTP_HOST']);
        }
    }

    public function testToolNameAndEnumValuesAreSentLikeAClientSendsThem(): void
    {
        $client = $this->createClient([$this->createInitializeResponse(), $this->createResultResponse(['structuredContent' => []])]);

        $client->callTool(ExampleSearchTool::class, ['level' => DummyEnum::Default]);

        self::assertSame(ExampleSearchTool::NAME, $this->requests[1]['body']['params']['name']);
        self::assertSame(DummyEnum::Default->value, $this->requests[1]['body']['params']['arguments']['level']);
    }

    public function testToolResultIsReturnedWithItsData(): void
    {
        $client = $this->createClient([$this->createInitializeResponse(), $this->createResultResponse(['structuredContent' => ['id' => 5]])]);

        $result = $client->callTool(ExampleSearchTool::class);

        self::assertFalse($result->isError);
        self::assertNull($result->error);
        self::assertSame(['id' => 5], $result->data);
    }

    public function testToolErrorIsReturnedWithItsMessage(): void
    {
        $client = $this->createClient([
            $this->createInitializeResponse(),
            $this->createResultResponse(['isError' => true, 'structuredContent' => [McpToolReferenceHandler::ERROR_KEY => self::ERROR_MESSAGE]]),
        ]);

        $result = $client->callTool(ExampleSearchTool::class);

        self::assertTrue($result->isError);
        self::assertSame(self::ERROR_MESSAGE, $result->error);
    }

    public function testErrorOfTheInputSchemaCountsAsAToolError(): void
    {
        $client = $this->createClient([
            $this->createInitializeResponse(),
            new Response(json_encode(['jsonrpc' => '2.0', 'id' => 2, 'error' => ['code' => -32_602, 'message' => self::ERROR_MESSAGE]], JSON_THROW_ON_ERROR)),
        ]);

        $result = $client->callTool(ExampleSearchTool::class);

        self::assertTrue($result->isError);
        self::assertSame(self::ERROR_MESSAGE, $result->error);
        self::assertSame([], $result->data);
    }

    public function testAnsweredStatusOtherThanOkFailsTheTest(): void
    {
        $client = $this->createClient([new Response('denied', Response::HTTP_FORBIDDEN)]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The MCP endpoint answered 403: denied');

        $client->callTool(ExampleSearchTool::class);
    }

    public function testPreviewWithoutAHashFailsTheTest(): void
    {
        $client = $this->createClient([
            $this->createInitializeResponse(),
            $this->createResultResponse(['isError' => true, 'structuredContent' => [McpToolReferenceHandler::ERROR_KEY => self::ERROR_MESSAGE]]),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(self::ERROR_MESSAGE);

        $client->getPreviewHash(ExampleSearchTool::class, []);
    }

    /**
     * @param list<Response> $responses
     */
    private function createClient(array $responses): McpTestClient
    {
        $browser = $this->createMock(KernelBrowser::class);
        $browser->method('request')
            ->willReturnCallback(function (string $method, string $uri, array $parameters = [], array $files = [], array $server = [], ?string $content = null) use ($browser): mixed {
                self::assertSame(self::PATH, $uri);
                $this->requests[] = ['server' => $server, 'body' => json_decode(json: (string) $content, associative: true, flags: JSON_THROW_ON_ERROR)];

                return $browser->getCrawler();
            });
        $browser->method('getResponse')
            ->willReturnCallback(function () use (&$responses): Response {
                return $responses[count($this->requests) - 1];
            });

        return new McpTestClient(browser: $browser, path: self::PATH, host: self::HOST, token: self::TOKEN);
    }

    private function createInitializeResponse(): Response
    {
        return new Response(content: '{}', status: Response::HTTP_OK, headers: ['Mcp-Session-Id' => self::SESSION_ID]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function createResultResponse(array $result): Response
    {
        return new Response(json_encode(['jsonrpc' => '2.0', 'id' => 2, 'result' => $result], JSON_THROW_ON_ERROR));
    }
}
