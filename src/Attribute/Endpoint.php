<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Endpoint
{
    public function __construct(
        private readonly string $endpointClass,
    ) {
    }

    public function endpointClass(): string
    {
        return $this->endpointClass;
    }
}
