<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\ClientBuilder;

use RuntimeException;

use function sprintf;

final class UnknownClientTag extends RuntimeException
{
    public static function forTag(string $tag): self
    {
        return new self(sprintf('No client is registered for tag \'%s\'.', $tag));
    }
}
