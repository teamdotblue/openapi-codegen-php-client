<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TeamBlue\OpenApi\Codegen\Client\Exception\ErrorResponse;
use TeamBlue\OpenApi\Codegen\Client\GuzzleClientWrapper;

use function assert;
use function json_encode;

class GuzzleClientWrapperTest extends TestCase
{
    public function testTranslatesAnErrorResponseBodyIntoAnErrorResponse(): void
    {
        $request = new Request('POST', '/widgets');
        $response = new Response(
            409,
            ['Content-Type' => 'application/json'],
            (string) json_encode(['type' => '/problem/widget-exists']),
        );
        $clientException = new ClientException('Conflict', $request, $response);

        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willThrowException($clientException);

        $wrapper = new GuzzleClientWrapper($client);

        try {
            $wrapper->request('POST', '/widgets');
            self::fail('Expected an ErrorResponse.');
        } catch (ErrorResponse $exception) {
            self::assertSame(409, $exception->statusCode());
            self::assertSame(['type' => '/problem/widget-exists'], $exception->decodedBody());
            self::assertSame($clientException, $exception->getPrevious());
        }
    }

    public function testCarriesANullDecodedBodyWhenTheResponseHasNoContent(): void
    {
        $request = new Request('POST', '/widgets');
        $response = new Response(400);
        $clientException = new ClientException('Bad request', $request, $response);

        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willThrowException($clientException);

        $wrapper = new GuzzleClientWrapper($client);

        try {
            $wrapper->request('POST', '/widgets');
            self::fail('Expected an ErrorResponse.');
        } catch (ErrorResponse $exception) {
            self::assertSame(400, $exception->statusCode());
            self::assertNull($exception->decodedBody());
            self::assertSame($clientException, $exception->getPrevious());
        }
    }

    public function testLeavesTheErrorResponseBodyReadableForRetryLogic(): void
    {
        $request = new Request('POST', '/widgets');
        $response = new Response(
            404,
            ['Content-Type' => 'application/json'],
            (string) json_encode(['type' => '/errors/404', 'detail' => 'Not found.']),
        );
        $clientException = new ClientException('Not found', $request, $response);

        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willThrowException($clientException);

        $wrapper = new GuzzleClientWrapper($client);

        try {
            $wrapper->request('POST', '/widgets');
            self::fail('Expected an ErrorResponse.');
        } catch (ErrorResponse $exception) {
            $previous = $exception->getPrevious();
            assert($previous instanceof ClientException);

            $rethrownResponse = $previous->getResponse();

            self::assertSame(
                (string) json_encode(['type' => '/errors/404', 'detail' => 'Not found.']),
                $rethrownResponse->getBody()->getContents(),
                'The rethrown exception must still expose a readable response body.',
            );
        }
    }
}
