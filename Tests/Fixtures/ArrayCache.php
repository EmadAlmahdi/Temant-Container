<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * A minimal in-memory PSR-16 cache for tests.
 *
 * Values are stored serialized, so tests exercise the same serialize/unserialize
 * round-trip a real persistent cache would.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, string> */
    private array $store = [];

    public int $writes = 0;
    public int $reads = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->reads++;

        if (!isset($this->store[$key])) {
            return $default;
        }

        return unserialize($this->store[$key]);
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->writes++;
        $this->store[$key] = serialize($value);

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }
}
