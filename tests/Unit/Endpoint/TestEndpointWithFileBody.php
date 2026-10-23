<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint;

use SplFileObject;
use TeamBlue\OpenApi\Codegen\Endpoint\Endpoint;
use TeamBlue\OpenApi\Codegen\Endpoint\EndpointLogic;

class TestEndpointWithFileBody implements Endpoint
{
    use EndpointLogic;

    private string $method = 'POST';

    private string $uri = '/test-with-file-body/{idTest}';

    private int $idTest;
    private SplFileObject $body;

    /** @inheritDoc */
    public function toRequestParameters(): array
    {
        return [
            'idTest' => $this->idTest,
            'body' => $this->body,
        ];
    }
}
