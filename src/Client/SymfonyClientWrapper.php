<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function assert;
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
        $response = $this->client->request($method, $uri, $options);

        return $this
            ->setResponse($response)
            ->contentFromResponse($response);
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
