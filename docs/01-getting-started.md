# Getting started

## Requirements

- PHP **8.5** or higher
- [Composer](https://getcomposer.org/)

The only runtime dependency is `psr/container`.

## Install

```bash
composer require temant/container
```

## Create a container

```php
use Temant\Container\Container;

$container = new Container();
```

The constructor takes two flags:

```php
new Container(
    autowiringEnabled: true, // resolve unregistered classes by reflection
    cacheAutowire:     true, // cache autowired instances as singletons
);
```

| Flag | `true` (default) | `false` |
|------|------------------|---------|
| `autowiringEnabled` | Unknown classes are built by reflection | Only explicitly registered entries resolve |
| `cacheAutowire` | An autowired class is built once, then reused | A new instance on every `get()` |

Both can be changed later — `autowiringEnabled` via
[`setAutowiring()`](03-autowiring.md#toggling-autowiring); `cacheAutowire` is
fixed for the container's lifetime.

## Retrieve an entry

`get()` and `has()` are the [PSR-11](https://www.php-fig.org/psr/psr-11/) methods.

```php
$container->has(Logger::class); // bool
$logger = $container->get(Logger::class);
```

`get()` throws a
[`NotFoundException`](13-exceptions.md#notfoundexception) when the id is unknown
and cannot be autowired.

## How an entry is produced

When you call `get($id)`, the container works through this list and stops at the
first match:

1. **Alias / binding** — `$id` is rewritten to its final target
   ([binding](04-binding-and-context.md#bindings)).
2. **Cached instance** — a previously resolved singleton or a pre-registered
   object is returned as-is.
3. **Shared factory** — the [`set()`](02-registering-services.md#shared-singleton)
   closure runs once; the result is cached.
4. **Factory** — the [`factory()`](02-registering-services.md#factory) closure
   runs every time; nothing is cached.
5. **Parent container** — if this is a [child container](10-child-containers.md)
   and the parent can resolve `$id`, it does.
6. **Autowiring** — if enabled and `$id` is an instantiable class, its
   constructor is resolved by [reflection](03-autowiring.md).
7. Otherwise → `NotFoundException`.

After steps 3, 4 and 6 the instance passes through the
[resolution pipeline](05-tags-and-decoration.md#how-the-pipeline-fits-together)
(extenders → inflectors → events). Steps 2 (cache hits) skip the pipeline.

## A first real example

```php
use Temant\Container\Container;
use Temant\Container\ContainerInterface;

$container = new Container();

$container->set(PDO::class, fn() => new PDO('sqlite::memory:'));

$container->set(UserRepository::class, function (ContainerInterface $c) {
    return new UserRepository($c->get(PDO::class));
});

// UserService is not registered — it is autowired, and its
// UserRepository dependency is pulled from the definition above.
$service = $container->get(UserService::class);
```

Next: [Registering services](02-registering-services.md).
