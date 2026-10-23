<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;

class TestData implements JsonSchemaAwareRecord
{
    use JsonSchemaAwareRecordLogic;

    private string $test;
    private string|null $testNull = null;
}
