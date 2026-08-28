# API reference

Every public method of `Temant\Container\Container`. Registration methods return
`$this` and are chainable.

## Construction

| Signature | |
|-----------|--|
| `__construct(bool $autowiringEnabled = true, bool $cacheAutowire = true, ?Psr\SimpleCache\CacheInterface $reflectionCache = null)` | See [Getting started](01-getting-started.md#create-a-container) and [Performance](14-performance.md) |

## Registration → [docs](02-registering-services.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `set(string $id, callable $factory)` | `$this` | Shared (singleton) service |
| `singleton(string $id, callable $factory)` | `$this` | Alias for `set()` |
| `factory(string $id, callable $factory)` | `$this` | New instance on every `get()` |
| `instance(string $id, object $object)` | `$this` | Store a pre-built object |
| `multi(array $definitions)` | `$this` | Bulk-register shared services |
| `lazy(string $id, callable $factory)` | `$this` | [Deferred](07-lazy-services.md) shared service |
| `setIf(string $id, callable $factory)` | `$this` | `set()` only if `$id` is unbound |
| `singletonIf(string $id, callable $factory)` | `$this` | Alias for `setIf()` |
| `factoryIf(string $id, callable $factory)` | `$this` | `factory()` only if `$id` is unbound |
| `instanceIf(string $id, object $object)` | `$this` | `instance()` only if `$id` is unbound |

## Binding & context → [docs](04-binding-and-context.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `bind(string $abstract, string $target)` | `$this` | Point an abstract id at a concrete id |
| `alias(string $alias, string $target)` | `$this` | Alias for `bind()` |
| `when(string $consumer)` | `ContextualBindingBuilder` | Start a contextual binding |
| &nbsp;&nbsp;`->needs(string $abstract)` | `$this` | The dependency type |
| &nbsp;&nbsp;`->give(string\|Closure\|list<string> $concrete)` | `void` | What to provide |
| &nbsp;&nbsp;`->giveTagged(string $tag)` | `void` | Provide every service under a tag |

## Tags & decoration → [docs](05-tags-and-decoration.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `tag(string $id, string $tag)` | `$this` | Add an id to a tag (de-duplicated) |
| `tagged(string $tag)` | `list<object>` | Resolve every service under a tag |
| `extend(string $id, Closure $extender)` | `$this` | Decorate/replace a service after resolution |
| `inflect(string $type, Closure $callback)` | `$this` | Mutate every resolved `instanceof $type` |

## Events → [docs](06-events.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `resolving(string\|Closure $idOrCallback, ?Closure $callback = null)` | `$this` | Callback before a service is returned |
| `afterResolving(string\|Closure $idOrCallback, ?Closure $callback = null)` | `$this` | Callback after the full pipeline |

## Service providers → [docs](08-service-providers.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `register(ServiceProviderInterface $provider)` | `$this` | Register (and boot, if already booted) a provider |
| `boot()` | `void` | Boot every registered provider once |

## Resolution → [docs](09-calling-callables.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `get(string $id)` | `mixed` | Retrieve an entry (PSR-11) |
| `has(string $id)` | `bool` | Whether `get()` would succeed (PSR-11) |
| `make(string $id, array $parameters = [])` | `object` | Fresh instance, bypassing the cache |
| `call(callable\|string $callable, array $namedOverrides = [])` | `mixed` | Invoke a callable with DI |

## Child containers → [docs](10-child-containers.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `createChild()` | `Container` | Child container with parent fallback |
| `getParent()` | `?Container` | Parent container, or `null` |

## Lifecycle → [docs](11-lifecycle.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `remove(string $id)` | `void` | Delete one entry (throws if absent) |
| `clear()` | `void` | Reset everything, including the frozen flag |
| `flushInstances()` | `void` | Drop cached instances, keep definitions |
| `freeze()` | `void` | Reject all further mutation |
| `isFrozen()` | `bool` | Whether the container is frozen |
| `warmUp()` | `void` | Eagerly resolve every shared service |
| `prewarmReflection(iterable<class-string> $classes)` | `void` | Build & cache autowiring reflection without instantiating — see [Performance](14-performance.md) |

## Autowiring → [docs](03-autowiring.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `setAutowiring(bool $enabled)` | `void` | Turn reflection autowiring on/off |
| `hasAutowiring()` | `bool` | Whether autowiring is on |

## Introspection → [docs](12-introspection.md)

| Method | Returns | Description |
|--------|---------|-------------|
| `keys()` | `list<string>` | Every registered id |
| `all()` | `array` | `{shared, factories, instances, bindings, tags}` |
| `allShared()` / `allFactories()` / `allInstances()` / `allBindings()` | `array` | One section of `all()` |
| `getDefinition(string $id)` | `?array` | Registration details, or `null` |
| `initialized(string $id)` | `bool` | Whether a lazy service's factory has run |
| `isBound(string $id)` | `bool` | Whether `$id` has a definition or binding (ignores autowiring) |

## Related types

| Type | Namespace |
|------|-----------|
| `ContainerInterface` | `Temant\Container` — extends `Psr\Container\ContainerInterface` |
| `ServiceProviderInterface` | `Temant\Container` |
| `Inject` | `Temant\Container\Attribute` |
| `LazyProxy` | `Temant\Container\Proxy` |
| Exceptions | `Temant\Container\Exception\*` — see [Exceptions](13-exceptions.md) |
