<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use TeamBlue\ClientMock\Mock;
use TeamBlue\ClientMock\MockLogic;

class ClientMock implements Mock
{
    use MockLogic;

    public function mockInterface(): string
    {
        return ClientInterface::class;
    }
}
