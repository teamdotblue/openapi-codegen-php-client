<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Model;

use TeamBlue\ValueObjects\Implementation\Int\RangeValue;

final class TestExclusiveRangeInt extends RangeValue
{
    public static function minimum(): int
    {
        return 0;
    }

    public static function maximum(): int
    {
        return 10;
    }

    public static function included(): bool
    {
        return false;
    }
}
