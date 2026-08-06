<?php

namespace Melon\Baiwang\Http;

use Melon\Baiwang\Contracts\HttpClientInterface;
use Melon\Baiwang\Exception\HttpException;
use Melon\Baiwang\HttpResponse;

final class CurlHttpClient implements HttpClientInterface
{
    public function post(string $url, string $body, array $headers, int $timeout): HttpResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new HttpException('Unable to initialize cURL.');
        }

        $headerLines = array();
        foreach ($headers as $name => $value) {
            if ($value !== null && $value !== '') {
                $headerLines[] = $name . ': ' . $value;
            }
        }

        curl_setopt_array($handle, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout,
        ));

        $responseBody = curl_exec($handle);
        $error = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($responseBody === false) {
            throw new HttpException($error !== '' ? $error : 'Baiwang HTTP request failed.', $statusCode);
        }

        return new HttpResponse($statusCode, $responseBody);
    }
}
