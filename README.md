# Temant Container

A lightweight, [PSR-11](https://www.php-fig.org/psr/psr-11/) compliant dependency injection container for PHP 8.2+ with autowiring support.

## Features

- **PSR-11 compliant** -- implements `Psr\Container\ContainerInterface`
- **Autowiring** -- automatic dependency resolution via reflection (optional, enabled by default)
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
- **Lazy proxies** -- defer heavy service instantiation until first use
- **Child containers** -- scoped resolution with parent fallback
- **Variadic parameter support** -- typed variadics resolved from tagged services
- **Freeze & warm-up** -- lock the container and pre-resolve singletons for production
- **Definition introspection** -- inspect registrations without resolving them
- **Circular dependency detection** -- clear error messages with full dependency chain
- **Zero dependencies** -- only requires `psr/container`

## Requirements

- PHP 8.2 or higher
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

## Usage

### Creating the Container

```php
use Temant\Container\Container;

// Default: autowiring enabled, autowired instances cached
$container = new Container();

// Disable autowiring (explicit registration only)
$container = new Container(autowiringEnabled: false);

// Autowiring enabled but instances not cached (new instance each time)
$container = new Container(autowiringEnabled: true, cacheAutowire: false);
```

### Registering Services

#### Shared (Singleton)

Registered with `set()` or its alias `singleton()`. The factory is called once on first `get()`, and the result is cached for all subsequent calls.

```php
$container->set(DatabaseConnection::class, function (ContainerInterface $c) {
    return new DatabaseConnection(
        host: 'localhost',
        name: 'mydb',
    );
});

// Alias for readability
$container->singleton(Mailer::class, fn(ContainerInterface $c) => new Mailer(
    $c->get(DatabaseConnection::class),
));
```

#### Factory

Registered with `factory()`. A new instance is created on every `get()` call.

```php
$container->factory(RequestId::class, fn() => new RequestId(bin2hex(random_bytes(16))));

$id1 = $container->get(RequestId::class); // unique
$id2 = $container->get(RequestId::class); // different instance
```

#### Pre-built Instance

Registered with `instance()`. The given object is returned as-is on every `get()` call.

```php
$config = new AppConfig(debug: true, timezone: 'UTC');

$container->instance(AppConfig::class, $config);
```

#### Bulk Registration

Register multiple shared services at once with `multi()`.

```php
$container->multi([
    Logger::class    => fn() => new Logger(),
    Mailer::class    => fn() => new Mailer(),
    Cache::class     => fn() => new RedisCache(),
]);
```

> **Note:** Duplicate registration throws a `ContainerException`. Use `remove()` first if you need to replace an entry.

#### Conditional Registration

Register only if the ID is not already bound. Useful in service providers or packages that shouldn't override user configuration.

```php
// Only registers if Logger::class is not already in the container
$container->setIf(Logger::class, fn() => new FileLogger());
$container->singletonIf(Logger::class, fn() => new FileLogger()); // alias for setIf()

// Same for factories and instances
$container->factoryIf(RequestId::class, fn() => new RequestId(uniqid()));
$container->instanceIf(AppConfig::class, new AppConfig());
```

### Interface Binding

Map an interface (or any abstract ID) to a concrete class. Supports chained bindings.

```php
$container->bind(LoggerInterface::class, FileLogger::class);

$container->set(FileLogger::class, fn() => new FileLogger('/var/log/app.log'));

// Resolves FileLogger
$logger = $container->get(LoggerInterface::class);
```

#### Aliases

`alias()` is identical to `bind()` -- use whichever reads better in context.

```php
$container->alias('db', DatabaseConnection::class);

$db = $container->get('db'); // same as get(DatabaseConnection::class)
```

### Contextual Binding

Provide different implementations depending on which class is consuming the dependency. Uses a fluent `when()->needs()->give()` API.

```php
$container->when(UserController::class)
          ->needs(LoggerInterface::class)
          ->give(FileLogger::class);

$container->when(AdminController::class)
          ->needs(LoggerInterface::class)
          ->give(ConsoleLogger::class);

// UserController gets FileLogger, AdminController gets ConsoleLogger
$userCtrl  = $container->get(UserController::class);
$adminCtrl = $container->get(AdminController::class);
```

You can also pass a closure factory:

```php
$container->when(PaymentService::class)
          ->needs(LoggerInterface::class)
          ->give(fn(ContainerInterface $c) => new FileLogger('/var/log/payments.log'));
```

Contextual bindings take precedence over global bindings. If no contextual binding matches, the container falls back to global bindings and autowiring as usual.

### Tagging

Group related services under a tag name and resolve them all at once.

```php
$container->set(ConsoleLogger::class, fn() => new ConsoleLogger());
$container->set(FileLogger::class, fn() => new FileLogger('/var/log/app.log'));

$container->tag(ConsoleLogger::class, 'loggers');
$container->tag(FileLogger::class, 'loggers');

// Returns [ConsoleLogger instance, FileLogger instance]
$loggers = $container->tagged('loggers');
```

Tags also power [variadic resolution](#variadic-parameter-support) -- when a constructor has a typed variadic parameter, the container looks for services tagged with that type name.

### Extending / Decorating Services

Wrap or modify a service after it is resolved. Multiple extenders per service are applied in registration order.

```php
$container->set(Logger::class, fn() => new Logger());

$container->extend(Logger::class, function (object $logger, ContainerInterface $c) {
    $logger->pushHandler(new StreamHandler('/var/log/debug.log'));
    return $logger;
});
```

### Inflectors

Apply common operations to any resolved service that matches a given type. Ideal for setter injection driven by interfaces.

```php
$container->inflect(LoggerAwareInterface::class, function (object $service, ContainerInterface $c) {
    $service->setLogger($c->get(LoggerInterface::class));
});

// Any resolved service implementing LoggerAwareInterface
// will automatically have setLogger() called.
$service = $container->get(MyService::class); // logger injected if MyService implements LoggerAwareInterface
```

Multiple inflectors for the same type are applied in registration order. Unlike `extend()`, inflectors:
- Match by `instanceof` (not by service ID), so one inflector covers all implementations.
- Do not need to return the object -- they modify it in place.

### Container Events

Register callbacks that fire during service resolution. Useful for logging, debugging, or cross-cutting concerns.

```php
// Fire for a specific service ID
$container->resolving(Logger::class, function (object $logger, ContainerInterface $c) {
    // Called when Logger is being resolved (before it's returned)
});

$container->afterResolving(Logger::class, function (object $logger, ContainerInterface $c) {
    // Called after Logger is fully resolved (after extenders + inflectors)
});

// Fire for every resolution (global)
$container->resolving(function (object $service, ContainerInterface $c) {
    // Called for every service resolution
});

$container->afterResolving(function (object $service, ContainerInterface $c) {
    // Called after every service resolution
});
```

Callbacks do not fire for cached singleton hits -- only on actual resolution.

### Service Providers

Organize related service registrations into reusable provider classes.

```php
use Temant\Container\Container;
use Temant\Container\ServiceProviderInterface;

class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(PDO::class, fn() => new PDO(
            'mysql:host=localhost;dbname=myapp', 'root', '',
        ));
    }

    public function boot(Container $container): void
    {
        // Runs after all providers are registered.
        // Safe to resolve other services here.
    }
}

$container->register(new DatabaseServiceProvider());

// Call boot() after all providers are registered
$container->boot();
```

Providers registered after `boot()` has been called are booted immediately.

### Autowiring

When enabled (the default), the container uses reflection to automatically resolve constructor dependencies without manual registration.

```php
class UserRepository
{
    public function __construct(
        private readonly DatabaseConnection $db,
        private readonly Logger $logger,
    ) {}
}

// No registration needed -- both UserRepository and its
// dependencies are resolved automatically.
$repo = $container->get(UserRepository::class);
```

Autowiring can be toggled at runtime:

```php
$container->setAutowiring(false);
$container->hasAutowiring(); // false
```

#### Resolution Rules

For **object types** (class/interface), the resolver tries in order:

1. Contextual binding for the current consumer class
2. Explicitly registered entry in the container
3. Autowire if enabled and the class exists
4. Return `null` if the parameter is nullable
5. Return the default value if one is declared
6. Throw `UnresolvableParameterException`

For **built-in types** (string, int, array, etc.):

1. Return the default value if one is declared
2. Return `null` if nullable
3. Throw `UnresolvableParameterException`

**Not supported** (by design): union types and intersection types throw `UnresolvableParameterException`.

#### Variadic Parameter Support

Typed variadic parameters (e.g., `LoggerInterface ...$loggers`) are resolved automatically:

1. If services are tagged with the parameter's type name, all tagged services are injected.
2. Otherwise, if a single instance of the type is registered, it is injected as a one-element array.
3. If nothing matches, an empty array is used (variadic allows zero arguments).

```php
class LoggerAggregate
{
    public function __construct(LoggerInterface ...$loggers) {}
}

$container->set(FileLogger::class, fn() => new FileLogger());
$container->set(ConsoleLogger::class, fn() => new ConsoleLogger());

// Tag both with the interface name
$container->tag(FileLogger::class, LoggerInterface::class);
$container->tag(ConsoleLogger::class, LoggerInterface::class);

// Both loggers are injected
$aggregate = $container->get(LoggerAggregate::class);
```

### Callable Invocation

Invoke any callable with auto-resolved parameters. Override specific parameters by name.

```php
$result = $container->call(function (Logger $logger, Mailer $mailer) {
    $logger->info('Sending mail...');
    return $mailer->send('hello@example.com', 'Hi!');
});

// With named overrides
$result = $container->call(
    fn(Logger $logger, string $message) => $logger->info($message),
    ['message' => 'Custom message'],
);
```

Works with closures, static methods, invokable objects, and `[$object, 'method']` callables.

#### `Class@method` Syntax

Resolve a class from the container and invoke a method on it in one step:

```php
$result = $container->call('App\Controller\UserController@show', ['id' => 42]);
```

This is equivalent to:

```php
$controller = $container->get('App\Controller\UserController');
$result = $container->call([$controller, 'show'], ['id' => 42]);
```

### Fresh Instances with `make()`

`make()` always creates a new instance, bypassing the singleton cache. Accepts named parameter overrides for constructor arguments.

```php
// Always returns a new Foo, even if Foo is registered as a singleton
$foo = $container->make(Foo::class);

// With parameter overrides
$mailer = $container->make(Mailer::class, [
    'transport' => 'smtp',
    'host'      => 'mail.example.com',
]);
```

`make()` respects bindings, extenders, and inflectors -- it just skips the instance cache.

### Lazy Proxies

Defer instantiation of heavy services until they are actually used. The factory does not run on `get()` -- it runs on the first method call, property access, or explicit `getTarget()`.

```php
use Temant\Container\LazyProxy;

$container->lazy(HeavyService::class, function (ContainerInterface $c) {
    // This runs only when a method is called on the proxy
    return new HeavyService($c->get(Database::class));
});

$service = $container->get(HeavyService::class); // Returns a LazyProxy -- factory NOT called
$service->doWork();                               // NOW the factory runs, then doWork() is called
```

You can inspect proxy state:

```php
$proxy = $container->get(HeavyService::class);

$proxy->isInitialized(); // false -- not yet created
$proxy->getTarget();     // forces creation, returns real instance
$proxy->isInitialized(); // true
```

> **Limitation:** The proxy delegates via `__call()` / `__get()` / `__set()` magic methods. `instanceof` checks against the proxied type will return `false`. For transparent lazy objects, PHP 8.4+ native lazy objects are recommended.

### Child Containers (Scoped)

Create a child container that inherits the parent's registrations. The child can add or override bindings without affecting the parent. Unresolved entries fall back to the parent.

```php
$parent = new Container();
$parent->set(Logger::class, fn() => new FileLogger());

$child = $parent->createChild();
$child->set(Logger::class, fn() => new ConsoleLogger()); // overrides parent

$child->get(Logger::class);  // ConsoleLogger
$parent->get(Logger::class); // FileLogger (unaffected)

// Falls back to parent for entries not in the child
$parent->set(Mailer::class, fn() => new Mailer());
$child->get(Mailer::class); // resolved from parent
```

Navigate the hierarchy:

```php
$child->getParent();            // returns parent Container
$parent->getParent();           // null (root container)
$child->createChild();          // grandchild
```

### Checking & Removing Entries

```php
// Check if an entry exists (considers bindings, parent, and autowiring)
$container->has(Logger::class); // true

// Remove an entry (definitions, bindings, cached instances, extenders)
$container->remove(Logger::class);

// Clear everything (all registrations, state, and frozen flag)
$container->clear();

// Clear only cached instances (keeps definitions)
// Useful in tests or long-running workers (Swoole, RoadRunner)
$container->flushInstances();
```

### Freeze & Warm-Up

#### Freezing

Lock the container to prevent any further modifications. Resolution continues to work normally.

```php
$container->freeze();

$container->isFrozen(); // true

$container->set(Foo::class, fn() => new Foo());
// throws ContainerException: "Cannot modify a frozen container."

// clear() resets the frozen state
$container->clear();
$container->isFrozen(); // false
```

All mutation methods throw when frozen: `set()`, `singleton()`, `factory()`, `instance()`, `bind()`, `alias()`, `tag()`, `extend()`, `inflect()`, `resolving()`, `afterResolving()`, `remove()`, `lazy()`, and contextual bindings.

#### Warm-Up

Pre-resolve all registered singletons to move instantiation overhead to startup time. Useful before `freeze()` in production.

```php
$container->warmUp(); // invokes all shared factories, populates instance cache
$container->freeze(); // lock for production
```

Already-resolved singletons are skipped.

### Introspection

```php
// List all registered service IDs
$container->keys(); // ['App\Logger', 'App\Mailer', ...]

// Structured snapshot of all registrations
$container->all();
// Returns: ['shared' => [...], 'factories' => [...], 'instances' => [...], 'bindings' => [...], 'tags' => [...]]

// Specific registration types
$container->allShared();
$container->allFactories();
$container->allInstances();
$container->allBindings();
```

#### Definition Introspection

Inspect a service's registration details without resolving it:

```php
$container->set(Logger::class, fn() => new FileLogger());
$container->tag(Logger::class, 'loggers');
$container->extend(Logger::class, fn($l) => $l);

$def = $container->getDefinition(Logger::class);
// [
//     'id'           => 'App\Logger',
//     'resolvedId'   => 'App\Logger',
//     'type'         => 'shared',         // 'shared', 'factory', 'instance', or null
//     'binding'      => null,             // target if this ID is a binding/alias
//     'tags'         => ['loggers'],
//     'hasExtenders' => true,
// ]

// Returns null for unregistered IDs
$container->getDefinition('unknown'); // null
```

## Exception Handling

All exceptions implement `Psr\Container\ContainerExceptionInterface` for PSR-11 interoperability.

| Exception | When |
|---|---|
| `NotFoundException` | Entry not found and cannot be autowired. Implements `NotFoundExceptionInterface`. |
| `ContainerException` | Duplicate registration, binding loop, frozen container, non-object return, or general resolution error. |
| `ClassResolutionException` | Class not found, not instantiable, or circular dependency detected. Extends `ContainerException`. |
| `UnresolvableParameterException` | Parameter has no type hint, unsupported type, or cannot be resolved. Extends `ContainerException`. |

```php
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

try {
    $service = $container->get('unknown');
} catch (NotFoundExceptionInterface $e) {
    // Entry not found
} catch (ContainerExceptionInterface $e) {
    // Other container error
}
```

## API Reference

### Registration

| Method | Description |
|---|---|
| `set(id, factory)` | Register a shared (singleton) service |
| `singleton(id, factory)` | Alias for `set()` |
| `factory(id, factory)` | Register a factory (new instance each `get()`) |
| `instance(id, object)` | Register a pre-built instance |
| `multi(definitions)` | Bulk-register shared services |
| `lazy(id, factory)` | Register a lazy-loaded service (deferred instantiation) |
| `setIf(id, factory)` | Register shared only if not already registered |
| `singletonIf(id, factory)` | Alias for `setIf()` |
| `factoryIf(id, factory)` | Register factory only if not already registered |
| `instanceIf(id, object)` | Register instance only if not already registered |

### Binding & Context

| Method | Description |
|---|---|
| `bind(abstract, target)` | Bind an abstract ID to a concrete target |
| `alias(alias, target)` | Alias for `bind()` |
| `when(consumer)` | Begin a contextual binding (returns builder) |

### Tagging & Extension

| Method | Description |
|---|---|
| `tag(id, tag)` | Tag a service with a group name |
| `tagged(tag)` | Resolve all services under a tag |
| `extend(id, closure)` | Decorate a service after resolution |
| `inflect(type, closure)` | Apply a callback to any matching type after resolution |

### Events

| Method | Description |
|---|---|
| `resolving(id\|closure, callback?)` | Register a resolving callback (ID-specific or global) |
| `afterResolving(id\|closure, callback?)` | Register an after-resolving callback (ID-specific or global) |

### Resolution

| Method | Description |
|---|---|
| `get(id)` | Retrieve an entry (PSR-11) |
| `has(id)` | Check if an entry can be resolved (PSR-11) |
| `make(id, parameters?)` | Create a fresh instance (bypasses cache) |
| `call(callable, overrides?)` | Invoke a callable with DI-resolved parameters |

### Service Providers

| Method | Description |
|---|---|
| `register(provider)` | Register a service provider |
| `boot()` | Boot all registered providers |

### Lifecycle

| Method | Description |
|---|---|
| `remove(id)` | Remove an entry from the container |
| `clear()` | Remove everything and reset state |
| `flushInstances()` | Clear cached instances only |
| `freeze()` | Prevent further modifications |
| `isFrozen()` | Check if frozen |
| `warmUp()` | Pre-resolve all singletons |

### Container Hierarchy

| Method | Description |
|---|---|
| `createChild()` | Create a child container with parent fallback |
| `getParent()` | Get the parent container (or null) |

### Autowiring

| Method | Description |
|---|---|
| `setAutowiring(bool)` | Enable/disable autowiring at runtime |
| `hasAutowiring()` | Check if autowiring is enabled |

### Introspection

| Method | Description |
|---|---|
| `keys()` | List all registered service IDs |
| `all()` | Structured snapshot of all registrations |
| `allShared()` | All shared definitions |
| `allFactories()` | All factory definitions |
| `allInstances()` | All cached instances |
| `allBindings()` | All bindings/aliases |
| `getDefinition(id)` | Inspect a service's registration details |

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
