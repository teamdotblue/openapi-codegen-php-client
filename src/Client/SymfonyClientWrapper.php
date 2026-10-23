<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client;

use JsonException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use TeamBlue\OpenApi\Codegen\Client\Exception\ErrorResponse;

use function array_key_exists;
use function assert;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

class SymfonyClientWrapper implements ClientWrapper
{
    private ResponseInterface|null $response = null;

    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
    }

    /** @inheritDoc */
    public function request(string $method, string $uri, array $options = []): mixed
    {
        $response = $this->client->request($method, $uri, $this->normalizeOptions($options));
        $this->setResponse($response);

        try {
            return $this->contentFromResponse($response);
        } catch (HttpExceptionInterface $exception) {
            throw $this->toErrorResponse($exception);
        }
    }

    private function toErrorResponse(HttpExceptionInterface $exception): ErrorResponse
    {
        return new ErrorResponse(
            $exception->getResponse()->getStatusCode(),
            $this->decodedBodyFromResponse($exception->getResponse()),
            $exception,
        );
    }

    /** @return array<mixed>|null */
    private function decodedBodyFromResponse(ResponseInterface $response): array|null
    {
        $contents = $response->getContent(false);

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

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        if (array_key_exists('form_params', $options) && ! array_key_exists('body', $options)) {
            $options['body'] = $options['form_params'];
            unset($options['form_params']);
        }

        return $options;
    }

    private function contentFromResponse(ResponseInterface $response): mixed
    {
        assert($response instanceof ResponseInterface);

        $responseContent = $response->getContent();

        if (empty($responseContent)) {
            return [];
        }

        return json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR);
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
