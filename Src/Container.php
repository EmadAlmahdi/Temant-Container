<?php

declare(strict_types=1);

namespace Temant\Container;

use Closure;
use Exception;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use Temant\Container\Contract\ResolverContainer;
use Temant\Container\Definition\BindingMap;
use Temant\Container\Definition\DefinitionMap;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\FrozenContainerException;
use Temant\Container\Exception\NotFoundException;
use Temant\Container\Proxy\LazyObjectFactory;
use Temant\Container\Reflection\ReflectionCache;
use Temant\Container\Registry\ContextualBindingRegistry;
use Temant\Container\Registry\ProviderRegistry;
use Temant\Container\Registry\TagRegistry;
use Temant\Container\Resolution\ResolutionPipeline;
use Temant\Container\Resolution\Resolver;

use function class_exists;
use function explode;
use function get_debug_type;
use function in_array;
use function is_object;
use function is_string;
use function str_contains;

/**
 * A lightweight, PSR-11 compliant dependency injection container with autowiring.
 *
 * This class is the public facade. It owns only container-wide state (frozen flag,
 * autowiring flags, parent link) and the {@see get()} / {@see make()} / {@see has()}
 * resolution flow. Everything else is delegated to focused collaborators:
 *
 * | Concern                         | Collaborator                     |
 * |---------------------------------|----------------------------------|
 * | shared/factory/instance/lazy    | {@see DefinitionMap}             |
 * | interface bindings & aliases    | {@see BindingMap}                |
 * | tags                            | {@see TagRegistry}               |
 * | contextual bindings             | {@see ContextualBindingRegistry} |
 * | extenders / inflectors / events | {@see ResolutionPipeline}        |
 * | service providers               | {@see ProviderRegistry}          |
 * | autowiring & callable invocation| {@see Resolver}                  |
 * | lazy proxies                    | {@see LazyObjectFactory}         |
 */
class Container implements ContainerInterface, ResolverContainer
{
    private readonly DefinitionMap $definitions;

    private readonly BindingMap $bindings;

    private readonly TagRegistry $tags;

    private readonly ContextualBindingRegistry $contextual;

    private readonly ResolutionPipeline $pipeline;

    private readonly ProviderRegistry $providers;

    private readonly LazyObjectFactory $lazyObjects;

    private ReflectionCache $reflectionCache;

    private Resolver $resolver;

    private bool $frozen = false;

    private ?Container $parent = null;

    /**
     * @param bool $autowiringEnabled Resolve unregistered classes via reflection.
     * @param bool $cacheAutowire Cache autowired instances as singletons.
     * @param CacheInterface|null $reflectionCache Optional PSR-16 store that persists
     *        autowiring reflection results across processes. See {@see ReflectionCache}.
     */
    public function __construct(
        private bool $autowiringEnabled = true,
        private bool $cacheAutowire = true,
        ?CacheInterface $reflectionCache = null,
    ) {
        $this->definitions = new DefinitionMap();
        $this->bindings = new BindingMap();
        $this->tags = new TagRegistry();
        $this->contextual = new ContextualBindingRegistry();
        $this->pipeline = new ResolutionPipeline();
        $this->providers = new ProviderRegistry();
        $this->lazyObjects = new LazyObjectFactory();
        $this->reflectionCache = new ReflectionCache($reflectionCache);
        $this->resolver = new Resolver($this, $this->reflectionCache);
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Registers a shared (singleton) entry. The factory runs once on first {@see get()}.
     *
     * @param callable(ResolverContainer): object $concrete
     * @return $this
     *
     * @throws ContainerException If the ID is already registered or the container is frozen.
     */
    public function set(string $id, callable $concrete): self
    {
        $this->guardMutation('set');
        $this->guardDuplicate($id);
        $this->definitions->share($id, $concrete);

        return $this;
    }

    /**
     * Alias for {@see set()}.
     *
     * @param callable(ResolverContainer): object $concrete
     * @return $this
     */
    public function singleton(string $id, callable $concrete): self
    {
        return $this->set($id, $concrete);
    }

    /**
     * Registers a factory entry -- a new instance on every {@see get()}.
     *
     * @param callable(ResolverContainer): object $concrete
     * @return $this
     *
     * @throws ContainerException If the ID is already registered or the container is frozen.
     */
    public function factory(string $id, callable $concrete): self
    {
        $this->guardMutation('factory');
        $this->guardDuplicate($id);
        $this->definitions->defineFactory($id, $concrete);

        return $this;
    }

    /**
     * Registers a pre-built object. The same instance is returned every time.
     *
     * @return $this
     *
     * @throws ContainerException If the ID is already registered or the container is frozen.
     */
    public function instance(string $id, object $object): self
    {
        $this->guardMutation('instance');
        $this->guardDuplicate($id);
        $this->definitions->instance($id, $object);

        return $this;
    }

    /**
     * Registers multiple shared entries at once.
     *
     * @param array<string, callable(ResolverContainer): object> $definitions
     * @return $this
     *
     * @throws ContainerException If any ID is already registered or the container is frozen.
     */
    public function multi(array $definitions): self
    {
        foreach ($definitions as $id => $concrete) {
            $this->set($id, $concrete);
        }

        return $this;
    }

    /**
     * Registers a lazy shared entry. {@see get()} returns a stand-in whose real
     * instance is created on first use -- a native lazy object where possible
     * (type-transparent), otherwise a {@see Proxy\LazyProxy}.
     *
     * @param callable(ResolverContainer): object $concrete
     * @return $this
     *
     * @throws ContainerException If the ID is already registered or the container is frozen.
     */
    public function lazy(string $id, callable $concrete): self
    {
        $this->guardMutation('lazy');
        $this->guardDuplicate($id);

        $this->definitions->lazy($id, $concrete);
        $this->definitions->cacheInstance($id, $this->buildLazyProxy($id, $concrete));

        return $this;
    }

    /**
     * Registers a shared entry only if the ID is not already registered.
     *
     * @param callable(ResolverContainer): object $concrete
     * @return $this
     */
    public function setIf(string $id, callable $concrete): self
    {
        if (!$this->definitions->isRegistered($id)) {
            $this->set($id, $concrete);
        }

        return $this;
    }

    /**
     * Alias for {@see setIf()}.
     *
     * @param callable(ResolverContainer): object $concrete
     * @return $this
     */
    public function singletonIf(string $id, callable $concrete): self
    {
        return $this->setIf($id, $concrete);
    }

    /**
     * Registers a factory entry only if the ID is not already registered.
     *
     * @param callable(ResolverContainer): object $concrete
     * @return $this
     */
    public function factoryIf(string $id, callable $concrete): self
    {
        if (!$this->definitions->isRegistered($id)) {
            $this->factory($id, $concrete);
        }

        return $this;
    }

    /**
     * Registers an instance only if the ID is not already registered.
     *
     * @return $this
     */
    public function instanceIf(string $id, object $object): self
    {
        if (!$this->definitions->isRegistered($id)) {
            $this->instance($id, $object);
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Bindings / aliases
    // -------------------------------------------------------------------------

    /**
     * Binds an abstract ID (typically an interface) to a concrete target ID.
     * Chains are followed: `A -> B -> C`.
     *
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function bind(string $abstract, string $target): self
    {
        $this->guardMutation('bind');
        $this->bindings->bind($abstract, $target);

        return $this;
    }

    /**
     * Alias for {@see bind()}.
     *
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function alias(string $alias, string $target): self
    {
        return $this->bind($alias, $target);
    }

    // -------------------------------------------------------------------------
    // Contextual bindings
    // -------------------------------------------------------------------------

    /**
     * Begins a contextual binding: `$container->when(X)->needs(Y)->give(Z)`.
     */
    public function when(string $consumer): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $consumer);
    }

    /**
     * Records a contextual binding.
     *
     * @param string|Closure|list<string> $concrete
     *
     * @throws ContainerException If the container is frozen.
     *
     * @internal Called by {@see ContextualBindingBuilder}.
     */
    public function addContextualBinding(string $consumer, string $abstract, string|Closure|array $concrete): void
    {
        $this->guardMutation('when');
        $this->contextual->add($consumer, $abstract, $concrete);
    }

    /**
     * @return string|Closure|list<string>|null
     *
     * @internal Used by the resolution layer.
     */
    public function getContextualBinding(string $consumer, string $abstract): string|Closure|array|null
    {
        return $this->contextual->find($consumer, $abstract);
    }

    // -------------------------------------------------------------------------
    // Tagging
    // -------------------------------------------------------------------------

    /**
     * Tags a service ID with a group name. Tagging the same ID twice is a no-op.
     *
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function tag(string $id, string $tag): self
    {
        $this->guardMutation('tag');
        $this->tags->add($id, $tag);

        return $this;
    }

    /**
     * Resolves every service registered under a tag.
     *
     * @return list<object>
     */
    public function tagged(string $tag): array
    {
        $out = [];

        foreach ($this->tags->idsFor($tag) as $id) {
            /** @var object $service */
            $service = $this->get($id);
            $out[] = $service;
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Decoration, inflectors, events
    // -------------------------------------------------------------------------

    /**
     * Registers a decorator applied after an entry is resolved. May be registered
     * before the entry itself.
     *
     * @param Closure(object, ResolverContainer): object $extender
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function extend(string $id, Closure $extender): self
    {
        $this->guardMutation('extend');
        $this->pipeline->extend($id, $extender);

        return $this;
    }

    /**
     * Registers a type-matched, in-place mutator (e.g. setter injection).
     *
     * @param Closure(object, ResolverContainer): void $callback
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function inflect(string $type, Closure $callback): self
    {
        $this->guardMutation('inflect');
        $this->pipeline->inflect($type, $callback);

        return $this;
    }

    /**
     * Registers a resolving callback -- ID-specific if a string is given first,
     * global if a Closure is given.
     *
     * @param string|Closure(object, ResolverContainer): void $idOrCallback
     * @param (Closure(object, ResolverContainer): void)|null $callback
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function resolving(string|Closure $idOrCallback, ?Closure $callback = null): self
    {
        $this->guardMutation('resolving');
        $this->pipeline->resolving($idOrCallback, $callback);

        return $this;
    }

    /**
     * Registers an after-resolving callback.
     *
     * @param string|Closure(object, ResolverContainer): void $idOrCallback
     * @param (Closure(object, ResolverContainer): void)|null $callback
     * @return $this
     *
     * @throws ContainerException If the container is frozen.
     */
    public function afterResolving(string|Closure $idOrCallback, ?Closure $callback = null): self
    {
        $this->guardMutation('afterResolving');
        $this->pipeline->afterResolving($idOrCallback, $callback);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Service providers
    // -------------------------------------------------------------------------

    /**
     * Registers a service provider, calling {@see ServiceProviderInterface::register()}
     * immediately (and {@see ServiceProviderInterface::boot()} too if already booted).
     *
     * @return $this
     */
    public function register(ServiceProviderInterface $provider): self
    {
        $this->providers->add($provider, $this);

        return $this;
    }

    /**
     * Boots every registered provider once.
     */
    public function boot(): void
    {
        $this->providers->boot($this);
    }

    // -------------------------------------------------------------------------
    // Resolution (PSR-11)
    // -------------------------------------------------------------------------

    /**
     * Retrieves an entry.
     *
     * Order: cached instance -> shared factory -> factory -> parent container ->
     * autowiring -> {@see NotFoundException}. Extenders, inflectors and events run
     * on every fresh resolution (not on cached-singleton hits).
     *
     * @throws NotFoundException If the entry cannot be found or autowired.
     * @throws ContainerException For any other resolution failure.
     */
    public function get(string $id): mixed
    {
        $id = $this->bindings->resolve($id);

        try {
            $cached = $this->definitions->instanceFor($id);
            if ($cached !== null) {
                return $cached;
            }

            $shared = $this->definitions->sharedFactory($id);
            if ($shared !== null) {
                $instance = $this->finalize($id, $this->invokeFactory($shared, $id, 'Shared'));
                $this->definitions->cacheInstance($id, $instance);

                return $instance;
            }

            $factory = $this->definitions->factoryFor($id);
            if ($factory !== null) {
                return $this->finalize($id, $this->invokeFactory($factory, $id, 'Factory'));
            }

            if ($this->parent !== null && $this->parent->has($id)) {
                return $this->parent->get($id);
            }

            if ($this->autowiringEnabled && $this->isAutowirable($id)) {
                $instance = $this->finalize($id, $this->resolver->resolve($id));

                if ($this->cacheAutowire) {
                    $this->definitions->cacheInstance($id, $instance);
                }

                return $instance;
            }

            throw NotFoundException::forEntry($id);
        } catch (NotFoundException | ContainerException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ContainerException("Error resolving entry '{$id}'.", 0, $e);
        }
    }

    /**
     * Whether an entry can be resolved: it has a definition or cached instance, is
     * resolvable by the parent container, or is an instantiable autowirable class.
     */
    public function has(string $id): bool
    {
        $id = $this->bindings->resolve($id);

        if ($this->definitions->isRegistered($id)) {
            return true;
        }

        if ($this->parent !== null && $this->parent->has($id)) {
            return true;
        }

        return $this->autowiringEnabled && $this->isAutowirable($id);
    }

    /**
     * Creates a fresh instance, bypassing the singleton cache.
     *
     * A registered factory/shared closure is re-invoked (closure-defined services
     * cannot take parameter overrides). An autowired class is rebuilt with the given
     * named constructor-argument overrides.
     *
     * @param array<string, mixed> $parameters
     *
     * @throws NotFoundException If the entry cannot be found or autowired.
     * @throws ContainerException For any other resolution failure.
     */
    public function make(string $id, array $parameters = []): object
    {
        $id = $this->bindings->resolve($id);

        try {
            $factory = $this->definitions->sharedFactory($id) ?? $this->definitions->factoryFor($id);
            if ($factory !== null) {
                return $this->finalize($id, $this->invokeFactory($factory, $id, 'Make'));
            }

            if ($this->parent !== null && $this->parent->has($id)) {
                return $this->parent->make($id, $parameters);
            }

            if ($this->autowiringEnabled && $this->isAutowirable($id)) {
                return $this->finalize($id, $this->resolver->resolve($id, $parameters));
            }

            throw NotFoundException::forEntry($id);
        } catch (NotFoundException | ContainerException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ContainerException("Error making entry '{$id}'.", 0, $e);
        }
    }

    /**
     * Invokes a callable, resolving its parameters from the container.
     *
     * Accepts closures, `[$object, 'method']`, invokable objects, and `'Class@method'`.
     *
     * @param array<string, mixed> $namedOverrides
     */
    public function call(callable|string $callable, array $namedOverrides = []): mixed
    {
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
    // Child containers
    // -------------------------------------------------------------------------

    /**
     * Creates a child container that falls back to this one for unresolved entries.
     */
    public function createChild(): self
    {
        $child = new self($this->autowiringEnabled, $this->cacheAutowire);
        $child->parent = $this;

        // Reflection facts are process-global; share the parent's cache so a child
        // never re-reflects a class the parent already analysed.
        $child->reflectionCache = $this->reflectionCache;
        $child->resolver = new Resolver($child, $this->reflectionCache);

        return $child;
    }

    /**
     * The parent container, or null for a root container.
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    // -------------------------------------------------------------------------
    // Removal / reset
    // -------------------------------------------------------------------------

    /**
     * Removes an entry -- its definition, cached instance, binding and extenders.
     *
     * @throws ContainerException If nothing matched the ID or the container is frozen.
     */
    public function remove(string $id): void
    {
        $this->guardMutation('remove');

        $removed = $this->definitions->forget($id);
        $removed = $this->bindings->forget($id) || $removed;

        if ($this->pipeline->hasExtenders($id)) {
            $this->pipeline->forget($id);
            $removed = true;
        }

        if (!$removed) {
            throw new ContainerException("Cannot remove '{$id}': no entry found in the container.");
        }
    }

    /**
     * Removes every registration and resets all state, including the frozen flag.
     */
    public function clear(): void
    {
        $this->definitions->clear();
        $this->bindings->clear();
        $this->tags->clear();
        $this->contextual->clear();
        $this->pipeline->clear();
        $this->providers->clear();
        // Keep the reflection cache: it holds process facts, not container state.
        $this->resolver = new Resolver($this, $this->reflectionCache);
        $this->frozen = false;
    }

    /**
     * Clears cached instances, keeping definitions. Lazy proxies are rebuilt so they
     * still defer correctly. Useful in long-running workers (Swoole, RoadRunner).
     */
    public function flushInstances(): void
    {
        $this->definitions->flushInstances();

        foreach ($this->definitions->lazyIds() as $id) {
            $factory = $this->definitions->lazyFactory($id);
            if ($factory !== null) {
                $this->definitions->cacheInstance($id, $this->buildLazyProxy($id, $factory));
            }
        }
    }

    // -------------------------------------------------------------------------
    // Freeze / warm-up
    // -------------------------------------------------------------------------

    /**
     * Locks the container: any further mutation throws {@see FrozenContainerException}.
     * Resolution keeps working. {@see clear()} lifts the lock.
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Pre-resolves every registered shared singleton, moving instantiation cost to
     * startup. Already-resolved singletons are skipped.
     */
    public function warmUp(): void
    {
        foreach ($this->definitions->sharedIds() as $id) {
            if ($this->definitions->instanceFor($id) === null) {
                $this->get($id);
            }
        }
    }

    /**
     * Pre-builds and caches the autowiring reflection for the given classes without
     * instantiating them. With a PSR-16 store configured (see the constructor), run
     * this once after deployment so no request pays the reflection cost.
     *
     * @param iterable<class-string> $classes
     */
    public function prewarmReflection(iterable $classes): void
    {
        $this->reflectionCache->warm($classes);
    }

    // -------------------------------------------------------------------------
    // Autowiring configuration
    // -------------------------------------------------------------------------

    /**
     * Enables or disables autowiring at runtime.
     */
    public function setAutowiring(bool $enabled): void
    {
        $this->autowiringEnabled = $enabled;
    }

    public function hasAutowiring(): bool
    {
        return $this->autowiringEnabled;
    }

    /**
     * Whether the ID has an explicit definition, cached instance, or binding --
     * without considering autowiring or the parent container.
     */
    public function isBound(string $id): bool
    {
        return $this->bindings->has($id) || $this->definitions->isRegistered($this->bindings->resolve($id));
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * Whether a lazily registered service has been initialised. False for non-lazy IDs.
     */
    public function initialized(string $id): bool
    {
        $id = $this->bindings->resolve($id);

        if (!$this->definitions->hasLazy($id)) {
            return false;
        }

        $proxy = $this->definitions->instanceFor($id);

        return $proxy !== null && $this->lazyObjects->isInitialized($proxy);
    }

    /**
     * Every registered service ID (shared, factories, instances).
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->definitions->keys();
    }

    /**
     * A structured snapshot of all registrations.
     *
     * @return array{
     *     shared: array<string, callable>,
     *     factories: array<string, callable>,
     *     instances: array<string, object>,
     *     bindings: array<string, string>,
     *     tags: array<string, list<string>>,
     * }
     */
    public function all(): array
    {
        return [
            'shared' => $this->definitions->allShared(),
            'factories' => $this->definitions->allFactories(),
            'instances' => $this->definitions->allInstances(),
            'bindings' => $this->bindings->all(),
            'tags' => $this->tags->all(),
        ];
    }

    /**
     * @return array<string, callable(ResolverContainer): object>
     */
    public function allShared(): array
    {
        return $this->definitions->allShared();
    }

    /**
     * @return array<string, callable(ResolverContainer): object>
     */
    public function allFactories(): array
    {
        return $this->definitions->allFactories();
    }

    /**
     * @return array<string, object>
     */
    public function allInstances(): array
    {
        return $this->definitions->allInstances();
    }

    /**
     * @return array<string, string>
     */
    public function allBindings(): array
    {
        return $this->bindings->all();
    }

    /**
     * Registration details for an ID, without resolving it. Null if unregistered.
     *
     * @return array{
     *     id: string,
     *     resolvedId: string,
     *     type: 'shared'|'factory'|'instance'|'lazy'|null,
     *     binding: string|null,
     *     tags: list<string>,
     *     hasExtenders: bool,
     * }|null
     */
    public function getDefinition(string $id): ?array
    {
        $resolved = $this->bindings->resolve($id);
        $type = $this->definitions->typeOf($resolved);
        $binding = $this->bindings->target($id);

        if ($type === null && $binding === null) {
            return null;
        }

        $tags = $this->tags->tagsFor($id);
        foreach ($this->tags->tagsFor($resolved) as $tag) {
            if (!in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return [
            'id' => $id,
            'resolvedId' => $resolved,
            'type' => $type,
            'binding' => $binding,
            'tags' => $tags,
            'hasExtenders' => $this->pipeline->hasExtenders($resolved),
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Invokes a user-supplied factory and guarantees an object came back.
     *
     * @param callable $factory A registered factory. Its declared return type is
     *                          `object`, but user code can violate that, so the
     *                          result is validated here rather than trusted.
     *
     * @throws ContainerException If the factory does not return an object.
     */
    private function invokeFactory(callable $factory, string $id, string $kind): object
    {
        $value = $factory($this);

        if (!is_object($value)) {
            throw new ContainerException(
                "{$kind} entry '{$id}' must return an object, got " . get_debug_type($value) . '.',
            );
        }

        return $value;
    }

    private function finalize(string $id, object $instance): object
    {
        return $this->pipeline->process($id, $instance, $this);
    }

    /**
     * @phpstan-assert-if-true class-string $id
     */
    private function isAutowirable(string $id): bool
    {
        if (!class_exists($id)) {
            return false;
        }

        return (new ReflectionClass($id))->isInstantiable();
    }

    /**
     * @param callable(ResolverContainer): object $factory
     */
    private function buildLazyProxy(string $id, callable $factory): object
    {
        $initializer = function () use ($factory, $id): object {
            return $this->finalize($id, $this->invokeFactory($factory, $id, 'Lazy'));
        };

        $concrete = $this->bindings->resolve($id);
        $allowNative = !$this->pipeline->hasExtenders($id) && !$this->pipeline->hasExtenders($concrete);

        return $this->lazyObjects->create($concrete, $initializer, $allowNative);
    }

    /**
     * @throws FrozenContainerException If the container is frozen.
     */
    private function guardMutation(string $operation): void
    {
        if ($this->frozen) {
            throw FrozenContainerException::forOperation($operation);
        }
    }

    /**
     * @throws ContainerException If the ID already has a registration.
     */
    private function guardDuplicate(string $id): void
    {
        if ($this->definitions->isRegistered($id)) {
            throw new ContainerException("Entry '{$id}' is already registered in the container.");
        }
    }
}
