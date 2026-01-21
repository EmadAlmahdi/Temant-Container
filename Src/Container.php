<?php

declare(strict_types=1);

namespace Temant\Container;

use Exception;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\NotFoundException;
use Temant\Container\Resolver\Resolver;

use function class_exists;
use function is_object;

/**
 * Dependency Injection Container.
 *
 * Design goals:
 * - Shared-by-default: `set()` registers a singleton/shared service
 * - `factory()` registers a service that returns a new instance every time
 * - `instance()` registers an already-created instance
 * - `bind()/alias()` for interface-to-concrete and id aliasing
 * - `tag()/tagged()` for grouped services
 * - `call()` for invoking callables with DI injection
 * - Autowiring (optional) via reflection
 *
 * @template T of object
 */
class Container implements ContainerInterface
{
    /**
     * Shared service factories (singleton by default).
     *
     * @var array<string, callable(ContainerInterface):object>
     */
    private array $shared = [];

    /**
     * Factory service definitions (new instance every get()).
     *
     * @var array<string, callable(ContainerInterface):object>
     */
    private array $factories = [];

    /**
     * Cached shared instances.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Bindings/aliases (id -> target id), e.g. interface => concrete, "db" => PDO::class
     *
     * @var array<string, string>
     */
    private array $bindings = [];

    /**
     * Tag registry.
     *
     * @var array<string, list<string>>
     */
    private array $tags = [];

    /**
     * Handles autowiring and callable invocation.
     */
    private Resolver $resolver;

    /**
     * @param bool $autowiringEnabled Whether autowiring is enabled.
     * @param bool $cacheAutowire Whether autowired classes should be cached (shared-by-default behavior).
     */
    public function __construct(
        private bool $autowiringEnabled = true,
        private bool $cacheAutowire = true
    ) {
        $this->resolver = new Resolver($this);
    }

    /**
     * Adds multiple definitions to the container.
     *
     * Shared-by-default: the provided definitions are treated as shared services.
     *
     * @template TT of object
     * @param array<class-string<TT>|string, callable(ContainerInterface): TT> $definitions
     * @return $this
     */
    public function multi(array $definitions): self
    {
        foreach ($definitions as $id => $concrete) {
            $this->set((string) $id, $concrete);
        }

        return $this;
    }

    /**
     * Register a shared (singleton) entry.
     *
     * This is the default registration method.
     *
     * @template TT of object
     * @param class-string<TT>|string $id
     * @param callable(ContainerInterface): TT $concrete
     * @return $this
     * @throws ContainerException If the id is already registered.
     */
    public function set(string $id, callable $concrete): self
    {
        $id = $this->normalizeId($id);

        if ($this->isRegistered($id)) {
            throw new ContainerException("Entry for '$id' already exists in the container.");
        }

        $this->shared[$id] = $concrete;
        return $this;
    }

    /**
     * Alias for set() — explicit naming for readability.
     *
     * @template TT of object
     * @param class-string<TT>|string $id
     * @param callable(ContainerInterface): TT $concrete
     * @return $this
     */
    public function singleton(string $id, callable $concrete): self
    {
        return $this->set($id, $concrete);
    }

    /**
     * Register a factory entry (new instance on each get()).
     *
     * @template TT of object
     * @param class-string<TT>|string $id
     * @param callable(ContainerInterface): TT $concrete
     * @return $this
     * @throws ContainerException If the id is already registered.
     */
    public function factory(string $id, callable $concrete): self
    {
        $id = $this->normalizeId($id);

        if ($this->isRegistered($id)) {
            throw new ContainerException("Entry for '$id' already exists in the container.");
        }

        $this->factories[$id] = $concrete;
        return $this;
    }

    /**
     * Register an existing instance (always returned as-is).
     *
     * @template TT of object
     * @param class-string<TT>|string $id
     * @param TT $object
     * @return $this
     * @throws ContainerException If the id is already registered.
     */
    public function instance(string $id, object $object): self
    {
        $id = $this->normalizeId($id); 

        $this->instances[$id] = $object;
        return $this;
    }

    /**
     * Bind an abstract id (typically interface) to a target id (typically concrete class).
     *
     * Example:
     *   $container->bind(LoggerInterface::class, MonologLogger::class);
     *
     * @param class-string|string $abstract
     * @param class-string|string $target
     * @return $this
     */
    public function bind(string $abstract, string $target): self
    {
        $this->bindings[$this->normalizeId($abstract)] = $this->normalizeId($target);
        return $this;
    }

    /**
     * Alias an id to another id.
     *
     * Example:
     *   $container->alias('db', PDO::class);
     *
     * @param string $alias
     * @param class-string|string $target
     * @return $this
     */
    public function alias(string $alias, string $target): self
    {
        return $this->bind($alias, $target);
    }

    /**
     * Tag an id with a tag name.
     *
     * @param class-string|string $id
     * @param string $tag
     * @return $this
     */
    public function tag(string $id, string $tag): self
    {
        $id = $this->normalizeId($id);
        $this->tags[$tag] ??= [];
        $this->tags[$tag][] = $id;
        return $this;
    }

    /**
     * Resolve all services registered under a tag.
     *
     * @return list<object>
     */
    public function tagged(string $tag): array
    {
        $ids = $this->tags[$tag] ?? [];
        $out = [];

        foreach ($ids as $id) {
            $out[] = $this->get($id);
        }

        return $out;
    }

    /**
     * Retrieves an entry from the container.
     *
     * Resolution order:
     *  1) bindings/aliases
     *  2) cached instance (shared/instance/autowired if caching enabled)
     *  3) shared definition
     *  4) factory definition
     *  5) autowire (if enabled)
     *
     * @template TT of object
     * @param class-string<TT>
     * @return TT|mixed
     * @throws NotFoundException If entry cannot be found/resolved.
     * @throws ContainerException For other container/runtime errors.
     */
    public function get(string $id): mixed
    {
        $id = $this->normalizeId($id);
        $id = $this->resolveBinding($id);

        try {
            // Cached instance?
            if (isset($this->instances[$id])) {
                return $this->instances[$id];
            }

            // Shared by default
            if (isset($this->shared[$id])) {
                $obj = ($this->shared[$id])($this);

                if (!is_object($obj)) {
                    throw new ContainerException("Shared entry '$id' did not return an object.");
                }

                return $this->instances[$id] = $obj;
            }

            // Factory
            if (isset($this->factories[$id])) {
                $obj = ($this->factories[$id])($this);

                if (!is_object($obj)) {
                    throw new ContainerException("Factory entry '$id' did not return an object.");
                }

                return $obj;
            }

            // Autowire
            if (class_exists($id) && $this->autowiringEnabled) {
                $obj = $this->resolver->resolve($id);

                if ($this->cacheAutowire) {
                    $this->instances[$id] = $obj;
                }

                return $obj;
            }

            throw new NotFoundException;
        } catch (NotFoundException $e) {
            throw $e::forEntry($id);
        } catch (ContainerException $e) {
            throw $e;
        } catch (Exception $e) {
            // Keep a consistent exception type for “something went wrong”.
            throw new ContainerException("Error retrieving entry '$id'.", 0, $e);
        }
    }

    /**
     * Checks if an entry exists in the container (definitions or instances). 
     *
     * @param class-string|string $id
     */
    public function has(string $id): bool
    {
        $id = $this->normalizeId($id);
        $id = $this->resolveBinding($id);

        return $this->isRegistered($id) || isset($this->instances[$id]);
    }

    /**
     * Removes an entry (definitions, bindings, cached instance, tags references stay as-is).
     *
     * @param class-string|string $id
     * @throws ContainerException If nothing exists to remove.
     */
    public function remove(string $id): void
    {
        $id = $this->normalizeId($id);

        $removed = false;

        if (isset($this->shared[$id])) {
            unset($this->shared[$id]);
            $removed = true;
        }

        if (isset($this->factories[$id])) {
            unset($this->factories[$id]);
            $removed = true;
        }

        if (isset($this->instances[$id])) {
            unset($this->instances[$id]);
            $removed = true;
        }

        if (isset($this->bindings[$id])) {
            unset($this->bindings[$id]);
            $removed = true;
        }

        if (!$removed) {
            throw new ContainerException("Entry for '$id' does not exist in the container.");
        }
    }

    /**
     * Clears all definitions, bindings, cached instances and tags.
     */
    public function clear(): void
    {
        $this->shared = [];
        $this->factories = [];
        $this->instances = [];
        $this->bindings = [];
        $this->tags = [];
    }

    /**
     * Clears ONLY cached instances (useful in tests / long-running workers).
     */
    public function flushInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Enables or disables autowiring at runtime.
     */
    public function setAutowiring(bool $enabled): void
    {
        $this->autowiringEnabled = $enabled;
    }

    /**
     * Checks if autowiring is enabled.
     * @return bool Whether autowiring is enabled.
     */
    public function hasAutowiring(): bool
    {
        return $this->autowiringEnabled;
    }

    /**
     * Invoke a callable while letting the container resolve type-hinted parameters.
     *
     * Useful for controllers/handlers:
     *   $container->call([$controller, 'action'])
     *   $container->call(fn(LoggerInterface $log) => ...)
     *
     * @param callable $callable
     * @param array<string,mixed> $namedOverrides Override by parameter name.
     */
    public function call(callable $callable, array $namedOverrides = []): mixed
    {
        return $this->resolver->call($callable, $namedOverrides);
    }

    public function all(): array
    {
        return [
            ...$this->shared,
            ...$this->factories,
            ...$this->instances,
            ...$this->bindings,
            ...$this->tags,
        ];
    }

    /**
     * @return array<string, callable(ContainerInterface):object>
     */
    public function allShared(): array
    {
        return $this->shared;
    }

    /**
     * @return array<string, callable(ContainerInterface):object>
     */
    public function allFactories(): array
    {
        return $this->factories;
    }

    /**
     * Normalize ids (keeps it simple; you can add trimming / validation here).
     */
    private function normalizeId(string $id): string
    {
        return $id;
    }

    /**
     * Resolve bindings/aliases (supports chains).
     */
    private function resolveBinding(string $id): string
    {
        $seen = [];

        while (isset($this->bindings[$id])) {
            if (isset($seen[$id])) {
                // Binding loop: a -> b -> a
                throw new ContainerException("Binding loop detected at '$id'.");
            }
            $seen[$id] = true;
            $id = $this->bindings[$id];
        }

        return $id;
    }

    /**
     * True if an id has a definition in shared/factory.
     * 
     * @param class-string|string $id
     * @return bool
     */
    private function isRegistered(string $id): bool
    {
        return isset($this->shared[$id]) || isset($this->factories[$id]);
    }
}