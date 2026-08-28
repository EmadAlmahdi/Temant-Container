<?php

declare(strict_types=1);

namespace Temant\Container\Definition;

use Temant\Container\Exception\ContainerException;

/**
 * Maps abstract identifiers (interfaces, aliases) to concrete target identifiers.
 *
 * Bindings chain: `A -> B -> C` resolves `A` to `C`. Loops are detected and rejected.
 *
 * @internal
 */
final class BindingMap
{
    /** @var array<string, string> */
    private array $bindings = [];

    /**
     * Points an abstract identifier at a target identifier.
     */
    public function bind(string $abstract, string $target): void
    {
        $this->bindings[$abstract] = $target;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    /**
     * The direct target of an abstract identifier (one hop), or null.
     */
    public function target(string $abstract): ?string
    {
        return $this->bindings[$abstract] ?? null;
    }

    /**
     * Follows the binding chain to the final concrete identifier.
     *
     * @throws ContainerException If the chain contains a loop.
     */
    public function resolve(string $id): string
    {
        $seen = [];

        while (isset($this->bindings[$id])) {
            if (isset($seen[$id])) {
                throw new ContainerException("Circular binding loop detected at '{$id}'.");
            }

            $seen[$id] = true;
            $id = $this->bindings[$id];
        }

        return $id;
    }

    /**
     * @return bool Whether a binding was removed.
     */
    public function forget(string $abstract): bool
    {
        if (!isset($this->bindings[$abstract])) {
            return false;
        }

        unset($this->bindings[$abstract]);

        return true;
    }

    public function clear(): void
    {
        $this->bindings = [];
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->bindings;
    }
}
