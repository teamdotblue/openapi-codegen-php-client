<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Model;

use TeamBlue\ValueObjects\Implementation\Enum\StringEnumValue;

final class TestCategory extends StringEnumValue
{
    public const CMS = 'CMS';
    public const VPS = 'VPS';

    /** @inheritDoc */
    public static function possibleValues(): array
    {
        return [
            self::CMS,
            self::VPS,
        ];
    }
}
