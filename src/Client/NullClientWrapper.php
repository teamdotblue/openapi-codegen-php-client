<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client;

class NullClientWrapper implements ClientWrapper
{
    /** @inheritDoc */
    public function request(string $method, string $uri, array $options = []): mixed
    {
        return [];
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
     * @phpstan-ignore-next-line
     */
    public function response()
    {
        return null;
    }
}
