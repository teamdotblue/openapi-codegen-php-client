<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Client;

use Exception;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TestSymfonyHttpError extends Exception implements HttpExceptionInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
    ) {
        parent::__construct('HTTP error');
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
