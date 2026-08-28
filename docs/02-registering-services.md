# Registering services

Every registration associates an **id** (usually a class-string) with a way to
produce an object. Registration methods are chainable and return `$this`.

> Registering the same id twice throws a
> [`ContainerException`](13-exceptions.md#containerexception). Call
> [`remove()`](11-lifecycle.md#remove) first to replace an entry, or use the
> [conditional variants](#conditional-registration).

## Shared (singleton)

`set()` — and its readability alias `singleton()` — register a factory that runs
**once**. The result is cached and returned for every later `get()`.

```php
$container->set(Clock::class, fn() => new SystemClock());

$a = $container->get(Clock::class);
$b = $container->get(Clock::class);
$a === $b; // true
```

The factory receives the container, so it can resolve other services:

```php
use Temant\Container\ContainerInterface;

$container->singleton(Mailer::class, fn(ContainerInterface $c) => new Mailer(
    $c->get(Transport::class),
    $c->get(LoggerInterface::class),
));
```

A factory **must return an object**. Returning a scalar or array throws a
`ContainerException` when the entry is resolved.

## Factory

`factory()` registers a factory that runs on **every** `get()`. Nothing is
cached.

```php
$container->factory(RequestId::class, fn() => new RequestId(bin2hex(random_bytes(16))));

$container->get(RequestId::class) === $container->get(RequestId::class); // false
```

Use this for values that must be unique per use — request IDs, form tokens,
throwaway builders.

## Pre-built instance

`instance()` stores an object you already have. The same object comes back every
time, untouched.

```php
$config = new AppConfig(debug: true, timezone: 'UTC');

$container->instance(AppConfig::class, $config);

$container->get(AppConfig::class) === $config; // true
```

## Bulk registration

`multi()` registers several shared services at once:

```php
$container->multi([
    Cache::class  => fn() => new RedisCache(),
    Mailer::class => fn() => new Mailer(),
    Clock::class  => fn() => new SystemClock(),
]);
```

Each entry follows the same duplicate rule as `set()`.

## Conditional registration

The `*If` variants register **only if the id is not already bound**. They are the
right tool inside [service providers](08-service-providers.md) and reusable
packages that must not override the host application's choices.

```php
$container->setIf(LoggerInterface::class, fn() => new NullLogger());
$container->singletonIf(LoggerInterface::class, fn() => new NullLogger()); // alias for setIf
$container->factoryIf(RequestId::class, fn() => new RequestId(uniqid()));
$container->instanceIf(AppConfig::class, new AppConfig());
```

If the id is already registered, the call is a silent no-op — no exception.

## Choosing a registration type

| Situation | Use |
|-----------|-----|
| Stateless service, safe to share | `set()` / `singleton()` |
| Carries per-use state, or must be unique each time | `factory()` |
| Object created outside the container (config, PSR-7 request, an SDK client) | `instance()` |
| A package default that the app may override | `setIf()` / `factoryIf()` / `instanceIf()` |
| Expensive to build and often unused | [`lazy()`](07-lazy-services.md) |

If a class has no configuration and only needs its constructor dependencies,
**don't register it at all** — [autowiring](03-autowiring.md) handles it.
