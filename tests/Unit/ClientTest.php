<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SplFileObject;
use TeamBlue\OpenApi\Codegen\Client\NullClientWrapper;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilder;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilderWithoutConfigs;

use function stream_get_meta_data;

class ClientTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    public static function requestResponseProvider(): array
    {
        return [
            'default' => [
                'response' => [],
                'return' => [],
            ],
            'hal' => [
                'response' => [
                    '_links' => [
                        'self' => ['href' => '/test/1'],
                    ],
                    '_embedded' => [
                        'author' => [
                            '_links' => [
                                'self' => ['href' => '/test/2'],
                            ],
                            'id' => 1,
                            'name' => 'test',
                            'homepage' => 'http://test.com',
                        ],
                    ],
                ],
                'return' => [
                    'id' => 1,
                    'name' => 'test',
                    'homepage' => 'http://test.com',
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $response
     * @param array<mixed> $return
     *
     * @dataProvider requestResponseProvider
     */
    public function testTestEndpoint(array $response, array $return): void
    {
        $wrapper = $this->createMock(NullClientWrapper::class);
        $wrapper
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'test/1',
                ['query' => ['testQuery' => 'ziezo']],
            )
            ->willReturn($response);

        $client = (new ClientBuilder($wrapper))->build();
        $clientResponse = $client->test();

        $this->assertEquals($return, $clientResponse);
    }

    public function testTestEndpointWithoutConfigs(): void
    {
        $client = (new ClientBuilderWithoutConfigs(new NullClientWrapper()))->build();
        $clientResponse = $client->test();

        $this->assertIsArray($clientResponse);
        $this->assertEmpty($clientResponse);
    }

    public function testTestEndpointWithEmptyBody(): void
    {
        $wrapper = $this->createMock(NullClientWrapper::class);
        $wrapper
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'test-with-array-body/1',
                ['json' => []],
            )
            ->willReturn([]);

        $client = (new ClientBuilder($wrapper))->build();
        $client->testWithEmptyBody();
    }

    public function testTestEndpointWithFileBody(): void
    {
        $file = new SplFileObject(__FILE__);

        $wrapper = $this->createMock(NullClientWrapper::class);
        $wrapper
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'test-with-file-body/1',
                $this->callback(function (array $options) use ($file): bool {
                    $this->assertArrayHasKey('body', $options);
                    $this->assertIsResource($options['body']);
                    $this->assertSame($file->getPathname(), stream_get_meta_data($options['body'])['uri']);

                    return true;
                }),
            )
            ->willReturn([]);

        $client = (new ClientBuilder($wrapper))->build();
        $client->testWithFileBody($file);
    }
}
