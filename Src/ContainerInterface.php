<?php

declare(strict_types=1);

namespace Temant\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Extended container contract layered on top of PSR-11.
 *
 * PSR-11 only standardises {@see get()} and {@see has()}. This interface adds the
 * capabilities that make the container useful as a dependency injection tool:
 * autowiring awareness, callable invocation, fresh-instance creation, tag resolution,
 * lazy-service introspection, and entry removal.
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Whether reflection-based autowiring is currently enabled.
     */
    public function hasAutowiring(): bool;

    /**
     * Invokes a callable, resolving its type-hinted parameters from the container.
     *
     * Accepts closures, `[$object, 'method']` pairs, invokable objects, and the
     * `'Class@method'` string form.
     *
     * @param callable|string $callable The callable to invoke.
     * @param array<string, mixed> $namedOverrides Values that take precedence, keyed by parameter name.
     * @return mixed The callable's return value.
     */
    public function call(callable|string $callable, array $namedOverrides = []): mixed;

    /**
     * Creates a fresh instance, bypassing the singleton cache.
     *
     * @param string $id The entry identifier.
     * @param array<string, mixed> $parameters Named constructor-argument overrides.
     * @return object The newly created instance.
     */
    public function make(string $id, array $parameters = []): object;

    /**
     * Resolves every service registered under a tag.
     *
     * @param string $tag The tag name.
     * @return list<object> The resolved service instances.
     */
    public function tagged(string $tag): array;

    /**
     * Whether a lazily registered service has been initialised (its factory has run).
     *
     * Returns false for entries that are not registered as lazy.
     *
     * @param string $id The entry identifier.
     */
    public function initialized(string $id): bool;

    /**
     * Removes an entry from the container.
     *
     * @param string $id The entry identifier to remove.
     */
    public function remove(string $id): void;
}
