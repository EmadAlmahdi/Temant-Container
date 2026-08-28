<?php

declare(strict_types=1);

namespace Temant\Container\Reflection;

use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use Throwable;

use function class_exists;

/**
 * Caches the reflection work behind autowiring.
 *
 * For each class, the constructor is reflected once to produce a
 * {@see ConstructorDescriptor}; every later resolution reads the descriptor
 * instead of touching reflection again.
 *
 * - **In-memory** caching is always on and covers a single process.
 * - An optional **PSR-16 store** persists descriptors across processes, so a
 *   traditional PHP-FPM deployment does the reflection once per deploy rather
 *   than once per request. Warm it by resolving your graph (or calling
 *   {@see \Temant\Container\Container::warmUp()}) after deployment.
 *
 * A persistent store is assumed to be cleared on deploy -- descriptors are keyed
 * by class name only and carry no signature hash.
 *
 * @internal
 */
final class ReflectionCache
{
    private const KEY_PREFIX = 'temant.container.ctor.';

    /** @var array<string, ConstructorDescriptor> */
    private array $memory = [];

    public function __construct(
        private readonly ?CacheInterface $store = null,
    ) {
    }

    /**
     * The constructor descriptor for a class. The class must already exist.
     *
     * @param class-string $class
     */
    public function constructorFor(string $class): ConstructorDescriptor
    {
        if (isset($this->memory[$class])) {
            return $this->memory[$class];
        }

        $descriptor = $this->load($class) ?? $this->build($class);

        $this->memory[$class] = $descriptor;

        return $descriptor;
    }

    /**
     * Pre-build and cache descriptors for a set of classes without instantiating them.
     *
     * @param iterable<class-string> $classes
     */
    public function warm(iterable $classes): void
    {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                $this->constructorFor($class);
            }
        }
    }

    /**
     * @param class-string $class
     */
    private function load(string $class): ?ConstructorDescriptor
    {
        if ($this->store === null) {
            return null;
        }

        try {
            $cached = $this->store->get(self::KEY_PREFIX . $class);
        } catch (Throwable) {
            return null;
        }

        return $cached instanceof ConstructorDescriptor ? $cached : null;
    }

    /**
     * @param class-string $class
     */
    private function build(string $class): ConstructorDescriptor
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $descriptor = new ConstructorDescriptor($reflection->isInstantiable(), false, []);
        } else {
            $parameters = [];

            foreach ($constructor->getParameters() as $parameter) {
                $parameters[] = ParameterDescriptor::fromReflection($parameter);
            }

            $descriptor = new ConstructorDescriptor($reflection->isInstantiable(), true, $parameters);
        }

        if ($this->store !== null && $descriptor->isPersistable()) {
            try {
                $this->store->set(self::KEY_PREFIX . $class, $descriptor);
            } catch (Throwable) {
                // A failing cache must never break resolution.
            }
        }

        return $descriptor;
    }
}
