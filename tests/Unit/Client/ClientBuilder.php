<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use TeamBlue\ClientMock\Mock;
use TeamBlue\ClientMock\MockPersister;
use TeamBlue\OpenApi\Codegen\ClientBuilder\UnknownClientTag;

class ClientBuilder extends \TeamBlue\OpenApi\Codegen\ClientBuilder\ClientBuilder
{
    public function first(): Client
    {
        /** @var Client $client */
        $client = $this->build(ClientInterface::TAG);

        return $client;
    }

    public function second(): SecondClient
    {
        /** @var SecondClient $client */
        $client =  $this->build(SecondClientInterface::TAG);

        return $client;
    }

    protected function createClient(string $tag): \TeamBlue\OpenApi\Codegen\Client\Client
    {
        return match ($tag) {
            ClientInterface::TAG => new Client($this->client(), $this->configs(__DIR__ . '/config.json')),
            SecondClientInterface::TAG => new SecondClient($this->client(), $this->configs(__DIR__ . '/config.json')),
            default => throw UnknownClientTag::forTag($tag),
        };
    }

    public static function mock(string $tag): Mock
    {
        return match ($tag) {
            ClientInterface::TAG => (new ClientMock())->setPersister(new MockPersister()),
            SecondClientInterface::TAG => (new SecondClientMock())->setPersister(new MockPersister()),
            default => throw UnknownClientTag::forTag($tag),
        };
    }
}
