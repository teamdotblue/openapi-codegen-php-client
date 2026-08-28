<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

class ClientBuilderWithoutConfigs extends ClientBuilder
{
    protected function createClient(string $tag): \TeamBlue\OpenApi\Codegen\Client\Client
    {
        return match ($tag) {
            ClientInterface::TAG => new Client($this->client(), []),
            default => parent::createClient($tag),
        };
    }
}
