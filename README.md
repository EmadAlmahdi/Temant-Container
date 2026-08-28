# Temant Container

[![CI](https://github.com/EmadAlmahdi/Temant-Container/actions/workflows/ci.yml/badge.svg)](https://github.com/EmadAlmahdi/Temant-Container/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/temant/container/v/stable)](https://packagist.org/packages/temant/container)
[![Total Downloads](https://poser.pugx.org/temant/container/downloads)](https://packagist.org/packages/temant/container)
[![License](https://poser.pugx.org/temant/container/license)](https://packagist.org/packages/temant/container)
[![PHP Version Require](https://poser.pugx.org/temant/container/require/php)](https://packagist.org/packages/temant/container)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](https://phpstan.org/)
[![PSR-11](https://img.shields.io/badge/PSR--11-compliant-blue)](https://www.php-fig.org/psr/psr-11/)

A lightweight, [PSR-11](https://www.php-fig.org/psr/psr-11/) compliant dependency injection container for PHP 8.5+ with autowiring support.

> **Upgrading from 2.x?** See [`docs/upgrading.md`](docs/upgrading.md). The short version: `LazyProxy` moved to `Temant\Container\Proxy\LazyProxy`, lazy services are now type-transparent native objects, and union-typed parameters are autowired instead of rejected.

## Features

- **PSR-11 compliant** -- implements `Psr\Container\ContainerInterface`
- **Autowiring** -- automatic dependency resolution via reflection (optional, enabled by default), including **union types** and the **`#[Inject]`** attribute
- **Shared (singleton) services** -- factory invoked once, result cached
- **Factory services** -- new instance on every retrieval
- **Pre-built instances** -- register existing objects directly
- **Interface binding** -- map abstracts to concretes (`bind()` / `alias()`)
- **Contextual binding** -- consumer-specific resolution via `when()->needs()->give()`
- **Tagging** -- group related services and resolve them together
- **Decoration** -- wrap or modify services after resolution with `extend()`
- **Inflectors** -- type-based post-resolution hooks (setter injection)
- **Container events** -- `resolving()` and `afterResolving()` lifecycle hooks
- **Service providers** -- modular, organized service registration with `register()` / `boot()` lifecycle
- **Callable invocation** -- invoke any callable with auto-resolved parameters via `call()`
- **`Class@method` syntax** -- resolve and invoke in one step
- **Fresh instances** -- `make()` bypasses cache with optional parameter overrides
- **Conditional registration** -- `setIf()`, `factoryIf()`, `instanceIf()` skip duplicates silently
- **Lazy services** -- defer heavy service instantiation until first use, using PHP's type-transparent native lazy objects
- **Child containers** -- scoped resolution with parent fallback
- **Variadic parameter support** -- typed variadics resolved from tagged services
- **Freeze & warm-up** -- lock the container and pre-resolve singletons for production
- **Definition introspection** -- inspect registrations without resolving them
- **Circular dependency detection** -- clear error messages with full dependency chain
- **Reflection cache** -- autowiring reflection is cached in memory, and optionally persisted across processes via any [PSR-16](https://www.php-fig.org/psr/simple-cache/) cache
- **Minimal dependencies** -- only `psr/container` and `psr/simple-cache` (interfaces only)

## Requirements

- PHP 8.5 or higher
- [Composer](https://getcomposer.org/)

## Installation

```bash
composer require temant/container
```

## Quick Start

```php
use Temant\Container\Container;

$container = new Container();

// Register a shared (singleton) service
$container->set(Logger::class, fn() => new Logger('/var/log/app.log'));

// Retrieve it -- same instance every time
$logger = $container->get(Logger::class);
```

## Documentation

The full guide lives in [`docs/`](docs/README.md).

| | |
|--|--|
| [Getting started](docs/01-getting-started.md) | Install, create a container, how an entry is produced |
| [Registering services](docs/02-registering-services.md) | `set` / `singleton` / `factory` / `instance` / `multi`, conditional registration |
| [Autowiring](docs/03-autowiring.md) | Reflection resolution, union types, `#[Inject]`, variadics |
| [Binding & contextual bindings](docs/04-binding-and-context.md) | `bind` / `alias`, `when()->needs()->give()`, `giveTagged()` |
| [Tags & decoration](docs/05-tags-and-decoration.md) | `tag` / `tagged`, `extend`, `inflect` |
| [Events](docs/06-events.md) | `resolving()` / `afterResolving()` |
| [Lazy services](docs/07-lazy-services.md) | Native lazy objects, `LazyProxy` fallback, `initialized()` |
| [Service providers](docs/08-service-providers.md) | `register()` / `boot()` lifecycle |
| [Calling callables & `make()`](docs/09-calling-callables.md) | `call()`, `Class@method`, fresh instances |
| [Child containers](docs/10-child-containers.md) | Scoped resolution with parent fallback |
| [Lifecycle](docs/11-lifecycle.md) | `remove` / `clear` / `flushInstances` / `freeze` / `warmUp` |
| [Introspection](docs/12-introspection.md) | `keys` / `all` / `getDefinition` / `initialized` |
| [Exceptions](docs/13-exceptions.md) | The exception hierarchy and when each is thrown |
| [Performance](docs/14-performance.md) | The reflection cache and the optional PSR-16 persistent cache |
| [API reference](docs/api-reference.md) | Every public method in one table |
| [Architecture](docs/architecture.md) | How the container is put together internally |
| [Upgrading from 2.x](docs/upgrading.md) | Breaking changes and migration steps |

## Testing

```bash
# Run tests
composer phpunit

# Run static analysis
composer phpstan

# Run both
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
