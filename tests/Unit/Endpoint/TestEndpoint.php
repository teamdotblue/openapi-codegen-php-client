<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint;

use TeamBlue\OpenApi\Codegen\Endpoint\Endpoint;
use TeamBlue\OpenApi\Codegen\Endpoint\EndpointLogic;

class TestEndpoint implements Endpoint
{
    use EndpointLogic;

    private string $method = 'GET';

    private string $uri = '/test/{idTest}';

    private int $idTest;
    private TestQuery|null $query = null;
    private TestData|null $body = null;
    private TestData|null $form = null;

    /** @inheritDoc */
    public function toRequestParameters(): array
    {
        return [
            'idTest' => $this->idTest,
            'query' => $this->query,
            'body' => $this->body,
            'form' => $this->form,
        ];
    }
}
