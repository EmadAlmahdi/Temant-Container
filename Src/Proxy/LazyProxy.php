<?php

declare(strict_types=1);

namespace Temant\Container\Proxy;

use Closure;
use Stringable;

/**
 * A fallback lazy wrapper that defers object creation until first use.
 *
 * The container prefers PHP's native lazy objects (see {@see LazyObjectFactory}),
 * which are fully type-transparent. This proxy is used only when a native lazy object
 * cannot be created -- when the entry resolves to an interface or non-class ID, or
 * when the entry has extenders that may replace the instance with a different type.
 *
 * All property and method access is delegated to the real instance via magic methods,
 * so `instanceof` checks against the proxied type return `false` for this proxy.
 */
final class LazyProxy
{
    private ?object $instance = null;

    private ?Closure $factory;

    /**
     * @param Closure(): object $factory Creates the real instance on first access.
     */
    public function __construct(Closure $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->resolve()->{$method}(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->resolve()->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->resolve()->{$name} = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->resolve()->{$name});
    }

    public function __unset(string $name): void
    {
        unset($this->resolve()->{$name});
    }

    public function __toString(): string
    {
        $target = $this->resolve();

        return $target instanceof Stringable ? $target->__toString() : $target::class;
    }

    /**
     * Whether the real instance has been created.
     */
    public function isInitialized(): bool
    {
        return $this->instance !== null;
    }

    /**
     * Forces creation and returns the real instance.
     */
    public function getTarget(): object
    {
        return $this->resolve();
    }

    private function resolve(): object
    {
        if ($this->instance === null) {
            /** @var Closure(): object $factory */
            $factory = $this->factory;
            $this->instance = $factory();
            $this->factory = null;
        }

        return $this->instance;
    }
}
