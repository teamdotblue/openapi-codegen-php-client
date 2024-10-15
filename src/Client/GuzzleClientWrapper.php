<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

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
        $response = $this->client->request($method, $uri, $options);

        return $this
            ->setResponse($response)
            ->contentFromResponse($response);
    }

    private function contentFromResponse(ResponseInterface $response): mixed
    {
        $response->getBody()->rewind();
        $contents = $response->getBody()->getContents();

        if (empty($contents)) {
            return [];
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
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
