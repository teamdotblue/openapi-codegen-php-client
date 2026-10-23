<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Model;

use TeamBlue\ValueObjects\Implementation\Int\RangeValue;

final class TestRangeInt extends RangeValue
{
    public static function minimum(): int
    {
        return 1;
    }

    public static function maximum(): int
    {
        return 100;
    }
}
