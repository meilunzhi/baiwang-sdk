<?php

namespace Melon\Baiwang;

final class Signer
{
    /**
     * Sign an OpenAPI request using the same sorted-parameter algorithm as the original SDK.
     *
     * @param array<string, mixed> $params
     * @param string $appSecret
     * @param mixed|null $bizContent
     */
    public static function sign(array $params, string $appSecret, $bizContent = null): string
    {
        ksort($params, SORT_STRING);
        $stringToSign = $appSecret;

        foreach ($params as $key => $value) {
            if (self::isNull($key) || self::isNull($value)) {
                continue;
            }
            $stringToSign .= $key . self::stringify($value);
        }

        if ($bizContent !== null && $bizContent !== false && $bizContent !== '') {
            $stringToSign .= self::jsonEncode($bizContent);
        }

        return strtoupper(md5($stringToSign . $appSecret));
    }

    /**
     * @param mixed $value
     */
    public static function isNull($value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param mixed $value
     */
    public static function jsonEncode($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \InvalidArgumentException('Unable to JSON encode Baiwang request data: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * @param mixed $value
     */
    private static function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return self::jsonEncode($value);
        }
        return (string) $value;
    }

    private function __construct()
    {
    }
}
