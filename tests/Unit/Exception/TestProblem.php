<?php

declare(strict_types=1);

namespace TeamBlue\OpenApi\Codegen\Tests\Unit\Exception;

use EventEngine\JsonSchema\JsonSchemaAwareRecord;
use Exception;
use TeamBlue\OpenApi\Codegen\Exception\ProblemDetailsExceptionLogic;

final class TestProblem extends Exception implements JsonSchemaAwareRecord
{
    use ProblemDetailsExceptionLogic;

    private string $type;
    private string $title;
    private string $detail;
    private int $status;

    public function type(): string
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    public function status(): int
    {
        return $this->status;
    }
}
