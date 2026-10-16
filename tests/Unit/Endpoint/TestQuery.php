<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint;

use EventEngine\Data\SpecialKeySupport;
use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use TeamBlue\JsonImmutableObjects\JsonSchemaAwareRecordLogic;
use TeamBlue\OpenApi\Codegen\Endpoint\SpecialKeySupportLogic;

class TestQuery implements JsonSchemaAwareRecord, SpecialKeySupport
{
    use JsonSchemaAwareRecordLogic;
    use SpecialKeySupportLogic;

    private string|null $testQuery = null;
    /** @var array<string>|null */
    private array|null $arrayQuery = null;
    /** @var array<string>|null */
    private array|null $explodeQuery = null;

    /** @return array<string, string> */
    private static function arrayPropItemTypeMap(): array
    {
        return ['arrayQuery' => 'string', 'explodeQuery' => 'string'];
    }

    /** @return array<string,string> */
    public function keyMapping(): array
    {
        return [
            'testQuery' => 'testQuery',
            'arrayQuery[]' => 'arrayQuery',
            'explodeQuery' => 'explodeQuery',
        ];
    }
}
