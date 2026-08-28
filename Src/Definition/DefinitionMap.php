<?php

declare(strict_types=1);

namespace Temant\Container\Definition;

use Temant\Container\Contract\ResolverContainer;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;

/**
 * Stores service definitions and cached instances.
 *
 * Owns the four kinds of registration the container recognises:
 *
 * - **shared**    -- a factory invoked once; its result is cached (singleton).
 * - **factory**   -- a factory invoked on every retrieval.
 * - **instance**  -- a pre-built object, plus the cache slot for resolved singletons.
 * - **lazy**      -- a factory whose invocation is deferred behind a proxy.
 *
 * This class holds no resolution logic; it is a typed data structure with guards.
 *
 * @internal
 */
final class DefinitionMap
{
    /** @var array<string, callable(ResolverContainer): object> */
    private array $shared = [];

    /** @var array<string, callable(ResolverContainer): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, callable(ResolverContainer): object> */
    private array $lazyFactories = [];

    /**
     * Registers a shared (singleton) factory.
     *
     * @param callable(ResolverContainer): object $factory
     */
    public function share(string $id, callable $factory): void
    {
        $this->shared[$id] = $factory;
    }

    /**
     * Registers a factory that produces a new instance on every retrieval.
     *
     * @param callable(ResolverContainer): object $factory
     */
    public function defineFactory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Registers a pre-built instance.
     */
    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    /**
     * Registers a lazy factory (deferred instantiation).
     *
     * @param callable(ResolverContainer): object $factory
     */
    public function lazy(string $id, callable $factory): void
    {
        $this->lazyFactories[$id] = $factory;
    }

    /**
     * Stores a resolved instance in the singleton cache.
     */
    public function cacheInstance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function hasShared(string $id): bool
    {
        return isset($this->shared[$id]);
    }

    public function hasFactory(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    public function hasInstance(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    public function hasLazy(string $id): bool
    {
        return isset($this->lazyFactories[$id]);
    }

    /**
     * Whether the ID has any kind of registration.
     */
    public function isRegistered(string $id): bool
    {
        return isset($this->shared[$id])
            || isset($this->factories[$id])
            || isset($this->instances[$id])
            || isset($this->lazyFactories[$id]);
    }

    /**
     * @return (callable(ResolverContainer): object)|null
     */
    public function sharedFactory(string $id): ?callable
    {
        return $this->shared[$id] ?? null;
    }

    /**
     * @return (callable(ResolverContainer): object)|null
     */
    public function factoryFor(string $id): ?callable
    {
        return $this->factories[$id] ?? null;
    }

    public function instanceFor(string $id): ?object
    {
        return $this->instances[$id] ?? null;
    }

    /**
     * @return (callable(ResolverContainer): object)|null
     */
    public function lazyFactory(string $id): ?callable
    {
        return $this->lazyFactories[$id] ?? null;
    }

    /**
     * The registration kind for an ID, checked lazy-first.
     *
     * @return 'shared'|'factory'|'instance'|'lazy'|null
     */
    public function typeOf(string $id): ?string
    {
        return match (true) {
            isset($this->lazyFactories[$id]) => 'lazy',
            isset($this->shared[$id]) => 'shared',
            isset($this->factories[$id]) => 'factory',
            isset($this->instances[$id]) => 'instance',
            default => null,
        };
    }

    /**
     * Removes every trace of an ID from this map.
     *
     * @return bool Whether anything was removed.
     */
    public function forget(string $id): bool
    {
        $removed = false;

        if (array_key_exists($id, $this->shared)) {
            unset($this->shared[$id]);
            $removed = true;
        }

        if (array_key_exists($id, $this->factories)) {
            unset($this->factories[$id]);
            $removed = true;
        }

        if (array_key_exists($id, $this->instances)) {
            unset($this->instances[$id]);
            $removed = true;
        }

        if (array_key_exists($id, $this->lazyFactories)) {
            unset($this->lazyFactories[$id]);
            $removed = true;
        }

        return $removed;
    }

    /**
     * Clears cached instances only, keeping definitions.
     */
    public function flushInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Removes all definitions and instances.
     */
    public function clear(): void
    {
        $this->shared = [];
        $this->factories = [];
        $this->instances = [];
        $this->lazyFactories = [];
    }

    /**
     * Every registered ID across shared, factory and instance registrations.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [
            ...array_keys($this->shared),
            ...array_keys($this->factories),
            ...array_keys($this->instances),
        ];

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    public function lazyIds(): array
    {
        return array_keys($this->lazyFactories);
    }

    /**
     * @return list<string>
     */
    public function sharedIds(): array
    {
        return array_keys($this->shared);
    }

    /** @return array<string, callable(ResolverContainer): object> */
    public function allShared(): array
    {
        return $this->shared;
    }

    /** @return array<string, callable(ResolverContainer): object> */
    public function allFactories(): array
    {
        return $this->factories;
    }

    /** @return array<string, object> */
    public function allInstances(): array
    {
        return $this->instances;
    }
}
