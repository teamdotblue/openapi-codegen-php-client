<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

class ClientBuilderWithNonExistingConfig extends ClientBuilder
{
    protected function createClient(string $tag): \TeamBlue\OpenApi\Codegen\Client\Client
    {
        return match ($tag) {
            ClientInterface::TAG => new Client($this->client(), $this->configs(__DIR__ . '/config-non-exist.json')),
            default => parent::createClient($tag),
        };
    }
}
