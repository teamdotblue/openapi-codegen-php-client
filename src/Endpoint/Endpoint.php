<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Endpoint;

use EventEngine\Data\ImmutableRecord;
use SplFileObject;

interface Endpoint extends ImmutableRecord
{
    public function method(): string;

    public function uri(): string;

    public function query(): ImmutableRecord|null;

    /** @return ImmutableRecord|array<mixed>|null */
    public function body(): ImmutableRecord|array|SplFileObject|null;

    /** @return ImmutableRecord|array<mixed>|null */
    public function form(): ImmutableRecord|array|null;

    /** @return array<string,mixed> */
    public function toRequestParameters(): array;
}
