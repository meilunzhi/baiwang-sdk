<?php

namespace Melon\Baiwang;

use Melon\Baiwang\Contracts\CacheInterface;
use Melon\Baiwang\Contracts\HttpClientInterface;

/**
 * Create a Baiwang SDK client.
 *
 * @param array<string, mixed> $config
 */
function createInvoice(array $config, ?HttpClientInterface $httpClient = null, ?CacheInterface $cache = null): BaiwangClient
{
    return ClientFactory::create($config, $httpClient, $cache);
}
