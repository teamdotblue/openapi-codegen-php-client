<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client;

use Closure;
use EventEngine\Data\ImmutableRecord;
use RuntimeException;
use SplFileObject;
use TeamBlue\OpenApi\Codegen\Client\Exception\ErrorResponse;
use TeamBlue\OpenApi\Codegen\Endpoint\Endpoint;
use TeamBlue\Util\ArrayUtil;
use Throwable;

use function array_key_exists;
use function array_map;
use function array_shift;
use function file_get_contents;
use function is_array;
use function is_string;
use function sprintf;

/** @SuppressWarnings("PHPMD.CyclomaticComplexity") */
abstract class Client
{
    private const ENDPOINT_PROPERTIES_OPTIONS_MAP = [
        'query' => 'query',
        'body' => 'json',
        'form' => 'form_params',
    ];

    /** @var array<string, string> */
    private array $headers = [];

    /** @param array<string, mixed> $configs */
    public function __construct(
        private readonly ClientWrapper $client,
        private readonly array $configs = [],
        private readonly Closure|null $optionBuilder = null,
    ) {
    }

    /** @param array<string, class-string<Throwable>> $exceptionMapping */
    protected function performRequest(Endpoint $endpoint, array $exceptionMapping = []): mixed
    {
        $method = $endpoint->method();
        $uri = $endpoint->uri();
        $options = $this->buildOptions($endpoint);

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (ErrorResponse $exception) {
            throw $this->translateErrorResponse($exception, $exceptionMapping);
        }

        if (($this->configs['transformHal'] ?? false) && is_array($response)) {
            return $this->transformHal($response);
        }

        return $response;
    }

    /**
     * Translates a caught transport error into the generated exception matching the
     * response body's "type" value. Falls back to the original transport exception
     * unchanged when there is no match, so behaviour for anything not covered by
     * $exceptionMapping is identical to before this translation existed.
     *
     * @param array<string, class-string<Throwable>> $exceptionMapping
     */
    private function translateErrorResponse(ErrorResponse $exception, array $exceptionMapping): Throwable
    {
        $type = $exception->decodedBody()['type'] ?? null;
        $exceptionClass = is_string($type) ? ($exceptionMapping[$type] ?? null) : null;

        if ($exceptionClass === null) {
            return $exception->getPrevious() ?? $exception;
        }

        /** @phpstan-ignore-next-line */
        return $exceptionClass::fromArray($exception->decodedBody());
    }

    /**
     * @param array<mixed> $content
     *
     * @return array<mixed>
     */
    private function transformHal(array $content): array
    {
        if (isset($content['_embedded']) && is_array($content['_embedded'])) {
            $content = array_shift($content['_embedded']);
        }

        unset($content['_links']);

        if (! ArrayUtil::isAssociative($content)) {
            foreach ($content as &$contentPart) {
                unset($contentPart['_links']);
            }
        }

        return $content;
    }

    public function addHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /** @return array<mixed> */
    protected function buildOptions(Endpoint $endpoint): array
    {
        $options = $this->optionBuilder ? ($this->optionBuilder)($endpoint) : [];

        if (! isset($options['headers']) && ! empty($this->headers)) {
            $options['headers'] = [];
        }

        foreach ($this->headers as $name => $value) {
            $options['headers'][$name] = $value;
        }

        $this->headers = [];

        foreach (self::ENDPOINT_PROPERTIES_OPTIONS_MAP as $propertyName => $option) {
            $data = $endpoint->{$propertyName}();
            if (array_key_exists($option, $options) || $data === null) {
                continue;
            }

            if ($data instanceof SplFileObject) {
                $options['body'] = $this->readFile($data);

                continue;
            }

            $options[$option] = $this->buildOptionData($endpoint, $propertyName, $data);
        }

        return $options;
    }

    /**
     * Reads the file as a string rather than handing over a raw resource/stream: several
     * ClientWrapper implementations (e.g. the BrowserKit-backed test client used by
     * api-platform/core) only accept a string body, not a resource.
     */
    private function readFile(SplFileObject $file): string
    {
        $contents = file_get_contents($file->getPathname());

        if ($contents === false) {
            throw new RuntimeException(
                sprintf('Could not read file \'%s\'.', $file->getPathname()),
            );
        }

        return $contents;
    }

    /** @return array<mixed> */
    private function buildOptionData(Endpoint $endpoint, string $propertyName, mixed $data): array
    {
        if ($data instanceof ImmutableRecord) {
            $data = $data->toArray();
        }

        if (! is_array($data)) {
            throw new RuntimeException(
                sprintf(
                    'Client data for property \'%s\' and method \'%s\' should be an array.',
                    $propertyName,
                    $endpoint->method(),
                ),
            );
        }

        $data = ArrayUtil::rejectNullValues($data);

        if (! ArrayUtil::isAssociative($data)) {
            return array_map(
                static fn ($dataItem) => $dataItem instanceof ImmutableRecord ? $dataItem->toArray() : $dataItem,
                $data,
            );
        }

        return ArrayUtil::removeSuffixFromKeys($data, '[]');
    }
}
