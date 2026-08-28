# Calling callables & fresh instances

## `call()`

Invoke any callable and let the container resolve its type-hinted parameters.

```php
$container->call(function (Mailer $mailer, LoggerInterface $log) {
    $log->info('sending');
    return $mailer->send(/* ... */);
});
```

Works with closures, `[$object, 'method']` pairs, invokable objects, and
first-class callable syntax.

### Named overrides

The second argument overrides parameters **by name**. Overridden parameters are
not resolved from the container.

```php
$container->call(
    fn(LoggerInterface $log, string $message) => $log->warning($message),
    ['message' => 'disk almost full'],
);
```

### `Class@method` syntax

A string of the form `Class@method` resolves the class from the container and
invokes the method:

```php
$container->call(ReportController::class . '@monthly', ['month' => 3]);
```

is equivalent to:

```php
$controller = $container->get(ReportController::class);
$container->call([$controller, 'monthly'], ['month' => 3]);
```

### Variadic parameters

A typed variadic in the callable is filled the same way as in a
[constructor](03-autowiring.md#variadic-parameters) — from a tag, a single
registered instance, or empty.

```php
$container->call(fn(HandlerInterface ...$handlers) => count($handlers));
```

## Fresh instances with `make()`

`make()` returns a **new** object, bypassing the singleton cache.

```php
$container->set(Report::class, fn() => new Report());

$a = $container->make(Report::class);
$b = $container->make(Report::class);
$a === $b;                          // false
$container->get(Report::class);     // still the cached singleton, untouched
```

### Constructor overrides

For an **autowired** class, the second argument overrides constructor arguments
by name:

```php
$mailer = $container->make(Mailer::class, [
    'host' => 'mail.example.com',
    'port' => 2525,
]);
```

> Overrides apply only on the autowiring path. If the id is registered with a
> **closure** ([`set()`](02-registering-services.md#shared-singleton) /
> [`factory()`](02-registering-services.md#factory)), `make()` re-invokes that
> closure and the `$parameters` array is ignored — closures take the container,
> not named arguments.

### What `make()` still honours

- [Bindings and aliases](04-binding-and-context.md) — the id is resolved first.
- The [resolution pipeline](05-tags-and-decoration.md#how-the-pipeline-fits-together)
  — extenders, inflectors and events all run.
- The [parent container](10-child-containers.md) — a child delegates `make()`
  upward if it has no local definition.

Only the instance **cache** is skipped.
