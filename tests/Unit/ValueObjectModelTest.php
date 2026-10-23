<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TeamBlue\JsonImmutableObjects\TypeDetector;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Model\TestCategory;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Model\TestExclusiveRangeInt;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Model\TestExternalId;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Model\TestPlainIdentifier;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Model\TestRangeInt;
use TeamBlue\OpenApi\Codegen\Tests\Unit\Model\TestScore;
use Throwable;

/**
 * Pins the shape that model.mustache's `x-is-value-object` branch generates for a
 * non-object referenced schema (enum, numeric range, pattern, recognized format,
 * plain opt-in). The fixtures under Model/ are hand-written copies of that output
 * shape, exercised here against the real team-blue/php-value-objects runtime.
 *
 * Each value-object base class has its own exception hierarchy (RangeValue/
 * PatternValue throw TeamBlue\ValueObjects\Exception\ValueObjectException, the enum
 * base throws a Symfony HttpException, UuidValue delegates to ramsey/uuid's own
 * exception) - rejection assertions use \Throwable to stay agnostic of that.
 */
class ValueObjectModelTest extends TestCase
{
    public function testEnumRoundTripsAndRejectsInvalidValue(): void
    {
        $category = TestCategory::fromString(TestCategory::CMS);

        $this->assertSame(TestCategory::CMS, $category->toString());
        $this->assertSame(TestCategory::VPS, TestCategory::fromValue(TestCategory::VPS)->toValue());

        $this->expectException(Throwable::class);
        TestCategory::fromString('NOT_A_CATEGORY');
    }

    public function testEnumIsRecognizedByJsonSchemaTypeDetector(): void
    {
        // This is the mechanism that lets a generated `Category $category` property
        // work inside a JsonSchemaAwareRecord with zero extra glue code: TypeDetector
        // recognizes any EnumValue-implementing class and derives its JSON schema
        // straight from possibleValues().
        $type = TypeDetector::typeFromClass(TestCategory::class);
        $schema = $type->toArray();

        $this->assertSame('string', $schema['type']);
        $this->assertSame([TestCategory::CMS, TestCategory::VPS], $schema['enum']);
    }

    public function testRangeAcceptsInBoundsAndRejectsOutOfBounds(): void
    {
        $this->assertSame(42, TestRangeInt::fromInt(42)->toInt());
        $this->assertSame(1, TestRangeInt::fromInt(1)->toInt());
        $this->assertSame(100, TestRangeInt::fromInt(100)->toInt());

        $this->expectException(Throwable::class);
        TestRangeInt::fromInt(101);
    }

    public function testExclusiveRangeRejectsBoundaryValues(): void
    {
        $this->assertSame(5, TestExclusiveRangeInt::fromInt(5)->toInt());

        $this->expectException(Throwable::class);
        TestExclusiveRangeInt::fromInt(10);
    }

    public function testPatternAcceptsMatchAndRejectsMismatch(): void
    {
        $this->assertSame('42%', TestScore::fromString('42%')->toString());

        $this->expectException(Throwable::class);
        TestScore::fromString('not-a-score');
    }

    public function testFormatBasedValueObjectValidatesAndRoundTrips(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $this->assertSame($uuid, TestExternalId::fromString($uuid)->toString());

        $this->expectException(Throwable::class);
        TestExternalId::fromString('not-a-uuid');
    }

    public function testPlainOptInValueObjectWrapsWithoutExtraConstraints(): void
    {
        $this->assertSame('abc-123', TestPlainIdentifier::fromString('abc-123')->toString());
        $this->assertTrue(
            TestPlainIdentifier::fromString('abc-123')->isEqualTo(TestPlainIdentifier::fromString('abc-123')),
        );
    }
}
