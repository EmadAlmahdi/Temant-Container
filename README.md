# Temant Container

A lightweight, [PSR-11](https://www.php-fig.org/psr/psr-11/) compliant dependency injection container for PHP 8.2+ with autowiring support.

## Features

- **PSR-11 compliant** -- implements `Psr\Container\ContainerInterface`
- **Autowiring** -- automatic dependency resolution via reflection (optional, enabled by default)
- **Shared (singleton) services** -- factory invoked once, result cached
- **Factory services** -- new instance on every retrieval
- **Pre-built instances** -- register existing objects directly
- **Interface binding** -- map abstracts to concretes (`bind()` / `alias()`)
- **Tagging** -- group related services and resolve them together
- **Decoration** -- wrap or modify services after resolution with `extend()`
- **Service providers** -- modular, organized service registration with `register()` / `boot()` lifecycle
- **Callable invocation** -- invoke any callable with auto-resolved parameters via `call()`
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

### Extending / Decorating Services

Wrap or modify a service after it is resolved. Multiple extenders per service are applied in registration order.

```php
$container->set(Logger::class, fn() => new Logger());

$container->extend(Logger::class, function (object $logger, ContainerInterface $c) {
    $logger->pushHandler(new StreamHandler('/var/log/debug.log'));
    return $logger;
});
```

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

1. Explicitly registered entry in the container
2. Autowire if enabled and the class exists
3. Return `null` if the parameter is nullable
4. Return the default value if one is declared
5. Throw `UnresolvableParameterException`

For **built-in types** (string, int, array, etc.):

1. Return the default value if one is declared
2. Return `null` if nullable
3. Throw `UnresolvableParameterException`

**Not supported** (by design): union types, intersection types, variadic parameters.

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

### Checking & Removing Entries

```php
// Check if an entry exists (considers bindings and autowiring)
$container->has(Logger::class); // true

// Remove an entry (definitions, bindings, cached instances, extenders)
$container->remove(Logger::class);

// Clear everything
$container->clear();

// Clear only cached instances (keeps definitions)
// Useful in tests or long-running workers (Swoole, RoadRunner)
$container->flushInstances();
```

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

## Exception Handling

All exceptions implement `Psr\Container\ContainerExceptionInterface` for PSR-11 interoperability.

| Exception | When |
|---|---|
| `NotFoundException` | Entry not found and cannot be autowired. Implements `NotFoundExceptionInterface`. |
| `ContainerException` | Duplicate registration, binding loop, non-object return, or general resolution error. |
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
