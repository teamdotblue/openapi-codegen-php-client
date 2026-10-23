<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Endpoint;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;
use EventEngine\Data\SpecialKeySupport;
use SplFileObject;
use Stringable;

use function array_diff_key;
use function array_flip;
use function get_object_vars;
use function is_scalar;
use function ltrim;
use function sprintf;
use function str_replace;
use function strval;

/**
 * @property string $method
 * @property string $uri
 * @property ImmutableRecord|null $query
 * @property ImmutableRecord|array<mixed>|null $body
 * @property ImmutableRecord|array<mixed>|null $form
 */
trait EndpointLogic
{
    use ImmutableRecordLogic;

    public function method(): string
    {
        return $this->method;
    }

    public function uriTemplate(): string
    {
        return $this->uri;
    }

    public function uri(): string
    {
        $uri = $this->uri;
        // todo if we switch to php8.2 make this a constant. (constants are not allowed in traits for php8.1)
        $diffPathParameterNames = [
            'method',
            'uri',
            'query',
            'body',
            'form',
        ];

        // Not using toArray() here, since it would fail to convert a body or form that is a SplFileObject.
        $data = get_object_vars($this);
        $pathParameters = array_diff_key($data, array_flip($diffPathParameterNames));
        foreach ($pathParameters as $pathParameterName => $pathParameter) {
            if ($this instanceof SpecialKeySupport) {
                $pathParameterName = $this->convertKeyForArray($pathParameterName);
            }

            $uri = str_replace(
                sprintf('{%s}', $pathParameterName),
                is_scalar($pathParameter) || $pathParameter instanceof Stringable
                    ? strval($pathParameter)
                    : '',
                (string) $uri,
            );
        }

        return ltrim((string) $uri, '/');
    }

    public function query(): ImmutableRecord|null
    {
        return $this->query ?? null;
    }

    /** @return ImmutableRecord|array<mixed>|null */
    public function body(): ImmutableRecord|array|SplFileObject|null
    {
        return $this->body ?? null;
    }

    /** @return ImmutableRecord|array<mixed>|null */
    public function form(): ImmutableRecord|array|null
    {
        return $this->form ?? null;
    }
}
