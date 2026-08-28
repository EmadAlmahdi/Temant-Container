# Binding & contextual bindings

## Bindings

`bind()` points an abstract id (usually an interface) at a concrete target id.
When the abstract is requested, the container resolves the target instead.

```php
$container->bind(LoggerInterface::class, FileLogger::class);
$container->set(FileLogger::class, fn() => new FileLogger('/var/log/app.log'));

$container->get(LoggerInterface::class); // a FileLogger
```

If the target has no registration of its own, it is [autowired](03-autowiring.md).

Bindings chain, and loops are rejected:

```php
$container->bind('a', 'b');
$container->bind('b', FileLogger::class);
$container->get('a'); // FileLogger

$container->bind('x', 'y');
$container->bind('y', 'x');
$container->get('x'); // ContainerException: Circular binding loop detected at 'x'.
```

`has()`, `get()`, `make()` and `getDefinition()` all resolve the binding chain
before doing anything else.

## Aliases

`alias()` is identical to `bind()` — pick whichever name reads better.

```php
$container->alias('db', PDO::class);
$container->get('db'); // same as get(PDO::class)
```

## Contextual bindings

Provide a different implementation depending on **which class is asking**. The
API is fluent: `when(consumer)->needs(abstract)->give(concrete)`.

```php
$container->when(WeatherController::class)
          ->needs(CacheInterface::class)
          ->give(RedisCache::class);

$container->when(HealthCheck::class)
          ->needs(CacheInterface::class)
          ->give(ArrayCache::class);
```

`give()` accepts:

| Argument | Meaning |
|----------|---------|
| a class-string | resolve that id for this consumer |
| a `Closure` | call it (receives the container) and use the result |
| a `list<string>` | resolve each id — for a [variadic](#a-list-or-a-tag-for-variadics) parameter |

```php
$container->when(PaymentGateway::class)
          ->needs(LoggerInterface::class)
          ->give(fn($c) => new FileLogger('/var/log/payments.log'));
```

### Precedence

A contextual binding beats a global [binding](#bindings) and beats autowiring.
If no contextual binding matches the consumer, resolution falls back to the
normal rules.

```php
$container->bind(LoggerInterface::class, SyslogLogger::class);          // global default
$container->when(DebugController::class)
          ->needs(LoggerInterface::class)
          ->give(FileLogger::class);                                    // just for DebugController

$container->get(DebugController::class);  // gets FileLogger
$container->get(HomeController::class);   // gets SyslogLogger (the global binding)
```

### A list or a tag for variadics

For a typed [variadic](03-autowiring.md#variadic-parameters) dependency, hand the
consumer a list of ids or a whole [tag](05-tags-and-decoration.md#tags):

```php
final class Dashboard
{
    /** @var list<WidgetInterface> */
    public array $widgets;
    public function __construct(WidgetInterface ...$widgets) { $this->widgets = $widgets; }
}

$container->when(Dashboard::class)
          ->needs(WidgetInterface::class)
          ->give([ChartWidget::class, TableWidget::class]);

// or, pulling from a tag:
$container->tag(ChartWidget::class, 'widgets');
$container->tag(TableWidget::class, 'widgets');

$container->when(Dashboard::class)
          ->needs(WidgetInterface::class)
          ->giveTagged('widgets');
```

## When to use which

| Goal | Tool |
|------|------|
| One implementation for an interface, app-wide | `bind()` |
| A short second name for an existing id | `alias()` |
| Different implementation per consuming class | `when()->needs()->give()` |
| Fill a variadic from several services | `give([...])` or `giveTagged()` |
