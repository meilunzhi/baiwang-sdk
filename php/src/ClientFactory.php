<?php

namespace Melon\Baiwang;

use Melon\Baiwang\Contracts\CacheInterface;
use Melon\Baiwang\Contracts\HttpClientInterface;

final class ClientFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config, ?HttpClientInterface $httpClient = null, ?CacheInterface $cache = null): BaiwangClient
    {
        return new BaiwangClient($config, $httpClient, $cache);
    }

    private function __construct()
    {
    }
}
