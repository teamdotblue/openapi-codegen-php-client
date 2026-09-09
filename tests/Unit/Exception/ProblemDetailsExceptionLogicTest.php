<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Throwable;

class ProblemDetailsExceptionLogicTest extends TestCase
{
    public function testGetMessageReturnsDetail(): void
    {
        $exception = TestProblem::fromArray([
            'type' => '/problem/widget-exists',
            'title' => 'Widget exists',
            'detail' => "A widget named 'foo' already exists.",
            'status' => 409,
        ]);

        self::assertSame("A widget named 'foo' already exists.", $exception->getMessage());
        self::assertInstanceOf(Throwable::class, $exception);
    }

    public function testToArrayRoundTripsOnlyTheDeclaredProperties(): void
    {
        $data = [
            'type' => '/problem/widget-exists',
            'title' => 'Widget exists',
            'detail' => "A widget named 'foo' already exists.",
            'status' => 409,
        ];

        $exception = TestProblem::fromArray($data);

        self::assertSame($data, $exception->toArray());
    }

    public function testIsCatchableLikeAnyOtherException(): void
    {
        $exception = TestProblem::fromArray([
            'type' => '/problem/widget-exists',
            'title' => 'Widget exists',
            'detail' => 'Already there.',
            'status' => 409,
        ]);

        try {
            throw $exception;
        } catch (TestProblem $caught) {
            self::assertSame($exception, $caught);
        }
    }
}
