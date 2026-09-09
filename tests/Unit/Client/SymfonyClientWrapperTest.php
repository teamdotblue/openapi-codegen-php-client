<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use TeamBlue\OpenApi\Codegen\Client\Exception\ErrorResponse;
use TeamBlue\OpenApi\Codegen\Client\SymfonyClientWrapper;

use function json_encode;

class SymfonyClientWrapperTest extends TestCase
{
    public function testTranslatesAnErrorResponseBodyIntoAnErrorResponse(): void
    {
        $body = (string) json_encode(['type' => '/problem/widget-exists']);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(409);

        $httpException = new TestSymfonyHttpError($response);
        $callCount = 0;
        $response->method('getContent')->willReturnCallback(
            static function () use (&$callCount, $httpException, $body) {
                $callCount++;

                if ($callCount === 1) {
                    throw $httpException;
                }

                return $body;
            },
        );

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $wrapper = new SymfonyClientWrapper($client);

        try {
            $wrapper->request('POST', '/widgets');
            self::fail('Expected an ErrorResponse.');
        } catch (ErrorResponse $exception) {
            self::assertSame(409, $exception->statusCode());
            self::assertSame(['type' => '/problem/widget-exists'], $exception->decodedBody());
            self::assertSame($httpException, $exception->getPrevious());
        }
    }

    public function testCarriesANullDecodedBodyWhenTheResponseHasNoContent(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);

        $httpException = new TestSymfonyHttpError($response);
        $callCount = 0;
        $response->method('getContent')->willReturnCallback(
            static function () use (&$callCount, $httpException) {
                $callCount++;

                if ($callCount === 1) {
                    throw $httpException;
                }

                return '';
            },
        );

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $wrapper = new SymfonyClientWrapper($client);

        try {
            $wrapper->request('POST', '/widgets');
            self::fail('Expected an ErrorResponse.');
        } catch (ErrorResponse $exception) {
            self::assertSame(400, $exception->statusCode());
            self::assertNull($exception->decodedBody());
            self::assertSame($httpException, $exception->getPrevious());
        }
    }
}
