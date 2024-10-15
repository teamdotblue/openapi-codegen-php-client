<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use TeamBlue\ClientMock\Mock;
use TeamBlue\ClientMock\MockPersister;
use Unit\Client\ClientMock;

class ClientBuilder extends \TeamBlue\OpenApi\Codegen\ClientBuilder\ClientBuilder
{
    public function build(): Client
    {
        return new Client(
            $this->client(),
            $this->configs(__DIR__ . '/config.json'),
        );
    }

    public static function mock(): Mock
    {
        return new ClientMock(new MockPersister());
    }
}
