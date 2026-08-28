# Autowiring

When autowiring is enabled (the default), the container builds unregistered
classes by inspecting their constructor with reflection and resolving each
parameter.

```php
final class UserRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly LoggerInterface $logger,
    ) {}
}

// Nothing registered — UserRepository and both dependencies are resolved.
$repo = $container->get(UserRepository::class);
```

Autowired instances are cached as singletons unless the container was created
with `cacheAutowire: false`.

## Toggling autowiring

```php
$container->setAutowiring(false);
$container->hasAutowiring(); // false
```

With autowiring off, `get()` on an unregistered class throws
[`NotFoundException`](13-exceptions.md#notfoundexception), and `has()` on it
returns `false`.

## Resolution rules

For each constructor parameter, in order:

### 0. `#[Inject]` attribute

If the parameter carries [`#[Inject(id)]`](#the-inject-attribute), the container
resolves that id directly and skips every rule below.

### Object parameters (a class or interface type)

1. A [contextual binding](04-binding-and-context.md#contextual-bindings) for the
   class currently being built.
2. An explicitly registered entry for that type.
3. Autowire it (if enabled and the class is instantiable).
4. `null` — if the parameter is nullable.
5. The declared default value.
6. Otherwise → [`UnresolvableParameterException`](13-exceptions.md#unresolvableparameterexception).

### Union types (`Foo|Bar`)

Each object member is tried in turn:

1. A member that is contextually or explicitly bound wins first.
2. Then the first member the container can autowire.
3. Then `null` (if nullable), then the default value.
4. Otherwise → `UnresolvableParameterException`.

```php
final class Report
{
    // Resolves to whichever of the two is registered; if both are, PdfWriter wins.
    public function __construct(private PdfWriter|HtmlWriter $writer) {}
}
```

### Built-in types (`string`, `int`, `array`, …)

1. The declared default value.
2. `null` — if nullable.
3. Otherwise → `UnresolvableParameterException`.

The container never invents scalar values. Give them a default, make them
nullable, or supply them explicitly via [`when()->needs()->give()`](04-binding-and-context.md#contextual-bindings),
[`#[Inject]`](#the-inject-attribute), or [`make()`](09-calling-callables.md#fresh-instances-with-make).

### Not supported

Intersection types (`A&B`) throw `UnresolvableParameterException`. Bind the
consumer contextually instead.

## The `#[Inject]` attribute

`#[Temant\Container\Attribute\Inject(id)]` pins a parameter to a specific
container id — useful when several implementations of an interface are
registered, or to name a service that isn't keyed by its class.

```php
use Temant\Container\Attribute\Inject;

final class AuditLog
{
    public function __construct(
        #[Inject(FileLogger::class)] private LoggerInterface $logger,
    ) {}
}
```

On a [variadic](#variadic-parameters) parameter, the id is treated as a tag name
first, then as a single service id.

## Variadic parameters

A typed variadic (`LoggerInterface ...$loggers`) is filled automatically:

1. A [contextual binding](04-binding-and-context.md#a-list-or-a-tag-for-variadics)
   for the type — a list of ids or a whole tag.
2. Every service [tagged](05-tags-and-decoration.md#tags) with the type name.
3. A single registered instance of the type, as a one-element array.
4. An empty array — a variadic legitimately accepts zero arguments.

```php
final class LoggerChain
{
    /** @var list<LoggerInterface> */
    public array $loggers;

    public function __construct(LoggerInterface ...$loggers)
    {
        $this->loggers = $loggers;
    }
}

$container->set(FileLogger::class, fn() => new FileLogger());
$container->set(SyslogLogger::class, fn() => new SyslogLogger());
$container->tag(FileLogger::class, LoggerInterface::class);
$container->tag(SyslogLogger::class, LoggerInterface::class);

$chain = $container->get(LoggerChain::class); // both loggers injected
```

## Circular dependencies

If resolving a class eventually requires that same class, the container throws
[`ClassResolutionException`](13-exceptions.md#classresolutionexception) with the
full chain:

```
Circular dependency detected while resolving App\A: App\A -> App\B -> App\A
```
