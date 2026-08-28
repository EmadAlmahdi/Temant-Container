# Service providers

A service provider groups related registrations into a class, keeping container
configuration modular. Implement `Temant\Container\ServiceProviderInterface`:

```php
use Temant\Container\Container;
use Temant\Container\ServiceProviderInterface;

final class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(PDO::class, fn() => new PDO(
            $_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASS'],
        ));

        $container->bind(ConnectionInterface::class, PdoConnection::class);
    }

    public function boot(Container $container): void
    {
        // Runs after every provider has registered.
        // Safe to resolve services here.
    }
}
```

## Lifecycle

```php
$container->register(new DatabaseServiceProvider());
$container->register(new CacheServiceProvider());

$container->boot(); // call once, after all providers are registered
```

1. `register()` is called **immediately** when the provider is added. Only define
   things here — do not resolve services, because other providers may not have
   registered yet.
2. `boot()` is called for every provider when you call `$container->boot()`. By
   now every registration exists, so resolving is safe.

Rules:

- `boot()` is **idempotent** — calling `$container->boot()` more than once does
  nothing after the first.
- A provider `register()`-ed **after** `boot()` has run is booted immediately.
- [`clear()`](11-lifecycle.md#clear) forgets all providers and resets the booted
  flag.

## Keeping providers overridable

Use the [conditional](02-registering-services.md#conditional-registration)
methods so an application can override a package's defaults:

```php
public function register(Container $container): void
{
    $container->setIf(LoggerInterface::class, fn() => new NullLogger());
}
```
