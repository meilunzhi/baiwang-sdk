<?php

namespace Melon\Baiwang\Exception;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @var array<string, mixed> */
    private $response;

    /**
     * @param string $message
     * @param array<string, mixed> $response
     */
    public function __construct(string $message, array $response)
    {
        $code = isset($response['code']) && is_numeric($response['code']) ? (int) $response['code'] : 0;
        parent::__construct($message, $code);
        $this->response = $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}
