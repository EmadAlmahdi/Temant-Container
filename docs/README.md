# Temant Container — Documentation

A lightweight, [PSR-11](https://www.php-fig.org/psr/psr-11/) dependency injection
container for PHP 8.5+ with reflection-based autowiring.

```php
use Temant\Container\Container;

$container = new Container();

$container->set(Mailer::class, fn($c) => new Mailer($c->get(Transport::class)));

$mailer = $container->get(Mailer::class); // same instance every time
```

## Contents

### Guide

| # | Page | What it covers |
|---|------|----------------|
| 1 | [Getting started](01-getting-started.md) | Install, create a container, the three ways an entry is produced |
| 2 | [Registering services](02-registering-services.md) | `set` / `singleton` / `factory` / `instance` / `multi`, conditional registration |
| 3 | [Autowiring](03-autowiring.md) | Reflection resolution, union types, `#[Inject]`, variadics, the resolution rules |
| 4 | [Binding & contextual bindings](04-binding-and-context.md) | `bind` / `alias`, `when()->needs()->give()`, `giveTagged()` |
| 5 | [Tags & decoration](05-tags-and-decoration.md) | `tag` / `tagged`, `extend`, `inflect` |
| 6 | [Events](06-events.md) | `resolving()` / `afterResolving()` |
| 7 | [Lazy services](07-lazy-services.md) | Native lazy objects, the `LazyProxy` fallback, `initialized()` |
| 8 | [Service providers](08-service-providers.md) | `register()` / `boot()` lifecycle |
| 9 | [Calling callables](09-calling-callables.md) | `call()`, `Class@method`, named overrides |
| 10 | [Child containers](10-child-containers.md) | Scoped containers with parent fallback |
| 11 | [Lifecycle](11-lifecycle.md) | `remove` / `clear` / `flushInstances` / `freeze` / `warmUp` |
| 12 | [Introspection](12-introspection.md) | `keys` / `all` / `getDefinition` / `initialized` |
| 13 | [Exceptions](13-exceptions.md) | The exception hierarchy and when each is thrown |

### Reference

- [API reference](api-reference.md) — every public method in one table
- [Architecture](architecture.md) — how the container is put together internally
- [Upgrading from 2.x](upgrading.md) — breaking changes and migration steps

## At a glance

| You want to… | Use |
|--------------|-----|
| One shared instance | [`set()` / `singleton()`](02-registering-services.md#shared-singleton) |
| A new instance every time | [`factory()`](02-registering-services.md#factory) |
| Register an object you already have | [`instance()`](02-registering-services.md#pre-built-instance) |
| Map an interface to an implementation | [`bind()`](04-binding-and-context.md#bindings) |
| Different implementation per consumer | [`when()->needs()->give()`](04-binding-and-context.md#contextual-bindings) |
| Collect a group of services | [`tag()` / `tagged()`](05-tags-and-decoration.md#tags) |
| Wrap a service after creation | [`extend()`](05-tags-and-decoration.md#extend-decoration) |
| Defer an expensive service | [`lazy()`](07-lazy-services.md) |
| A fresh instance, ignoring the cache | [`make()`](09-calling-callables.md#fresh-instances-with-make) |
| Invoke a function with DI | [`call()`](09-calling-callables.md) |
| Lock the container for production | [`warmUp()` + `freeze()`](11-lifecycle.md#freeze) |
