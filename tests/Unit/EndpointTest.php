<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SplFileObject;
use TeamBlue\OpenApi\Codegen\Endpoint\Endpoint;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestData;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestEndpoint;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestEndpointWithMappedPathParameter;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestEndpointWithStringableIdAndFileBody;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint\TestStringableId;

class EndpointTest extends TestCase
{
    public function testMethodAndUri(): void
    {
        $endpoint = TestEndpoint::fromArray(
            [
                'idTest' => 1,
                'query' => ['testQuery' => 'test'],
            ],
        );

        $this->assertInstanceOf(Endpoint::class, $endpoint);
        $this->assertEquals('GET', $endpoint->method());
        $this->assertEquals('test/1', $endpoint->uri());
        $this->assertEquals('/test/{idTest}', $endpoint->uriTemplate());
    }

    public function testQuery(): void
    {
        $endpoint = TestEndpoint::fromArray(
            [
                'idTest' => 1,
                'query' => ['arrayQuery[]' => ['test', 'test2']],
            ],
        );

        $this->assertEquals(['test', 'test2'], $endpoint->query()?->toArray()['arrayQuery[]']);
    }

    public function testBody(): void
    {
        $endpoint = TestEndpoint::fromArray(
            [
                'idTest' => 1,
                'body' => ['test' => 'test'],
            ],
        );

        $body = $endpoint->body();

        $this->assertInstanceOf(TestData::class, $body);
        $this->assertEquals('test', $body->toArray()['test']);
    }

    public function testForm(): void
    {
        $endpoint = TestEndpoint::fromArray(
            [
                'idTest' => 1,
                'form' => ['test' => 'test'],
            ],
        );

        $form = $endpoint->form();

        $this->assertInstanceOf(TestData::class, $form);
        $this->assertEquals('test', $form->toArray()['test']);
    }

    /**
     * uri() must build the path from a Stringable path parameter (e.g. a value object), not just
     * from scalars, even when the endpoint also carries a file body.
     */
    public function testUriWithStringablePathParameterAndFileBody(): void
    {
        $endpoint = TestEndpointWithStringableIdAndFileBody::fromRecordData(
            [
                'idTest' => new TestStringableId('abc123'),
                'body' => new SplFileObject(__FILE__),
            ],
        );

        $this->assertEquals('test-with-stringable-id-and-file-body/abc123', $endpoint->uri());
    }

    /**
     * When the OpenAPI path parameter name (used in the URI template, e.g. "server_id") differs
     * from the generated PHP property name (e.g. "serverId"), uri() must still be able to
     * substitute the placeholder using the endpoint's keyMapping().
     */
    public function testUriWithMappedPathParameterNames(): void
    {
        $endpoint = TestEndpointWithMappedPathParameter::fromArray(
            [
                'server_id' => 'localhost',
                'zone_id' => 'example.com.',
            ],
        );

        $this->assertEquals('servers/localhost/zones/example.com.', $endpoint->uri());
    }
}
