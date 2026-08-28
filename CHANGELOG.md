# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-08-28

Version 3 keeps the public surface you use every day -- `Container`,
`ContainerInterface`, `ServiceProviderInterface`, `ContextualBindingBuilder` -- but
rebuilds the internals into small, focused collaborators and sharpens autowiring.

### Added

- **Reflection cache.** Autowiring reflection (constructor + parameter analysis) is
  now cached as a plain data structure and reused, so repeated `make()` calls,
  factory-defined autowired dependencies, and tagged resolution no longer reflect
  every time. Pass a PSR-16 cache as the new third constructor argument
  (`reflectionCache:`) to persist it across processes; `prewarmReflection()` warms
  it without instantiating. Adds a `psr/simple-cache` (interfaces-only) dependency.
- **Native lazy objects.** `lazy()` now returns a real, type-transparent instance of
  the target class (PHP 8.4+ lazy proxies). `instanceof` checks and type hints work.
  The magic-method `LazyProxy` remains as a fallback for interface IDs and decorated
  services.
- **`Container::initialized(string $id): bool`** -- reports whether a lazy service's
  factory has run yet.
- **Union-type autowiring.** A `Foo|Bar` parameter is resolved by trying each member:
  an explicitly bound member wins first, then the first autowirable member, then
  `null` / the default value, then an exception.
- **`#[Inject(id)]` attribute** for constructor and callable parameters -- resolve a
  parameter from a specific container ID instead of by type. Works on variadics too.
- **`when()->needs()->giveTagged('tag')`** and **`give([Id::class, ...])`** for
  satisfying typed variadic parameters through contextual bindings.
- **`FrozenContainerException`** (extends `ContainerException`) thrown by mutators on
  a frozen container.
- `LICENSE`, `CHANGELOG.md`, `.editorconfig`, Dependabot, and a `--prefer-lowest` CI
  leg.

### Changed

- **Internal namespaces moved:**
  - `Temant\Container\LazyProxy` -> `Temant\Container\Proxy\LazyProxy`
  - `Temant\Container\Resolver\*` -> `Temant\Container\Resolution\*` (internal)
- **`Container` decomposed** into `Definition\DefinitionMap`, `Definition\BindingMap`,
  `Registry\{TagRegistry, ContextualBindingRegistry, ProviderRegistry}`,
  `Reflection\{ReflectionCache, ConstructorDescriptor, ParameterDescriptor}`,
  `Resolution\{Resolver, ConstructorResolver, ParameterResolver, ResolvingStack,
  ResolutionPipeline}`, and `Proxy\LazyObjectFactory`. The resolution layer now
  depends on `Contract\ResolverContainer`, not the concrete class; the internal
  `Resolver` / `ConstructorResolver` constructors changed shape.
- **`extend()` may be called before the target is registered** -- extenders queue and
  apply on the next resolution instead of throwing.
- **`has()` returns `false` for an existing but non-instantiable class** (abstract /
  interface) with no binding. Previously it returned `true` even though `get()` could
  not build it.
- **Tags de-duplicate** -- tagging the same ID under the same tag twice is a no-op.
- `ContainerInterface` gained `tagged()` and `initialized()`.
- `Tests/` is reorganised into `Tests/Unit` and `Tests/Feature`.

### Removed

- Union types are no longer an automatic error -- see "Union-type autowiring" above.
  `UnresolvableParameterException::unionTypeNotSupported()` is now thrown only when no
  member of a union can be resolved.

### Migration from 2.x

- Replace `use Temant\Container\LazyProxy;` with `use Temant\Container\Proxy\LazyProxy;`.
- If you referenced `Temant\Container\Resolver\*` directly (it was `@internal`), switch
  to `Temant\Container\Resolution\*`.
- If you relied on `get()` of a lazy service returning a `LazyProxy` for a concrete
  class, it now returns a native lazy instance. Use `Container::initialized($id)` for
  state instead of `LazyProxy::isInitialized()`, or register the ID as an interface to
  keep the old proxy.
- If you caught the generic `ContainerException` from a frozen-container mutation,
  nothing changes (`FrozenContainerException` extends it). Catch the new class if you
  want to distinguish it.
- If your code depended on `has()` returning `true` for an abstract class or interface,
  add an explicit `bind()`.

## [2.0.0]

Baseline release prior to the version-3 overhaul.
