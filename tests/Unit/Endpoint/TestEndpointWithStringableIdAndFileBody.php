<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint;

use SplFileObject;
use TeamBlue\OpenApi\Codegen\Endpoint\Endpoint;
use TeamBlue\OpenApi\Codegen\Endpoint\EndpointLogic;

/**
 * Reproduces path parameters built from a Stringable value object (e.g. the value objects used
 * throughout our domain), combined with a file body, since uri() must not rely on toArray() for
 * either.
 */
class TestEndpointWithStringableIdAndFileBody implements Endpoint
{
    use EndpointLogic;

    private string $method = 'POST';

    private string $uri = '/test-with-stringable-id-and-file-body/{idTest}';

    private TestStringableId $idTest;
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
