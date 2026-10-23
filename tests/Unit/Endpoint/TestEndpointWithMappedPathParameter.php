<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Endpoint;

use EventEngine\Data\SpecialKeySupport;
use TeamBlue\OpenApi\Codegen\Endpoint\Endpoint;
use TeamBlue\OpenApi\Codegen\Endpoint\EndpointLogic;
use TeamBlue\OpenApi\Codegen\Endpoint\SpecialKeySupportLogic;

/**
 * Mirrors generated endpoints whose OpenAPI path parameter name (snake_case, used in the URI
 * template) differs from the generated PHP property name (camelCase), e.g. PatchZone's
 * `/servers/{server_id}/zones/{zone_id}`.
 */
class TestEndpointWithMappedPathParameter implements Endpoint, SpecialKeySupport
{
    use EndpointLogic;
    use SpecialKeySupportLogic;

    private string $method = 'PATCH';

    private string $uri = '/servers/{server_id}/zones/{zone_id}';

    private string $serverId;
    private string $zoneId;

    /** @inheritDoc */
    public function toRequestParameters(): array
    {
        return [
            'server_id' => $this->serverId,
            'zone_id' => $this->zoneId,
        ];
    }

    /** @return array<string,string> */
    public function keyMapping(): array
    {
        return [
            'server_id' => 'serverId',
            'zone_id' => 'zoneId',
        ];
    }
}
