<?php

namespace Melon\Baiwang\Exception;

use RuntimeException;

final class HttpException extends RuntimeException
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $responseBody;

    public function __construct(string $message, int $statusCode = 0, string $responseBody = '')
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
