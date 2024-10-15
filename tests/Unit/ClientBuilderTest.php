<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TeamBlue\OpenApi\Codegen\Client\NullClientWrapper;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\Client;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilder;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilderWithNonExistingConfig;

class ClientBuilderTest extends TestCase
{
    public function testBuildClient(): void
    {
        $clientBuilder = new ClientBuilder(new NullClientWrapper());

        $this->assertInstanceOf(ClientBuilder::class, $clientBuilder);

        $client = $clientBuilder->build();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testBuildClientWithNonExistingConfig(): void
    {
        $this->expectExceptionMessageMatches('/Could not load configs for path/');
        $clientBuilder = new ClientBuilderWithNonExistingConfig(new NullClientWrapper());
        $clientBuilder->build();
    }
}
