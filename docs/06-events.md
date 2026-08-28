# Events

`resolving()` and `afterResolving()` register callbacks that fire while a service
is being produced. Use them for logging, metrics, debugging, or cross-cutting
setup — not for building the service itself (that is what factories and
[`extend()`](05-tags-and-decoration.md#extend-decoration) are for).

## Per-id callbacks

Pass an id first, then the callback. It fires only for that id.

```php
$container->resolving(HttpClient::class, function (object $client, $c) {
    // runs just before HttpClient is handed back
});

$container->afterResolving(HttpClient::class, function (object $client, $c) {
    // runs after extenders and inflectors, last of all
});
```

## Global callbacks

Pass a `Closure` directly (no id) and it fires for **every** resolution.

```php
$container->resolving(function (object $service, $c) {
    error_log('resolving ' . $service::class);
});
```

## Firing order

Within a single resolution:

```
extenders  ->  inflectors  ->  resolving (id)  ->  resolving (global)
           ->  afterResolving (id)  ->  afterResolving (global)
```

## When they do *not* fire

Callbacks fire only on an **actual resolution**:

- A cached [singleton](02-registering-services.md#shared-singleton) hit — the
  callbacks ran on the first `get()`, not on subsequent ones.
- A pre-registered [`instance()`](02-registering-services.md#pre-built-instance).

They **do** fire on every call for a [`factory()`](02-registering-services.md#factory)
service and on every [`make()`](09-calling-callables.md#fresh-instances-with-make).

## Example: timing every service

```php
$timings = [];

$container->resolving(function (object $s) use (&$timings) {
    $timings[$s::class] = -microtime(true);
});

$container->afterResolving(function (object $s) use (&$timings) {
    $timings[$s::class] += microtime(true);
});
```
