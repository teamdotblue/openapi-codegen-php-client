<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Client\Exception;

use Exception;
use Throwable;

use function sprintf;

/**
 * Internal carrier thrown by ClientWrapper implementations when the underlying HTTP
 * client reports a non-2xx response with a decodable JSON body. Client::performRequest()
 * catches this to look up a matching generated exception class by the body's "type"
 * value; when there is no match, it rethrows the original transport exception
 * (getPrevious()) unchanged, so this class is never meant to reach calling code.
 */
final class ErrorResponse extends Exception
{
    /** @param array<mixed>|null $decodedBody */
    public function __construct(
        private readonly int $statusCode,
        private readonly array|null $decodedBody,
        Throwable $previous,
    ) {
        parent::__construct(
            sprintf('The server responded with status code %d.', $statusCode),
            0,
            $previous,
        );
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<mixed>|null */
    public function decodedBody(): array|null
    {
        return $this->decodedBody;
    }
}
