<?php

namespace Melon\Baiwang\Contracts;

interface CacheInterface
{
    /**
     * Return a cached value, or null when it is not present or expired.
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key);

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl Lifetime in seconds.
     */
    public function set(string $key, $value, int $ttl): void;
}
