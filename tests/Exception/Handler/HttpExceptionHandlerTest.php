<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Tests\Exception\Handler;

use AnzuSystems\CommonBundle\Exception\Handler\HttpExceptionHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class HttpExceptionHandlerTest extends TestCase
{
    /**
     * @dataProvider getErrorResponseDataProvider
     */
    public function testGetErrorResponse(HttpException $exception, bool $debug, int $expectedStatusCode, string $expectedDetail): void
    {
        $handler = new HttpExceptionHandler($debug);

        $response = $handler->getErrorResponse($exception);
        $content = json_decode((string) $response->getContent(), true);

        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame(HttpExceptionHandler::ERROR, $content['error']);
        self::assertSame($expectedDetail, $content['detail']);
        self::assertNotEmpty($content['contextId']);
    }

    /**
     * @return array<string, array{exception: HttpException, debug: bool, expectedStatusCode: int, expectedDetail: string}>
     */
    public static function getErrorResponseDataProvider(): array
    {
        return [
            'conflict with debug enabled' => [
                'exception' => new ConflictHttpException('Conflict detail message'),
                'debug' => true,
                'expectedStatusCode' => JsonResponse::HTTP_CONFLICT,
                'expectedDetail' => 'Conflict detail message',
            ],
            'conflict with debug disabled' => [
                'exception' => new ConflictHttpException('Conflict detail message'),
                'debug' => false,
                'expectedStatusCode' => JsonResponse::HTTP_CONFLICT,
                'expectedDetail' => 'An error occurred',
            ],
            'service unavailable keeps status code' => [
                'exception' => new ServiceUnavailableHttpException(message: 'Service down'),
                'debug' => true,
                'expectedStatusCode' => JsonResponse::HTTP_SERVICE_UNAVAILABLE,
                'expectedDetail' => 'Service down',
            ],
            'plain http exception keeps custom status code' => [
                'exception' => new HttpException(JsonResponse::HTTP_METHOD_NOT_ALLOWED, 'Method not allowed here'),
                'debug' => false,
                'expectedStatusCode' => JsonResponse::HTTP_METHOD_NOT_ALLOWED,
                'expectedDetail' => 'An error occurred',
            ],
        ];
    }

    public function testGetSupportedExceptionClasses(): void
    {
        self::assertSame([HttpException::class], (new HttpExceptionHandler())->getSupportedExceptionClasses());
    }
}
