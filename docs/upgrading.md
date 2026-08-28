# Upgrading from 2.x to 3.0

Version 3 keeps the public surface you use every day — `Container`,
`ContainerInterface`, `ServiceProviderInterface`, `ContextualBindingBuilder` —
but rebuilds the internals and sharpens autowiring. Most applications need only
the two namespace changes below.

## Required changes

### 1. `LazyProxy` moved namespace

```diff
-use Temant\Container\LazyProxy;
+use Temant\Container\Proxy\LazyProxy;
```

### 2. Internal resolver namespace moved

Only relevant if you referenced these directly (they were `@internal`):

```diff
-Temant\Container\Resolver\Resolver
-Temant\Container\Resolver\ConstructorResolver
-Temant\Container\Resolver\ParameterResolver
+Temant\Container\Resolution\Resolver
+Temant\Container\Resolution\ConstructorResolver
+Temant\Container\Resolution\ParameterResolver
```

Their constructors changed: the by-reference `array &$resolvingStack` argument is
now a `Temant\Container\Resolution\ResolvingStack` object.

## Behaviour changes to review

### `lazy()` returns a native lazy object

For a concrete class id, `get()` now returns a **type-transparent native lazy
object** instead of a `LazyProxy`. `instanceof` now works — but the inspection
API differs:

```diff
-$proxy = $container->get(HeavyService::class);
-$proxy->isInitialized();
+$container->initialized(HeavyService::class);
```

`LazyProxy` (with `isInitialized()` / `getTarget()`) is still returned when the
id resolves to an **interface** or the entry has `extend()` decorators. See
[Lazy services](07-lazy-services.md).

### Union types are now autowired

A `Foo|Bar` parameter used to throw immediately. It now resolves — a bound
member first, then the first autowirable member. `UnresolvableParameterException`
is thrown only when *no* member can be produced. If you relied on the old
throw-always behaviour, add an explicit
[contextual binding](04-binding-and-context.md#contextual-bindings) for the
consumer.

### `has()` is stricter

`has()` now returns `false` for an existing but non-instantiable class (an
abstract class or interface) that has no binding. Previously it returned `true`
even though `get()` could not build it. If you depended on the old result, add
an explicit `bind()`.

### Frozen mutations throw a subclass

Mutating a [frozen](11-lifecycle.md#freeze) container now throws
`FrozenContainerException`. It extends `ContainerException`, so
`catch (ContainerException)` is unaffected; catch the new type if you want to
distinguish it.

### `extend()` before registration

Calling `extend()` for an id that isn't registered yet no longer throws — the
extender queues and applies on the next resolution.

### Tags de-duplicate

`tag($id, $tag)` twice with the same pair now stores one entry;
[`tagged()`](05-tags-and-decoration.md#tags) will not return duplicates.

## New in 3.0

- [`#[Inject(id)]`](03-autowiring.md#the-inject-attribute) attribute
- [`when()->needs()->giveTagged('tag')`](04-binding-and-context.md#a-list-or-a-tag-for-variadics)
  and `give([...ids])` for variadics
- [`Container::initialized($id)`](12-introspection.md#initialized)
- `ContainerInterface` gained `tagged()` and `initialized()`

See [`CHANGELOG.md`](../CHANGELOG.md) for the full list.
