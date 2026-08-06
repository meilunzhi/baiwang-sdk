<?php

namespace Melon\Baiwang\Contracts;

use Melon\Baiwang\HttpResponse;

interface HttpClientInterface
{
    /**
     * @param string $url
     * @param string $body JSON request body.
     * @param array<string, string> $headers
     * @param int $timeout Timeout in milliseconds.
     */
    public function post(string $url, string $body, array $headers, int $timeout): HttpResponse;
}
