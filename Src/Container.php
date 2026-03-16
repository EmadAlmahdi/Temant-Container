<?php

declare(strict_types=1);

namespace Temant\Container;

use Closure;
use Exception;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\NotFoundException;
use Temant\Container\Resolver\Resolver;

use function array_keys;
use function array_unique;
use function array_values;
use function class_exists;
use function explode;
use function in_array;
use function is_object;
use function is_string;
use function str_contains;

/**
 * PSR-11 compliant Dependency Injection Container with autowiring support.
 *
 * Design goals:
 *   - **Shared-by-default**: {@see set()} registers a singleton service.
 *   - **Factory support**: {@see factory()} returns a new instance on every call.
 *   - **Instance support**: {@see instance()} stores a pre-created object.
 *   - **Bindings/aliases**: {@see bind()} and {@see alias()} for interface-to-concrete mapping.
 *   - **Contextual bindings**: {@see when()} for consumer-specific resolution.
 *   - **Tagging**: {@see tag()} and {@see tagged()} for grouped service retrieval.
 *   - **Decoration**: {@see extend()} wraps existing services with decorators.
 *   - **Inflectors**: {@see inflect()} for type-based post-resolution hooks.
 *   - **Events**: {@see resolving()} and {@see afterResolving()} for lifecycle hooks.
 *   - **Service providers**: {@see register()} and {@see boot()} for modular configuration.
 *   - **Callable invocation**: {@see call()} invokes any callable with DI-resolved parameters.
 *   - **Fresh instances**: {@see make()} creates new instances with parameter overrides.
 *   - **Autowiring**: Optional reflection-based resolution (enabled by default).
 *   - **Lazy proxies**: {@see lazy()} defers instantiation until first use.
 *   - **Child containers**: {@see createChild()} for scoped resolution.
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
     * Lazy service factories, tracked separately from shared/instances.
     *
     * @var array<string, callable(ContainerInterface): object>
     */
    private array $lazyFactories = [];

    /**
     * Bindings/aliases mapping an abstract ID to a concrete target ID.
     *
     * @var array<string, string>
     */
    private array $bindings = [];

    /**
     * Contextual bindings: [consumer][abstract] => concrete or closure.
     *
     * @var array<string, array<string, string|Closure>>
     */
    private array $contextualBindings = [];

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
     * Inflector callbacks keyed by type/interface name.
     *
     * @var array<string, list<Closure(object, ContainerInterface): void>>
     */
    private array $inflectors = [];

    /**
     * ID-specific resolving callbacks.
     *
     * @var array<string, list<Closure(object, ContainerInterface): void>>
     */
    private array $resolvingCallbacks = [];

    /**
     * Global resolving callbacks (fired for every resolution).
     *
     * @var list<Closure(object, ContainerInterface): void>
     */
    private array $globalResolvingCallbacks = [];

    /**
     * ID-specific after-resolving callbacks.
     *
     * @var array<string, list<Closure(object, ContainerInterface): void>>
     */
    private array $afterResolvingCallbacks = [];

    /**
     * Global after-resolving callbacks (fired for every resolution).
     *
     * @var list<Closure(object, ContainerInterface): void>
     */
    private array $globalAfterResolvingCallbacks = [];

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
     * Whether the container is frozen (no modifications allowed).
     */
    private bool $frozen = false;

    /**
     * Handles autowiring and callable invocation.
     */
    private Resolver $resolver;

    /**
     * Parent container for scoped resolution.
     */
    private ?Container $parent = null;

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
     * @throws ContainerException If any ID is already registered or container is frozen.
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
     * @throws ContainerException If the ID is already registered or container is frozen.
     */
    public function set(string $id, callable $concrete): self
    {
        $this->guardAgainstFrozen();
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
     * @throws ContainerException If the ID is already registered or container is frozen.
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
     * @throws ContainerException If the ID is already registered or container is frozen.
     */
    public function factory(string $id, callable $concrete): self
    {
        $this->guardAgainstFrozen();
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
     * @throws ContainerException If the ID is already registered or container is frozen.
     */
    public function instance(string $id, object $object): self
    {
        $this->guardAgainstFrozen();
        $this->guardAgainstDuplicate($id);

        $this->instances[$id] = $object;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Conditional Registration
    // -------------------------------------------------------------------------

    /**
     * Registers a shared entry only if the ID is not already registered.
     *
     * @param string                              $id       The service identifier.
     * @param callable(ContainerInterface): object $concrete Factory returning the service instance.
     * @return $this
     */
    public function setIf(string $id, callable $concrete): self
    {
        if (!$this->isRegistered($id)) {
            $this->set($id, $concrete);
        }

        return $this;
    }

    /**
     * Alias for {@see setIf()} -- explicit naming for readability.
     *
     * @param string                              $id       The service identifier.
     * @param callable(ContainerInterface): object $concrete Factory returning the service instance.
     * @return $this
     */
    public function singletonIf(string $id, callable $concrete): self
    {
        return $this->setIf($id, $concrete);
    }

    /**
     * Registers a factory entry only if the ID is not already registered.
     *
     * @param string                              $id       The service identifier.
     * @param callable(ContainerInterface): object $concrete Factory returning the service instance.
     * @return $this
     */
    public function factoryIf(string $id, callable $concrete): self
    {
        if (!$this->isRegistered($id)) {
            $this->factory($id, $concrete);
        }

        return $this;
    }

    /**
     * Registers an instance only if the ID is not already registered.
     *
     * @param string $id     The service identifier.
     * @param object $object The pre-created instance.
     * @return $this
     */
    public function instanceIf(string $id, object $object): self
    {
        if (!$this->isRegistered($id)) {
            $this->instance($id, $object);
        }

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
     *
     * @throws ContainerException If the container is frozen.
     */
    public function bind(string $abstract, string $target): self
    {
        $this->guardAgainstFrozen();

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
     *
     * @throws ContainerException If the container is frozen.
     */
    public function alias(string $alias, string $target): self
    {
        return $this->bind($alias, $target);
    }

    // -------------------------------------------------------------------------
    // Contextual Bindings
    // -------------------------------------------------------------------------

    /**
     * Begins a contextual binding definition.
     *
     * Usage:
     *   $container->when(UserController::class)
     *             ->needs(LoggerInterface::class)
     *             ->give(FileLogger::class);
     *
     * @param string $consumer The class that triggers this contextual binding.
     * @return ContextualBindingBuilder
     */
    public function when(string $consumer): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $consumer);
    }

    /**
     * Registers a contextual binding.
     *
     * @param string         $consumer The consuming class.
     * @param string         $abstract The abstract type/interface.
     * @param string|Closure $concrete The concrete implementation (class name or factory).
     * @return void
     *
     * @throws ContainerException If the container is frozen.
     *
     * @internal Called by {@see ContextualBindingBuilder::give()}.
     */
    public function addContextualBinding(string $consumer, string $abstract, string|Closure $concrete): void
    {
        $this->guardAgainstFrozen();

        $this->contextualBindings[$consumer][$abstract] = $concrete;
    }

    /**
     * Returns the contextual binding for a consumer/abstract pair, or null.
     *
     * @param string $consumer The consuming class.
     * @param string $abstract The abstract type/interface.
     * @return string|Closure|null
     *
     * @internal Used by the resolver.
     */
    public function getContextualBinding(string $consumer, string $abstract): string|Closure|null
    {
        return $this->contextualBindings[$consumer][$abstract] ?? null;
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
     *
     * @throws ContainerException If the container is frozen.
     */
    public function tag(string $id, string $tag): self
    {
        $this->guardAgainstFrozen();

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
     * @param string                                      $id       The service identifier to extend.
     * @param Closure(object, ContainerInterface): object  $extender The decorator closure.
     * @return $this
     *
     * @throws ContainerException If the ID has no existing registration or container is frozen.
     */
    public function extend(string $id, Closure $extender): self
    {
        $this->guardAgainstFrozen();

        if (!$this->isRegistered($id)) {
            throw new ContainerException("Cannot extend '{$id}': no existing registration found.");
        }

        $this->extenders[$id] ??= [];
        $this->extenders[$id][] = $extender;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Inflectors
    // -------------------------------------------------------------------------

    /**
     * Registers a post-resolution inflector for a given type.
     *
     * When any resolved object is an instance of the given type, the callback
     * is invoked. Useful for setter injection based on interfaces.
     *
     * Usage:
     *   $container->inflect(LoggerAwareInterface::class, function ($obj, $c) {
     *       $obj->setLogger($c->get(LoggerInterface::class));
     *   });
     *
     * @param string                                     $type     The interface/class name to match.
     * @param Closure(object, ContainerInterface): void  $callback The inflector callback.
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function inflect(string $type, Closure $callback): self
    {
        $this->guardAgainstFrozen();

        $this->inflectors[$type] ??= [];
        $this->inflectors[$type][] = $callback;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Container Events
    // -------------------------------------------------------------------------

    /**
     * Registers a resolving callback.
     *
     * If a string ID is provided, the callback fires only for that ID.
     * If a Closure is provided directly, it fires for every resolution (global).
     *
     * @param string|Closure(object, ContainerInterface): void  $idOrCallback Service ID or global callback.
     * @param (Closure(object, ContainerInterface): void)|null  $callback     Callback when $idOrCallback is a string.
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function resolving(string|Closure $idOrCallback, ?Closure $callback = null): self
    {
        $this->guardAgainstFrozen();

        if ($idOrCallback instanceof Closure) {
            $this->globalResolvingCallbacks[] = $idOrCallback;
        } else {
            /** @var Closure $callback */
            $this->resolvingCallbacks[$idOrCallback] ??= [];
            $this->resolvingCallbacks[$idOrCallback][] = $callback;
        }

        return $this;
    }

    /**
     * Registers an after-resolving callback.
     *
     * If a string ID is provided, the callback fires only for that ID.
     * If a Closure is provided directly, it fires for every resolution (global).
     *
     * @param string|Closure(object, ContainerInterface): void  $idOrCallback Service ID or global callback.
     * @param (Closure(object, ContainerInterface): void)|null  $callback     Callback when $idOrCallback is a string.
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function afterResolving(string|Closure $idOrCallback, ?Closure $callback = null): self
    {
        $this->guardAgainstFrozen();

        if ($idOrCallback instanceof Closure) {
            $this->globalAfterResolvingCallbacks[] = $idOrCallback;
        } else {
            /** @var Closure $callback */
            $this->afterResolvingCallbacks[$idOrCallback] ??= [];
            $this->afterResolvingCallbacks[$idOrCallback][] = $callback;
        }

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
     *   5. Delegate to parent container if available.
     *   6. Autowire the class if autowiring is enabled.
     *   7. Throw {@see NotFoundException}.
     *
     * Extenders, inflectors, and event callbacks are applied after resolution.
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

                $obj = $this->finalizeService($id, $obj);

                return $this->instances[$id] = $obj;
            }

            // 3) Factory (new each time)
            if (isset($this->factories[$id])) {
                $obj = ($this->factories[$id])($this);
                $this->guardObjectReturn($obj, $id, 'Factory');

                return $this->finalizeService($id, $obj);
            }

            // 4) Parent container
            if ($this->parent !== null && $this->parent->has($id)) {
                return $this->parent->get($id);
            }

            // 5) Autowire
            if ($this->autowiringEnabled && class_exists($id)) {
                $obj = $this->resolver->resolve($id);
                $obj = $this->finalizeService($id, $obj);

                if ($this->cacheAutowire) {
                    $this->instances[$id] = $obj;
                }

                return $obj;
            }

            throw NotFoundException::forEntry($id);
        } catch (NotFoundException|ContainerException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ContainerException("Error resolving entry '{$id}'.", 0, $e);
        }
    }

    /**
     * Checks if an entry can be resolved by the container.
     *
     * Returns true if the ID (after binding resolution) has a definition,
     * cached instance, is resolvable via parent, or is an autowirable class.
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

        if ($this->parent !== null && $this->parent->has($id)) {
            return true;
        }

        return $this->autowiringEnabled && class_exists($id);
    }

    // -------------------------------------------------------------------------
    // Fresh Instance Creation
    // -------------------------------------------------------------------------

    /**
     * Creates a fresh instance of a service, bypassing the singleton cache.
     *
     * Unlike {@see get()}, this always invokes the factory or autowires a new instance.
     * Accepts named parameter overrides for constructor arguments.
     *
     * Events (resolving/afterResolving), extenders, and inflectors are all applied.
     *
     * @param string               $id         The entry identifier.
     * @param array<string, mixed> $parameters Named parameter overrides for the constructor.
     * @return object The newly created instance.
     *
     * @throws NotFoundException  If the entry cannot be found or resolved.
     * @throws ContainerException For resolution or runtime errors.
     */
    public function make(string $id, array $parameters = []): object
    {
        $id = $this->resolveBinding($id);

        try {
            // Shared or factory definition: invoke factory (no caching)
            if (isset($this->shared[$id]) || isset($this->factories[$id])) {
                $factory = $this->shared[$id] ?? $this->factories[$id];
                $obj = $factory($this);
                $this->guardObjectReturn($obj, $id, 'Make');

                return $this->finalizeService($id, $obj);
            }

            // Parent container
            if ($this->parent !== null && $this->parent->has($id)) {
                return $this->parent->make($id, $parameters);
            }

            // Autowire with parameter overrides
            if ($this->autowiringEnabled && class_exists($id)) {
                $obj = $this->resolver->resolve($id, $parameters);

                return $this->finalizeService($id, $obj);
            }

            throw NotFoundException::forEntry($id);
        } catch (NotFoundException|ContainerException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ContainerException("Error making entry '{$id}'.", 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Callable Invocation
    // -------------------------------------------------------------------------

    /**
     * Invokes a callable while resolving its type-hinted parameters from the container.
     *
     * Supports closures, array callables, and 'Class@method' string syntax:
     *   $container->call('App\\Controller\\UserController@show');
     *
     * @param callable|string      $callable       The callable to invoke.
     * @param array<string, mixed> $namedOverrides Override values keyed by parameter name.
     * @return mixed The return value of the callable.
     */
    public function call(callable|string $callable, array $namedOverrides = []): mixed
    {
        // Support 'Class@method' string syntax
        if (is_string($callable) && str_contains($callable, '@')) {
            [$class, $method] = explode('@', $callable, 2);
            /** @var object $instance */
            $instance = $this->get($class);
            $callable = [$instance, $method];
        }

        /** @var callable $callable */
        return $this->resolver->call($callable, $namedOverrides);
    }

    // -------------------------------------------------------------------------
    // Lazy Proxies
    // -------------------------------------------------------------------------

    /**
     * Registers a lazy shared (singleton) service.
     *
     * The factory is wrapped in a {@see LazyProxy} that defers instantiation
     * until the first method call on the proxy.
     *
     * @param string                              $id       The service identifier.
     * @param callable(ContainerInterface): object $concrete Factory returning the service instance.
     * @return $this
     *
     * @throws ContainerException If the ID is already registered or container is frozen.
     */
    public function lazy(string $id, callable $concrete): self
    {
        $this->guardAgainstFrozen();
        $this->guardAgainstDuplicate($id);

        $this->lazyFactories[$id] = $concrete;
        $this->instances[$id] = $this->buildLazyProxy($id, $concrete);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Child Containers (Scoped)
    // -------------------------------------------------------------------------

    /**
     * Creates a child container that inherits this container's bindings.
     *
     * The child can override and add its own bindings. Resolution falls back
     * to the parent when entries are not found locally.
     *
     * @return self The new child container.
     */
    public function createChild(): self
    {
        $child = new self($this->autowiringEnabled, $this->cacheAutowire);
        $child->parent = $this;

        return $child;
    }

    /**
     * Returns the parent container, or null if this is a root container.
     *
     * @return self|null
     */
    public function getParent(): ?self
    {
        return $this->parent;
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
     * @throws ContainerException If no entry exists for the given ID or container is frozen.
     */
    public function remove(string $id): void
    {
        $this->guardAgainstFrozen();

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

        if (isset($this->lazyFactories[$id])) {
            unset($this->lazyFactories[$id]);
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
        $this->lazyFactories = [];
        $this->bindings = [];
        $this->contextualBindings = [];
        $this->tags = [];
        $this->extenders = [];
        $this->inflectors = [];
        $this->resolvingCallbacks = [];
        $this->globalResolvingCallbacks = [];
        $this->afterResolvingCallbacks = [];
        $this->globalAfterResolvingCallbacks = [];
        $this->providers = [];
        $this->booted = false;
        $this->frozen = false;
    }

    /**
     * Clears only cached instances, keeping definitions intact.
     *
     * Useful in tests or long-running workers (e.g. Swoole, RoadRunner) to force
     * singletons to be recreated on the next {@see get()} call.
     *
     * Lazy proxies are rebuilt (not destroyed) so they still defer correctly.
     *
     * @return void
     */
    public function flushInstances(): void
    {
        $this->instances = [];

        // Rebuild lazy proxies so they defer correctly instead of being lost
        foreach ($this->lazyFactories as $id => $factory) {
            $this->instances[$id] = $this->buildLazyProxy($id, $factory);
        }
    }

    // -------------------------------------------------------------------------
    // Freeze / Warm-Up
    // -------------------------------------------------------------------------

    /**
     * Freezes the container, preventing any further modifications.
     *
     * After freezing, any attempt to register, bind, extend, or remove services
     * will throw a ContainerException. Resolution continues to work normally.
     *
     * @return void
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    /**
     * Checks whether the container is frozen.
     *
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Pre-resolves all registered shared (singleton) services.
     *
     * Forces all singleton factories to run, populating the instance cache.
     * Useful in production to move all resolution overhead to startup.
     *
     * @return void
     */
    public function warmUp(): void
    {
        foreach (array_keys($this->shared) as $id) {
            if (!isset($this->instances[$id])) {
                $this->get($id);
            }
        }
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

    /**
     * Returns introspection data about a registered service.
     *
     * @param string $id The service identifier.
     * @return array{
     *     id: string,
     *     resolvedId: string,
     *     type: 'shared'|'factory'|'instance'|'lazy'|null,
     *     binding: string|null,
     *     tags: list<string>,
     *     hasExtenders: bool,
     * }|null Null if the ID has no registration.
     */
    public function getDefinition(string $id): ?array
    {
        $resolved = $this->resolveBinding($id);

        $type = null;
        if (isset($this->lazyFactories[$resolved])) {
            $type = 'lazy';
        } elseif (isset($this->shared[$resolved])) {
            $type = 'shared';
        } elseif (isset($this->factories[$resolved])) {
            $type = 'factory';
        } elseif (isset($this->instances[$resolved])) {
            $type = 'instance';
        }

        $binding = $this->bindings[$id] ?? null;

        if ($type === null && $binding === null) {
            return null;
        }

        $tags = [];
        foreach ($this->tags as $tag => $ids) {
            if (in_array($id, $ids, true) || in_array($resolved, $ids, true)) {
                $tags[] = $tag;
            }
        }

        return [
            'id' => $id,
            'resolvedId' => $resolved,
            'type' => $type,
            'binding' => $binding,
            'tags' => $tags,
            'hasExtenders' => isset($this->extenders[$resolved]),
        ];
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
     * Checks if an ID has any existing registration (shared, factory, instance, or lazy).
     *
     * @param string $id The entry identifier.
     * @return bool
     */
    private function isRegistered(string $id): bool
    {
        return isset($this->shared[$id])
            || isset($this->factories[$id])
            || isset($this->instances[$id])
            || isset($this->lazyFactories[$id]);
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
     * Throws if the container is frozen.
     *
     * @return void
     *
     * @throws ContainerException If the container is frozen.
     */
    private function guardAgainstFrozen(): void
    {
        if ($this->frozen) {
            throw new ContainerException('Cannot modify a frozen container.');
        }
    }

    /**
     * Ensures a factory/shared callback returned an object.
     *
     * @param mixed  $value The returned value to check.
     * @param string $id    The service identifier (for the error message).
     * @param string $type  The registration type label ("Shared", "Factory", "Make", or "Lazy").
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
     * Applies the full post-resolution pipeline to a freshly created service.
     *
     * Pipeline order: extenders -> inflectors -> resolving callbacks -> after-resolving callbacks.
     *
     * @param string $id  The service identifier.
     * @param object $obj The resolved service instance.
     * @return object The finalized service instance (possibly decorated by extenders).
     */
    private function finalizeService(string $id, object $obj): object
    {
        $obj = $this->applyExtenders($id, $obj);
        $this->applyInflectors($obj);
        $this->fireResolvingCallbacks($id, $obj);
        $this->fireAfterResolvingCallbacks($id, $obj);

        return $obj;
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

    /**
     * Applies all matching inflectors to a resolved service.
     *
     * @param object $obj The resolved service instance.
     * @return void
     */
    private function applyInflectors(object $obj): void
    {
        foreach ($this->inflectors as $type => $callbacks) {
            if ($obj instanceof $type) {
                foreach ($callbacks as $callback) {
                    $callback($obj, $this);
                }
            }
        }
    }

    /**
     * Fires ID-specific and global resolving callbacks.
     *
     * @param string $id  The service identifier.
     * @param object $obj The resolved service instance.
     * @return void
     */
    private function fireResolvingCallbacks(string $id, object $obj): void
    {
        foreach ($this->resolvingCallbacks[$id] ?? [] as $callback) {
            $callback($obj, $this);
        }

        foreach ($this->globalResolvingCallbacks as $callback) {
            $callback($obj, $this);
        }
    }

    /**
     * Fires ID-specific and global after-resolving callbacks.
     *
     * @param string $id  The service identifier.
     * @param object $obj The resolved service instance.
     * @return void
     */
    private function fireAfterResolvingCallbacks(string $id, object $obj): void
    {
        foreach ($this->afterResolvingCallbacks[$id] ?? [] as $callback) {
            $callback($obj, $this);
        }

        foreach ($this->globalAfterResolvingCallbacks as $callback) {
            $callback($obj, $this);
        }
    }

    /**
     * Creates a LazyProxy for the given factory.
     *
     * @param string                              $id      The service identifier.
     * @param callable(ContainerInterface): object $factory The factory to wrap.
     * @return LazyProxy
     */
    private function buildLazyProxy(string $id, callable $factory): LazyProxy
    {
        return new LazyProxy(function () use ($factory, $id): object {
            $obj = $factory($this);
            $this->guardObjectReturn($obj, $id, 'Lazy');

            return $this->finalizeService($id, $obj);
        });
    }
}
