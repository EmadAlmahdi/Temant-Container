<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use Closure;
use Temant\Container\Contract\ResolverContainer;

/**
 * The post-resolution processing chain applied to every freshly created service.
 *
 * Owns four kinds of hook and runs them, for a given entry, in this order:
 *
 *   1. **extenders**  -- decorate/replace the instance ({@see extend()}); keyed by ID.
 *   2. **inflectors** -- mutate the instance in place when it matches a type
 *                        ({@see inflect()}); matched with `instanceof`.
 *   3. **resolving**  -- notification callbacks fired before the instance is returned.
 *   4. **afterResolving** -- notification callbacks fired last.
 *
 * ID-specific hooks run before their global counterparts.
 *
 * @internal
 */
final class ResolutionPipeline
{
    /** @var array<string, list<Closure(object, ResolverContainer): object>> */
    private array $extenders = [];

    /** @var array<string, list<Closure(object, ResolverContainer): void>> */
    private array $inflectors = [];

    /** @var array<string, list<Closure(object, ResolverContainer): void>> */
    private array $resolving = [];

    /** @var list<Closure(object, ResolverContainer): void> */
    private array $globalResolving = [];

    /** @var array<string, list<Closure(object, ResolverContainer): void>> */
    private array $afterResolving = [];

    /** @var list<Closure(object, ResolverContainer): void> */
    private array $globalAfterResolving = [];

    /**
     * Registers a decorator for an entry. Extenders may be registered before the
     * entry itself; they apply the next time it is resolved.
     *
     * @param Closure(object, ResolverContainer): object $extender
     */
    public function extend(string $id, Closure $extender): void
    {
        $this->extenders[$id][] = $extender;
    }

    public function hasExtenders(string $id): bool
    {
        return isset($this->extenders[$id]);
    }

    /**
     * Registers a type-matched, in-place mutator (e.g. setter injection).
     *
     * @param Closure(object, ResolverContainer): void $callback
     */
    public function inflect(string $type, Closure $callback): void
    {
        $this->inflectors[$type][] = $callback;
    }

    /**
     * @param string|Closure(object, ResolverContainer): void $idOrCallback
     * @param (Closure(object, ResolverContainer): void)|null $callback
     */
    public function resolving(string|Closure $idOrCallback, ?Closure $callback = null): void
    {
        if ($idOrCallback instanceof Closure) {
            $this->globalResolving[] = $idOrCallback;

            return;
        }

        if ($callback === null) {
            return;
        }

        $this->resolving[$idOrCallback][] = $callback;
    }

    /**
     * @param string|Closure(object, ResolverContainer): void $idOrCallback
     * @param (Closure(object, ResolverContainer): void)|null $callback
     */
    public function afterResolving(string|Closure $idOrCallback, ?Closure $callback = null): void
    {
        if ($idOrCallback instanceof Closure) {
            $this->globalAfterResolving[] = $idOrCallback;

            return;
        }

        if ($callback === null) {
            return;
        }

        $this->afterResolving[$idOrCallback][] = $callback;
    }

    /**
     * Runs the full pipeline for an entry and returns the final instance.
     */
    public function process(string $id, object $instance, ResolverContainer $container): object
    {
        foreach ($this->extenders[$id] ?? [] as $extender) {
            $instance = $extender($instance, $container);
        }

        foreach ($this->inflectors as $type => $callbacks) {
            if ($instance instanceof $type) {
                foreach ($callbacks as $callback) {
                    $callback($instance, $container);
                }
            }
        }

        foreach ($this->resolving[$id] ?? [] as $callback) {
            $callback($instance, $container);
        }

        foreach ($this->globalResolving as $callback) {
            $callback($instance, $container);
        }

        foreach ($this->afterResolving[$id] ?? [] as $callback) {
            $callback($instance, $container);
        }

        foreach ($this->globalAfterResolving as $callback) {
            $callback($instance, $container);
        }

        return $instance;
    }

    /**
     * Drops the extenders registered for an entry (used by {@see \Temant\Container\Container::remove()}).
     */
    public function forget(string $id): void
    {
        unset($this->extenders[$id]);
    }

    public function clear(): void
    {
        $this->extenders = [];
        $this->inflectors = [];
        $this->resolving = [];
        $this->globalResolving = [];
        $this->afterResolving = [];
        $this->globalAfterResolving = [];
    }
}
