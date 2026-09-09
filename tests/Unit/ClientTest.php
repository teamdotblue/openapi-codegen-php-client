<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplFileObject;
use TeamBlue\OpenApi\Codegen\Client\Exception\ErrorResponse;
use TeamBlue\OpenApi\Codegen\Client\NullClientWrapper;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilder;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Client\ClientBuilderWithoutConfigs;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Exception\TestProblem;

use function file_get_contents;

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

        $client = (new ClientBuilder($wrapper))->first();
        $clientResponse = $client->test();

        $this->assertEquals($return, $clientResponse);
    }

    public function testTestEndpointWithoutConfigs(): void
    {
        $client = (new ClientBuilderWithoutConfigs(new NullClientWrapper()))->first();
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

        $client = (new ClientBuilder($wrapper))->first();
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
                    $this->assertIsString($options['body']);
                    $this->assertSame(file_get_contents($file->getPathname()), $options['body']);

                    return true;
                }),
            )
            ->willReturn([]);

        $client = (new ClientBuilder($wrapper))->first();
        $client->testWithFileBody($file);
    }

    public function testTranslatesAMappedErrorResponseIntoTheGeneratedException(): void
    {
        $wrapper = $this->createMock(NullClientWrapper::class);
        $wrapper
            ->method('request')
            ->willThrowException(new ErrorResponse(
                409,
                [
                    'type' => '/problem/widget-exists',
                    'title' => 'Widget exists',
                    'detail' => 'Already there.',
                    'status' => 409,
                ],
                new RuntimeException('original transport error'),
            ));

        $client = (new ClientBuilder($wrapper))->first();

        $this->expectException(TestProblem::class);
        $this->expectExceptionMessage('Already there.');

        $client->testWithExceptionMapping(['/problem/widget-exists' => TestProblem::class]);
    }

    public function testRethrowsTheOriginalExceptionWhenTheTypeIsNotMapped(): void
    {
        $originalException = new RuntimeException('original transport error');

        $wrapper = $this->createMock(NullClientWrapper::class);
        $wrapper
            ->method('request')
            ->willThrowException(new ErrorResponse(
                409,
                ['type' => '/problem/something-else'],
                $originalException,
            ));

        $client = (new ClientBuilder($wrapper))->first();

        try {
            $client->testWithExceptionMapping(['/problem/widget-exists' => TestProblem::class]);
            self::fail('Expected the original exception.');
        } catch (RuntimeException $caught) {
            self::assertSame($originalException, $caught);
        }
    }
}
