<?php

declare(strict_types=1);

namespace Temant\Container;

use Closure;

/**
 * A generic lazy proxy that defers object creation until first use.
 *
 * Delegates all property access and method calls to the real instance,
 * which is created on first interaction.
 *
 * Limitation: instanceof checks against the proxied type will return false.
 * For PHP 8.4+, consider using native lazy objects instead.
 */
final class LazyProxy
{
    private ?object $instance = null;
    private ?Closure $factory;

    /**
     * @param Closure(): object $factory Factory that creates the real instance.
     */
    public function __construct(Closure $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Delegates method calls to the real instance.
     *
     * @param string       $method Method name.
     * @param list<mixed>  $args   Method arguments.
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->resolve()->{$method}(...$args);
    }

    /**
     * Delegates property reads to the real instance.
     *
     * @param string $name Property name.
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->resolve()->{$name};
    }

    /**
     * Delegates property writes to the real instance.
     *
     * @param string $name  Property name.
     * @param mixed  $value Property value.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->resolve()->{$name} = $value;
    }

    /**
     * Delegates isset checks to the real instance.
     *
     * @param string $name Property name.
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->resolve()->{$name});
    }

    /**
     * Delegates unset to the real instance.
     *
     * @param string $name Property name.
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->resolve()->{$name});
    }

    /**
     * Delegates string casting to the real instance.
     *
     * @return string
     */
    public function __toString(): string
    {
        $target = $this->resolve();

        if ($target instanceof \Stringable) {
            return $target->__toString();
        }

        return $target::class;
    }

    /**
     * Checks whether the real instance has been created.
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->instance !== null;
    }

    /**
     * Returns the real instance, creating it if necessary.
     *
     * @return object
     */
    public function getTarget(): object
    {
        return $this->resolve();
    }

    /**
     * Resolves the real instance (once).
     *
     * @return object
     */
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
