# Introspection

Inspect what the container holds without resolving anything.

## `keys()`

Every registered id across shared, factory and instance registrations:

```php
$container->keys(); // ['App\Logger', 'App\Mailer', ...]
```

## `all()`

A structured snapshot:

```php
$container->all();
// [
//     'shared'    => array<string, callable>,
//     'factories' => array<string, callable>,
//     'instances' => array<string, object>,
//     'bindings'  => array<string, string>,
//     'tags'      => array<string, list<string>>,
// ]
```

Narrower accessors: `allShared()`, `allFactories()`, `allInstances()`,
`allBindings()`.

## `getDefinition()`

Registration details for a single id, or `null` if it has neither a definition
nor a binding:

```php
$container->set(Logger::class, fn() => new FileLogger());
$container->tag(Logger::class, 'logging');
$container->extend(Logger::class, fn($l) => $l);
$container->bind('log', Logger::class);

$container->getDefinition('log');
// [
//     'id'           => 'log',
//     'resolvedId'   => 'App\Logger',
//     'type'         => 'shared',        // 'shared' | 'factory' | 'instance' | 'lazy' | null
//     'binding'      => 'App\Logger',    // the direct target if this id is a binding, else null
//     'tags'         => ['logging'],
//     'hasExtenders' => true,
// ]

$container->getDefinition('unknown'); // null
```

## `initialized()`

Whether a [lazy](07-lazy-services.md) service's factory has run yet. Returns
`false` for any id that is not registered as lazy.

```php
$container->lazy(SearchIndex::class, fn($c) => new SearchIndex(/* ... */));

$container->initialized(SearchIndex::class); // false
$container->get(SearchIndex::class)->query('x');
$container->initialized(SearchIndex::class); // true
```

## `has()` and `hasAutowiring()`

- `has($id)` — `true` when `get($id)` would not throw
  [`NotFoundException`](13-exceptions.md#notfoundexception): the id has a
  definition or cached instance, resolves via the parent, or is an instantiable
  autowirable class. It is `false` for an abstract class or interface that has no
  binding.
- `hasAutowiring()` — whether reflection autowiring is currently on.
