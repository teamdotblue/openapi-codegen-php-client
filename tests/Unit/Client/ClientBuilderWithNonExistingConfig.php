<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use TeamBlue\ClientMock\Mock;
use TeamBlue\ClientMock\MockPersister;
use TeamBlue\OpenApi\Codegen\ClientBuilder\ClientBuilder;
use Unit\Client\ClientMock;

class ClientBuilderWithNonExistingConfig extends ClientBuilder
{
    public function build(): Client
    {
        return new Client(
            $this->client(),
            $this->configs(__DIR__ . '/config-non-exist.json'),
        );
    }

    public static function mock(): Mock
    {
        return new ClientMock(new MockPersister());
    }
}
