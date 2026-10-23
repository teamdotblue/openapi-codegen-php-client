<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client;

interface ClientWrapper
{
    /** @param array<string, mixed> $options */
    public function request(string $method, string $uri, array $options = []): mixed;

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     * @phpstan-ignore-next-line
     */
    public function response();
}
