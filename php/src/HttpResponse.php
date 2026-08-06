<?php

namespace Melon\Baiwang;

final class HttpResponse
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $body;

    /**
     * @param int $statusCode
     * @param string $body
     */
    public function __construct(int $statusCode, string $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
