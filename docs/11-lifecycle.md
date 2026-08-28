# Lifecycle

## `remove()`

Delete one entry — its definition, cached instance, binding, and any
[extenders](05-tags-and-decoration.md#extend-decoration) for that id.

```php
$container->remove(LoggerInterface::class);
```

Throws a [`ContainerException`](13-exceptions.md#containerexception) if nothing
matched the id. Removing then re-registering is the supported way to replace an
entry.

## `clear()`

Reset the container to its just-constructed state: every definition, binding,
tag, event, extender, inflector and service provider is dropped, the booted flag
is reset, and — unlike every other mutator — `clear()` also **lifts a
[freeze](#freeze)**.

The `autowiringEnabled` / `cacheAutowire` flags are kept.

## `flushInstances()`

Drop cached singleton instances but **keep every definition**. The next `get()`
rebuilds each singleton from its factory.

```php
$container->flushInstances();
```

[Lazy proxies](07-lazy-services.md) are rebuilt, so they still defer correctly.
This is the tool for resetting state between requests in a long-running worker
without losing configuration.

## Freeze

`freeze()` locks the container. Every mutating method then throws
[`FrozenContainerException`](13-exceptions.md#frozencontainerexception) (a
subclass of `ContainerException`). Resolution keeps working normally.

```php
$container->freeze();
$container->isFrozen();                       // true
$container->set(X::class, fn() => new X());   // throws FrozenContainerException
```

Frozen methods: `set`, `singleton`, `factory`, `instance`, `lazy`, the `*If`
variants, `bind`, `alias`, `when()->…`, `tag`, `extend`, `inflect`, `resolving`,
`afterResolving`, `remove`.

Only [`clear()`](#clear) unfreezes.

## Warm-up

`warmUp()` eagerly resolves every registered
[shared](02-registering-services.md#shared-singleton) service, moving
instantiation cost to start-up. Already-resolved singletons are skipped.

```php
$container->warmUp();
$container->freeze();
```

## Recommended production sequence

```php
$container = new Container();

foreach ($providers as $provider) {
    $container->register($provider);
}
$container->boot();

$container->warmUp(); // pay instantiation cost now
$container->freeze(); // catch accidental late mutation
```

In a worker that reuses the container across requests, skip `freeze()` and call
[`flushInstances()`](#flushinstances) between requests instead.
