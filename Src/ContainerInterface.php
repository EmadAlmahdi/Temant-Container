<?php

declare(strict_types=1);

namespace Temant\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Extended container interface providing dependency injection capabilities
 * beyond the PSR-11 contract.
 *
 * Extends PSR-11's ContainerInterface with autowiring awareness, callable
 * invocation, fresh instance creation, and service removal.
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Checks whether autowiring is currently enabled.
     *
     * @return bool True if autowiring is enabled, false otherwise.
     */
    public function hasAutowiring(): bool;

    /**
     * Invokes a callable while resolving its type-hinted parameters from the container.
     *
     * Supports closures, array callables, and 'Class@method' string syntax.
     *
     * @param callable|string $callable The callable to invoke.
     * @param array<string, mixed> $namedOverrides Override values keyed by parameter name.
     * @return mixed The return value of the callable.
     */
    public function call(callable|string $callable, array $namedOverrides = []): mixed;

    /**
     * Creates a fresh instance of a service, bypassing singleton cache.
     *
     * Unlike {@see get()}, this always invokes the factory or autowires a new instance.
     * Accepts named parameter overrides for constructor arguments.
     *
     * @param string $id The entry identifier.
     * @param array<string, mixed> $parameters Named parameter overrides for the constructor.
     * @return object The newly created instance.
     */
    public function make(string $id, array $parameters = []): object;

    /**
     * Removes an entry from the container.
     *
     * @param string $id The entry identifier to remove.
     * @return void
     */
    public function remove(string $id): void;
}
