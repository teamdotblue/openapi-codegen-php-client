<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Model;

use TeamBlue\ValueObjects\Implementation\String\PatternValue;

final class TestScore extends PatternValue
{
    public static function pattern(): string
    {
        return '^[0-9]{1,3}%$';
    }
}
