<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use TeamBlue\OpenApi\Codegen\Client\Exception\ErrorResponse;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

class GuzzleClientWrapper implements ClientWrapper
{
    private ResponseInterface|null $response = null;

    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    /** @inheritDoc */
    public function request(string $method, string $uri, array $options = []): mixed
    {
        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (BadResponseException $exception) {
            throw $this->toErrorResponse($exception);
        }

        return $this
            ->setResponse($response)
            ->contentFromResponse($response);
    }

    private function toErrorResponse(BadResponseException $exception): ErrorResponse
    {
        $response = $exception->getResponse();
        $this->setResponse($response);

        return new ErrorResponse(
            $response->getStatusCode(),
            $this->decodedBodyFromResponse($response),
            $exception,
        );
    }

    private function contentFromResponse(ResponseInterface $response): mixed
    {
        $contents = $this->contentsFromResponse($response);

        if (empty($contents)) {
            return [];
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<mixed>|null */
    private function decodedBodyFromResponse(ResponseInterface $response): array|null
    {
        $contents = $this->contentsFromResponse($response);

        if (empty($contents)) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function contentsFromResponse(ResponseInterface $response): string
    {
        $contents = $this->readBody($response);

        // Never leave a consumed stream behind: callers may inspect the
        // response body of a rethrown exception (e.g. retry logic reading
        // getContents() to decide whether an error is retryable).
        $this->rewindBody($response);

        return $contents;
    }

    private function readBody(ResponseInterface $response): string
    {
        $this->rewindBody($response);

        return $response->getBody()->getContents();
    }

    private function rewindBody(ResponseInterface $response): void
    {
        if (! $response->getBody()->isSeekable()) {
            return;
        }

        $response->getBody()->rewind();
    }

    private function setResponse(ResponseInterface $response): self
    {
        $this->response = $response;

        return $this;
    }

    public function response(): ResponseInterface|null
    {
        return $this->response;
    }
}
