# Architecture

`Container` is a **facade**. It holds only container-wide state — the frozen
flag, the autowiring flags, the parent link — and the `get()` / `make()` /
`has()` control flow. Every other concern lives in a focused collaborator that
the facade composes in its constructor.

```
Temant\Container\
├── Container.php              facade + get()/make()/has()
├── ContainerInterface.php     extended PSR-11 contract (adds tagged, initialized, ...)
├── ServiceProviderInterface.php
├── ContextualBindingBuilder.php   fluent when()->needs()->give()
│
├── Contract/
│   └── ResolverContainer.php   the narrow view the resolution layer depends on
│
├── Definition/
│   ├── DefinitionMap.php       shared / factory / instance / lazy storage + guards
│   └── BindingMap.php          alias chains + loop detection
│
├── Registry/
│   ├── TagRegistry.php
│   ├── ContextualBindingRegistry.php
│   └── ProviderRegistry.php    register/boot lifecycle
│
├── Resolution/
│   ├── Resolver.php            entry point: class + callable resolution
│   ├── ConstructorResolver.php reflect a constructor, build the class
│   ├── ParameterResolver.php   resolve one parameter (types, unions, #[Inject], variadics)
│   ├── ResolvingStack.php      the in-progress stack (circular-dep detection, consumer context)
│   └── ResolutionPipeline.php  extenders -> inflectors -> resolving/afterResolving events
│
├── Attribute/
│   └── Inject.php              #[Inject(id)]
│
├── Proxy/
│   ├── LazyProxy.php           magic-method fallback proxy
│   └── LazyObjectFactory.php   builds a native lazy object, or the fallback
│
└── Exception/
    ├── ContainerException.php
    ├── FrozenContainerException.php
    ├── NotFoundException.php
    ├── ClassResolutionException.php
    └── UnresolvableParameterException.php
```

## Dependency direction

The resolution layer never depends on the concrete `Container`. It depends on
`Contract\ResolverContainer`, a small interface exposing exactly what a resolver
needs:

```php
interface ResolverContainer extends Psr\Container\ContainerInterface
{
    public function hasAutowiring(): bool;
    public function isBound(string $id): bool;
    public function tagged(string $tag): array;
    public function getContextualBinding(string $consumer, string $abstract): string|Closure|array|null;
}
```

`Container` implements it. This keeps the arrows pointing one way
(facade → collaborators) and lets each resolver be unit-tested against a light
fake.

## What the facade delegates

| Facade methods | Collaborator |
|----------------|--------------|
| `set` `singleton` `factory` `instance` `multi` `lazy` `*If` | `DefinitionMap` (+ `LazyObjectFactory` for `lazy`) |
| `bind` `alias`, all id resolution | `BindingMap` |
| `tag` `tagged` | `TagRegistry` |
| `when` / `addContextualBinding` / `getContextualBinding` | `ContextualBindingRegistry` |
| `extend` `inflect` `resolving` `afterResolving` | `ResolutionPipeline` |
| `register` `boot` | `ProviderRegistry` |
| `call`, autowiring | `Resolver` |

The facade keeps the frozen-container and duplicate-id guards at the delegation
boundary so collaborators stay pure data structures.

## Resolution walk-through

`get(id)`:

1. `BindingMap::resolve(id)` — follow the alias chain.
2. `DefinitionMap::instanceFor(id)` — cached instance? return it (skips the pipeline).
3. `DefinitionMap::sharedFactory(id)` — run once, `ResolutionPipeline::process`, cache, return.
4. `DefinitionMap::factoryFor(id)` — run, `ResolutionPipeline::process`, return (no cache).
5. Parent container, if any.
6. `Resolver::resolve(id)` — autowire, `ResolutionPipeline::process`, cache if `cacheAutowire`.
7. `NotFoundException`.

`Resolver` drives `ConstructorResolver`, which pushes the class onto a shared
`ResolvingStack` (for circular-dependency detection) and asks `ParameterResolver`
for each argument. `ParameterResolver` reads `ResolvingStack::current()` to know
which consumer a contextual binding applies to.
