<?php

declare(strict_types=1);

namespace Temant\Container\Registry;

use Closure;

/**
 * Stores consumer-specific bindings: "when X needs Y, give it Z".
 *
 * A concrete may be a target ID (string), a factory (Closure), or a list of IDs
 * used to satisfy a typed variadic parameter.
 *
 * @internal
 */
final class ContextualBindingRegistry
{
    /** @var array<string, array<string, string|Closure|list<string>>> */
    private array $bindings = [];

    /**
     * @param string|Closure|list<string> $concrete
     */
    public function add(string $consumer, string $abstract, string|Closure|array $concrete): void
    {
        $this->bindings[$consumer][$abstract] = $concrete;
    }

    /**
     * @return string|Closure|list<string>|null
     */
    public function find(string $consumer, string $abstract): string|Closure|array|null
    {
        return $this->bindings[$consumer][$abstract] ?? null;
    }

    public function clear(): void
    {
        $this->bindings = [];
    }
}
