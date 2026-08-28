<?php

declare(strict_types=1);

namespace Temant\Container\Proxy;

use Closure;
use ReflectionClass;

use function class_exists;

/**
 * Builds lazy stand-ins for deferred services.
 *
 * Prefers PHP's native lazy proxy objects -- real instances of the target class whose
 * initialisation is deferred until first access -- so `instanceof` and type hints keep
 * working. Falls back to {@see LazyProxy} when a native proxy is not possible:
 *
 *  - the resolved concrete is not an instantiable class (e.g. an interface ID), or
 *  - the entry has extenders, which may legitimately return a different type than the
 *    factory produced, something a native proxy of a fixed class cannot represent.
 *
 * @internal
 */
final class LazyObjectFactory
{
    /**
     * @param string $concreteId The fully-resolved concrete identifier for the entry.
     * @param Closure(): object $initializer Runs the user factory and the resolution
     *                                       pipeline, returning the finished instance.
     * @param bool $allowNative Set false to force the {@see LazyProxy} fallback.
     */
    public function create(string $concreteId, Closure $initializer, bool $allowNative = true): object
    {
        if ($allowNative && class_exists($concreteId)) {
            /** @var ReflectionClass<object> $reflection */
            $reflection = new ReflectionClass($concreteId);

            if ($reflection->isInstantiable()) {
                return $reflection->newLazyProxy(static fn(object $proxy): object => $initializer());
            }
        }

        return new LazyProxy($initializer);
    }

    /**
     * Whether the given lazy stand-in has been initialised (its factory has run).
     */
    public function isInitialized(object $proxy): bool
    {
        if ($proxy instanceof LazyProxy) {
            return $proxy->isInitialized();
        }

        // Reflect the class by name: building a ReflectionClass from the proxy
        // instance can itself trigger initialisation for some class shapes.
        return !(new ReflectionClass($proxy::class))->isUninitializedLazyObject($proxy);
    }
}
