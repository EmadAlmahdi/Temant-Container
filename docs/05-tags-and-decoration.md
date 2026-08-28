# Tags & decoration

Three features hook into a service **after** it is created: tags group services,
`extend()` wraps one service, `inflect()` mutates every service of a type.

## Tags

Group related ids under a tag name, then resolve them together.

```php
$container->set(SlackChannel::class, fn() => new SlackChannel());
$container->set(EmailChannel::class, fn() => new EmailChannel());

$container->tag(SlackChannel::class, 'notifications');
$container->tag(EmailChannel::class, 'notifications');

/** @var list<object> $channels */
$channels = $container->tagged('notifications'); // [SlackChannel, EmailChannel]
```

- Order is insertion order.
- Tagging the same id under the same tag twice is a no-op.
- `tagged()` on an unknown tag returns `[]`.
- Each id is resolved through the normal `get()` path when `tagged()` is called.

Tags also feed [variadic autowiring](03-autowiring.md#variadic-parameters) and
[`giveTagged()`](04-binding-and-context.md#a-list-or-a-tag-for-variadics).

```php
final class NotificationHub
{
    public function __construct(private ChannelInterface ...$channels) {}
}

$container->tag(SlackChannel::class, ChannelInterface::class);
$container->tag(EmailChannel::class, ChannelInterface::class);

$hub = $container->get(NotificationHub::class); // both channels injected
```

## Extend (decoration)

Wrap or replace a single service after it is resolved. The closure receives the
resolved object and the container, and returns the object to use.

```php
$container->set(LoggerInterface::class, fn() => new FileLogger());

$container->extend(LoggerInterface::class, function (object $logger, $c) {
    return new BufferingLogger($logger); // swap in a decorator
});
```

- **Multiple extenders** run in registration order, each receiving the previous
  one's output.
- Extenders run **every time** the service is resolved — including each call for
  a [`factory()`](02-registering-services.md#factory) service.
- You may call `extend()` **before** the service is registered; the extender is
  applied on the next resolution.

```php
$container->set('counter', fn() => (object) ['n' => 1]);
$container->extend('counter', function ($o) { $o->n += 4; return $o; });
$container->extend('counter', function ($o) { $o->n *= 3; return $o; });

$container->get('counter')->n; // 15
```

## Inflect (type-matched mutation)

Run a callback against **every resolved object that is an instance of a given
type**. Ideal for interface-driven setter injection.

```php
interface LoggerAware
{
    public function setLogger(LoggerInterface $logger): void;
}

$container->inflect(LoggerAware::class, function (object $service, $c) {
    $service->setLogger($c->get(LoggerInterface::class));
});

// Any resolved service implementing LoggerAware now gets its logger injected.
```

`inflect()` differs from `extend()`:

| | `extend()` | `inflect()` |
|--|-----------|-------------|
| Matches by | service **id** | `instanceof` **type** |
| Covers | one registration | every implementation, autowired or not |
| Return value | must return the object | ignored — mutate in place |

Multiple inflectors for the same type run in registration order.

## How the pipeline fits together

After a service is created (by a shared factory, a factory, or autowiring) it
passes through one pipeline, in this order:

```
extenders (by id)  ->  inflectors (by type)  ->  resolving events  ->  afterResolving events
```

Cache hits — a resolved singleton or a pre-registered
[`instance()`](02-registering-services.md#pre-built-instance) — **skip** the
pipeline entirely.

See [Events](06-events.md) for the last two stages.
