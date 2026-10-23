<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TeamBlue\ClientMock\Mock;
use TeamBlue\OpenApi\Codegen\Client\NullClientWrapper;
use TeamBlue\OpenApi\Codegen\ClientBuilder\UnknownClientTag;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\Client;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilder;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilderWithNonExistingConfig;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\SecondClient;

class ClientBuilderTest extends TestCase
{
    public function testBuildReturnsCorrectClientPerTag(): void
    {
        $clientBuilder = new ClientBuilder(new NullClientWrapper());

        $this->assertInstanceOf(Client::class, $clientBuilder->first());
        $this->assertInstanceOf(SecondClient::class, $clientBuilder->second());
    }

    public function testBuildCachesPerTagWithinSameInstance(): void
    {
        $clientBuilder = new ClientBuilder(new NullClientWrapper());

        $this->assertSame($clientBuilder->first(), $clientBuilder->first());
    }

    public function testBuildDoesNotCacheAcrossTags(): void
    {
        $clientBuilder = new ClientBuilder(new NullClientWrapper());

        $this->assertNotSame($clientBuilder->first(), $clientBuilder->second());
    }

    public function testBuildDoesNotCacheAcrossBuilderInstances(): void
    {
        $first = new ClientBuilder(new NullClientWrapper());
        $second = new ClientBuilder(new NullClientWrapper());

        $this->assertNotSame($first->first(), $second->first());
    }

    public function testBuildClientWithNonExistingConfig(): void
    {
        $this->expectExceptionMessageMatches('/Could not load configs for path/');
        $clientBuilder = new ClientBuilderWithNonExistingConfig(new NullClientWrapper());
        $clientBuilder->first();
    }

    public function testMockReturnsIndependentInstancesPerCall(): void
    {
        $first = ClientBuilder::mock('first');
        $second = ClientBuilder::mock('first');

        $this->assertInstanceOf(Mock::class, $first);
        $this->assertNotSame($first, $second);
    }

    public function testMockUnknownTagThrows(): void
    {
        $this->expectException(UnknownClientTag::class);

        ClientBuilder::mock('unknown');
    }
}
