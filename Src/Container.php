<?php

declare(strict_types=1);

namespace Temant\Container;

use Closure;
use Exception;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\NotFoundException;
use Temant\Container\Resolver\Resolver;

use function class_exists;
use function is_object;

/**
 * PSR-11 compliant Dependency Injection Container with autowiring support.
 *
 * Design goals:
 *   - **Shared-by-default**: {@see set()} registers a singleton service.
 *   - **Factory support**: {@see factory()} returns a new instance on every call.
 *   - **Instance support**: {@see instance()} stores a pre-created object.
 *   - **Bindings/aliases**: {@see bind()} and {@see alias()} for interface-to-concrete mapping.
 *   - **Tagging**: {@see tag()} and {@see tagged()} for grouped service retrieval.
 *   - **Decoration**: {@see extend()} wraps existing services with decorators.
 *   - **Service providers**: {@see register()} and {@see boot()} for modular configuration.
 *   - **Callable invocation**: {@see call()} invokes any callable with DI-resolved parameters.
 *   - **Autowiring**: Optional reflection-based resolution (enabled by default).
 */
class Container implements ContainerInterface
{
    /**
     * Shared service factories (singleton by default).
     *
     * @var array<string, callable(ContainerInterface): object>
     */
    private array $shared = [];

    /**
     * Factory service definitions (new instance every {@see get()} call).
     *
     * @var array<string, callable(ContainerInterface): object>
     */
    private array $factories = [];

    /**
     * Cached shared instances (populated on first resolve).
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Bindings/aliases mapping an abstract ID to a concrete target ID.
     *
     * @var array<string, string>
     */
    private array $bindings = [];

    /**
     * Tag registry mapping tag names to lists of service IDs.
     *
     * @var array<string, list<string>>
     */
    private array $tags = [];

    /**
     * Decorator closures applied after service resolution.
     *
     * @var array<string, list<Closure(object, ContainerInterface): object>>
     */
    private array $extenders = [];

    /**
     * Registered service providers.
     *
     * @var list<ServiceProviderInterface>
     */
    private array $providers = [];

    /**
     * Whether {@see boot()} has been called.
     */
    private bool $booted = false;

    /**
     * Handles autowiring and callable invocation.
     */
    private Resolver $resolver;

    /**
     * @param bool $autowiringEnabled Whether autowiring is enabled (default: true).
     * @param bool $cacheAutowire    Whether autowired instances are cached as singletons (default: true).
     */
    public function __construct(
        private bool $autowiringEnabled = true,
        private bool $cacheAutowire = true,
    ) {
        $this->resolver = new Resolver($this);
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Registers multiple shared (singleton) entries at once.
     *
     * @param array<string, callable(ContainerInterface): object> $definitions
     * @return $this
     *
     * @throws ContainerException If any ID is already registered.
     */
    public function multi(array $definitions): self
    {
        foreach ($definitions as $id => $concrete) {
            $this->set((string) $id, $concrete);
        }

        return $this;
    }

    /**
     * Registers a shared (singleton) entry.
     *
     * The factory is invoked once on first {@see get()}, and the result is cached
     * for all subsequent calls.
     *
     * @param string                              $id       The service identifier (typically a class-string).
     * @param callable(ContainerInterface): object $concrete Factory returning the service instance.
     * @return $this
     *
     * @throws ContainerException If the ID is already registered.
     */
    public function set(string $id, callable $concrete): self
    {
        $this->guardAgainstDuplicate($id);

        $this->shared[$id] = $concrete;

        return $this;
    }

    /**
     * Alias for {@see set()} -- explicit naming for readability.
     *
     * @param string                              $id       The service identifier.
     * @param callable(ContainerInterface): object $concrete Factory returning the service instance.
     * @return $this
     *
     * @throws ContainerException If the ID is already registered.
     */
    public function singleton(string $id, callable $concrete): self
    {
        return $this->set($id, $concrete);
    }

    /**
     * Registers a factory entry that creates a new instance on every {@see get()} call.
     *
     * @param string                              $id       The service identifier.
     * @param callable(ContainerInterface): object $concrete Factory returning the service instance.
     * @return $this
     *
     * @throws ContainerException If the ID is already registered.
     */
    public function factory(string $id, callable $concrete): self
    {
        $this->guardAgainstDuplicate($id);

        $this->factories[$id] = $concrete;

        return $this;
    }

    /**
     * Registers an existing object instance.
     *
     * The same instance is returned on every {@see get()} call.
     *
     * @param string $id     The service identifier.
     * @param object $object The pre-created instance.
     * @return $this
     *
     * @throws ContainerException If the ID is already registered.
     */
    public function instance(string $id, object $object): self
    {
        $this->guardAgainstDuplicate($id);

        $this->instances[$id] = $object;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Bindings / Aliases
    // -------------------------------------------------------------------------

    /**
     * Binds an abstract ID (typically an interface) to a target ID (typically a concrete class).
     *
     * When the abstract ID is requested, the container resolves the target instead.
     * Supports chained bindings (A -> B -> C).
     *
     * @param string $abstract The abstract service identifier.
     * @param string $target   The concrete target identifier.
     * @return $this
     */
    public function bind(string $abstract, string $target): self
    {
        $this->bindings[$abstract] = $target;

        return $this;
    }

    /**
     * Creates an alias for an existing service ID.
     *
     * Alias for {@see bind()} -- semantic naming for readability.
     *
     * @param string $alias  The alias identifier.
     * @param string $target The target identifier the alias points to.
     * @return $this
     */
    public function alias(string $alias, string $target): self
    {
        return $this->bind($alias, $target);
    }

    // -------------------------------------------------------------------------
    // Tagging
    // -------------------------------------------------------------------------

    /**
     * Tags a service ID with a group name.
     *
     * @param string $id  The service identifier to tag.
     * @param string $tag The tag name.
     * @return $this
     */
    public function tag(string $id, string $tag): self
    {
        $this->tags[$tag] ??= [];
        $this->tags[$tag][] = $id;

        return $this;
    }

    /**
     * Resolves all services registered under a tag.
     *
     * @param string $tag The tag name.
     * @return list<object> The resolved service instances.
     */
    public function tagged(string $tag): array
    {
        $ids = $this->tags[$tag] ?? [];
        $out = [];

        foreach ($ids as $id) {
            /** @var object $service */
            $service = $this->get($id);
            $out[] = $service;
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Decoration / Extension
    // -------------------------------------------------------------------------

    /**
     * Registers a decorator for an existing service.
     *
     * The decorator receives the resolved service and the container, and must return
     * an object (typically the same type, wrapped or modified).
     *
     * Multiple extenders can be registered per service; they are applied in order.
     *
     * @param string                                        $id       The service identifier to extend.
     * @param Closure(object, ContainerInterface): object $extender The decorator closure.
     * @return $this
     *
     * @throws ContainerException If the ID has no existing registration.
     */
    public function extend(string $id, Closure $extender): self
    {
        if (!$this->isRegistered($id)) {
            throw new ContainerException("Cannot extend '{$id}': no existing registration found.");
        }

        $this->extenders[$id] ??= [];
        $this->extenders[$id][] = $extender;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Service Providers
    // -------------------------------------------------------------------------

    /**
     * Registers a service provider.
     *
     * Calls {@see ServiceProviderInterface::register()} immediately. If the container
     * is already booted, also calls {@see ServiceProviderInterface::boot()} right away.
     *
     * @param ServiceProviderInterface $provider The provider to register.
     * @return $this
     */
    public function register(ServiceProviderInterface $provider): self
    {
        $provider->register($this);
        $this->providers[] = $provider;

        if ($this->booted) {
            $provider->boot($this);
        }

        return $this;
    }

    /**
     * Boots all registered service providers.
     *
     * Calls {@see ServiceProviderInterface::boot()} on each provider. Safe to call
     * multiple times; subsequent calls are no-ops.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }

        $this->booted = true;
    }

    // -------------------------------------------------------------------------
    // Resolution (PSR-11)
    // -------------------------------------------------------------------------

    /**
     * Retrieves an entry from the container by its identifier.
     *
     * Resolution order:
     *   1. Resolve bindings/aliases to the final target ID.
     *   2. Return a cached instance if available.
     *   3. Invoke a shared (singleton) factory, cache and return.
     *   4. Invoke a factory definition and return (no caching).
     *   5. Autowire the class if autowiring is enabled.
     *   6. Throw {@see NotFoundException}.
     *
     * Extenders (decorators) are applied after resolution, before caching.
     *
     * @param string $id The entry identifier.
     * @return mixed The resolved entry.
     *
     * @throws NotFoundException  If the entry cannot be found or resolved.
     * @throws ContainerException For resolution or runtime errors.
     */
    public function get(string $id): mixed
    {
        $id = $this->resolveBinding($id);

        try {
            // 1) Cached instance
            if (isset($this->instances[$id])) {
                return $this->instances[$id];
            }

            // 2) Shared (singleton)
            if (isset($this->shared[$id])) {
                $obj = ($this->shared[$id])($this);
                $this->guardObjectReturn($obj, $id, 'Shared');

                $obj = $this->applyExtenders($id, $obj);

                return $this->instances[$id] = $obj;
            }

            // 3) Factory (new each time)
            if (isset($this->factories[$id])) {
                $obj = ($this->factories[$id])($this);
                $this->guardObjectReturn($obj, $id, 'Factory');

                return $this->applyExtenders($id, $obj);
            }

            // 4) Autowire
            if ($this->autowiringEnabled && class_exists($id)) {
                $obj = $this->resolver->resolve($id);
                $obj = $this->applyExtenders($id, $obj);

                if ($this->cacheAutowire) {
                    $this->instances[$id] = $obj;
                }

                return $obj;
            }

            throw new NotFoundException();
        } catch (NotFoundException $e) {
            throw $e::forEntry($id);
        } catch (ContainerException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ContainerException("Error resolving entry '{$id}'.", 0, $e);
        }
    }

    /**
     * Checks if an entry can be resolved by the container.
     *
     * Returns true if the ID (after binding resolution) has a definition,
     * cached instance, or is an autowirable class.
     *
     * @param string $id The entry identifier.
     * @return bool
     */
    public function has(string $id): bool
    {
        $id = $this->resolveBinding($id);

        if ($this->isRegistered($id)) {
            return true;
        }

        return $this->autowiringEnabled && class_exists($id);
    }

    // -------------------------------------------------------------------------
    // Callable Invocation
    // -------------------------------------------------------------------------

    /**
     * Invokes a callable while resolving its type-hinted parameters from the container.
     *
     * Useful for controllers and handlers:
     *   $container->call(fn(LoggerInterface $log) => $log->info('hello'));
     *
     * @param callable             $callable       The callable to invoke.
     * @param array<string, mixed> $namedOverrides Override values keyed by parameter name.
     * @return mixed The return value of the callable.
     */
    public function call(callable $callable, array $namedOverrides = []): mixed
    {
        return $this->resolver->call($callable, $namedOverrides);
    }

    // -------------------------------------------------------------------------
    // Removal / Reset
    // -------------------------------------------------------------------------

    /**
     * Removes an entry from the container (definitions, bindings, cached instances).
     *
     * @param string $id The entry identifier to remove.
     * @return void
     *
     * @throws ContainerException If no entry exists for the given ID.
     */
    public function remove(string $id): void
    {
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

        if (isset($this->extenders[$id])) {
            unset($this->extenders[$id]);
            $removed = true;
        }

        if (!$removed) {
            throw new ContainerException("Cannot remove '{$id}': no entry found in the container.");
        }
    }

    /**
     * Clears all definitions, bindings, cached instances, tags, extenders, and providers.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->shared = [];
        $this->factories = [];
        $this->instances = [];
        $this->bindings = [];
        $this->tags = [];
        $this->extenders = [];
        $this->providers = [];
        $this->booted = false;
    }

    /**
     * Clears only cached instances, keeping definitions intact.
     *
     * Useful in tests or long-running workers (e.g. Swoole, RoadRunner) to force
     * singletons to be recreated on the next {@see get()} call.
     *
     * @return void
     */
    public function flushInstances(): void
    {
        $this->instances = [];
    }

    // -------------------------------------------------------------------------
    // Autowiring Configuration
    // -------------------------------------------------------------------------

    /**
     * Enables or disables autowiring at runtime.
     *
     * When disabled, the container will only resolve explicitly registered services.
     *
     * @param bool $enabled Whether autowiring should be enabled.
     * @return void
     */
    public function setAutowiring(bool $enabled): void
    {
        $this->autowiringEnabled = $enabled;
    }

    /**
     * Checks whether autowiring is currently enabled.
     *
     * @return bool
     */
    public function hasAutowiring(): bool
    {
        return $this->autowiringEnabled;
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * Returns all registered service IDs (shared, factories, and instances).
     *
     * @return list<string> Unique list of registered IDs.
     */
    public function keys(): array
    {
        return array_values(array_unique([
            ...array_keys($this->shared),
            ...array_keys($this->factories),
            ...array_keys($this->instances),
        ]));
    }

    /**
     * Returns a structured snapshot of all container registrations.
     *
     * @return array{
     *     shared:    array<string, callable>,
     *     factories: array<string, callable>,
     *     instances: array<string, object>,
     *     bindings:  array<string, string>,
     *     tags:      array<string, list<string>>,
     * }
     */
    public function all(): array
    {
        return [
            'shared' => $this->shared,
            'factories' => $this->factories,
            'instances' => $this->instances,
            'bindings' => $this->bindings,
            'tags' => $this->tags,
        ];
    }

    /**
     * Returns all shared (singleton) definitions.
     *
     * @return array<string, callable(ContainerInterface): object>
     */
    public function allShared(): array
    {
        return $this->shared;
    }

    /**
     * Returns all factory definitions.
     *
     * @return array<string, callable(ContainerInterface): object>
     */
    public function allFactories(): array
    {
        return $this->factories;
    }

    /**
     * Returns all cached instances.
     *
     * @return array<string, object>
     */
    public function allInstances(): array
    {
        return $this->instances;
    }

    /**
     * Returns all bindings/aliases.
     *
     * @return array<string, string>
     */
    public function allBindings(): array
    {
        return $this->bindings;
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves binding/alias chains to the final target ID.
     *
     * @param string $id The entry identifier.
     * @return string The resolved target identifier.
     *
     * @throws ContainerException If a circular binding loop is detected.
     */
    private function resolveBinding(string $id): string
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
     * Checks if an ID has any existing registration (shared, factory, or instance).
     *
     * @param string $id The entry identifier.
     * @return bool
     */
    private function isRegistered(string $id): bool
    {
        return isset($this->shared[$id])
            || isset($this->factories[$id])
            || isset($this->instances[$id]);
    }

    /**
     * Throws if the given ID already has a registration.
     *
     * @param string $id The entry identifier.
     * @return void
     *
     * @throws ContainerException If the ID is already registered.
     */
    private function guardAgainstDuplicate(string $id): void
    {
        if ($this->isRegistered($id)) {
            throw new ContainerException("Entry '{$id}' is already registered in the container.");
        }
    }

    /**
     * Ensures a factory/shared callback returned an object.
     *
     * @param mixed  $value The returned value to check.
     * @param string $id    The service identifier (for the error message).
     * @param string $type  The registration type label ("Shared" or "Factory").
     * @return void
     *
     * @throws ContainerException If the value is not an object.
     */
    private function guardObjectReturn(mixed $value, string $id, string $type): void
    {
        if (!is_object($value)) {
            throw new ContainerException("{$type} entry '{$id}' must return an object, got " . get_debug_type($value) . '.');
        }
    }

    /**
     * Applies all registered extenders/decorators to a resolved service.
     *
     * @param string $id  The service identifier.
     * @param object $obj The resolved service instance.
     * @return object The (possibly decorated) service instance.
     */
    private function applyExtenders(string $id, object $obj): object
    {
        if (!isset($this->extenders[$id])) {
            return $obj;
        }

        foreach ($this->extenders[$id] as $extender) {
            $obj = $extender($obj, $this);
        }

        return $obj;
    }
}
