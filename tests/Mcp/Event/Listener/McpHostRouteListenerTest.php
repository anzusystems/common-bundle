<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Mcp\Event\Listener;

use AnzuSystems\CommonBundle\Mcp\Event\Listener\McpHostRouteListener;
use AnzuSystems\CommonBundle\Mcp\Resolver\McpHostResolver;
use AnzuSystems\CommonBundle\Tests\AnzuKernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class McpHostRouteListenerTest extends AnzuKernelTestCase
{
    private const string MCP_HOST = 'core-cms-mcp.localhost';
    private const string APP_HOST = 'core-cms.localhost';
    private const string PATH = '/api/adm/v1/quiz';

    /**
     * @dataProvider requestProvider
     */
    public function testOnlyMarkedRoutesPassOnTheMcpPodOrHost(
        bool $mcpPod,
        string $host,
        bool $marked,
        int $requestType,
        bool $expectNotFound,
    ): void {
        $request = Request::create(sprintf('http://%s%s', $host, self::PATH));
        $request->attributes->set(McpHostRouteListener::ALLOWED_ON_MCP_HOST, $marked);
        $listener = new McpHostRouteListener(new McpHostResolver([self::MCP_HOST]), $mcpPod);

        if ($expectNotFound) {
            $this->expectException(NotFoundHttpException::class);
        }
        $listener(new RequestEvent(
            kernel: self::bootKernel(),
            request: $request,
            requestType: $requestType,
        ));

        self::assertFalse($expectNotFound);
    }

    /**
     * @return array<string, array{bool, string, bool, int, bool}>
     */
    public static function requestProvider(): array
    {
        return [
            'unmarked route on the mcp host' => [false, self::MCP_HOST, false, HttpKernelInterface::MAIN_REQUEST, true],
            'mcp host with a root dot' => [false, self::MCP_HOST . '.', false, HttpKernelInterface::MAIN_REQUEST, true],
            'marked route on the mcp host' => [false, self::MCP_HOST, true, HttpKernelInterface::MAIN_REQUEST, false],
            'other host on the mcp pod' => [true, self::APP_HOST, false, HttpKernelInterface::MAIN_REQUEST, true],
            'marked route on the mcp pod' => [true, self::APP_HOST, true, HttpKernelInterface::MAIN_REQUEST, false],
            'app host on an app pod' => [false, self::APP_HOST, false, HttpKernelInterface::MAIN_REQUEST, false],
            'sub-request on the mcp pod' => [true, self::APP_HOST, false, HttpKernelInterface::SUB_REQUEST, false],
        ];
    }
}
