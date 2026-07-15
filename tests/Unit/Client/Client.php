<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use SplFileObject;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestEndpoint;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestEndpointWithArrayBody;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestEndpointWithFileBody;

class Client extends \TeamBlue\OpenApi\Codegen\Client\Client
{
    public function test(): mixed
    {
        return $this->performRequest(
            TestEndpoint::fromArray(
                [
                    'idTest' => 1,
                    'query' => ['testQuery' => 'ziezo'],
                ],
            ),
        );
    }

    public function testWithEmptyBody(): mixed
    {
        return $this->performRequest(
            TestEndpointWithArrayBody::fromArray(
                [
                    'idTest' => 1,
                    'body' => [],
                ],
            ),
        );
    }

    public function testWithFileBody(SplFileObject $file): mixed
    {
        return $this->performRequest(
            TestEndpointWithFileBody::fromRecordData(
                [
                    'idTest' => 1,
                    'body' => $file,
                ],
            ),
        );
    }
}
