# Lazy services

`lazy()` registers a shared service whose factory does **not** run on `get()`. It
runs on the first real interaction with the returned object — a method call or a
property access.

Use it for services that are expensive to build (open a connection, parse a large
file, warm a cache) and are only needed on some code paths.

```php
$container->lazy(SearchIndex::class, function ($c) {
    return new SearchIndex($c->get(Elasticsearch::class)); // heavy
});

$index = $container->get(SearchIndex::class); // factory NOT called
// ... a request that never searches ...
$index->query('term');                        // NOW the factory runs, then query()
```

The same proxy object is returned on every `get()`.

## Native lazy objects

When the id resolves to a concrete, instantiable class, `lazy()` returns a
**native PHP lazy object** — a genuine instance of that class whose
initialisation is deferred. `instanceof` checks and type hints work normally:

```php
$index = $container->get(SearchIndex::class);
$index instanceof SearchIndex; // true
```

Check whether the factory has run:

```php
$container->initialized(SearchIndex::class); // false, then true after first use
```

## The `LazyProxy` fallback

If a native lazy object can't be used, the container falls back to
`Temant\Container\Proxy\LazyProxy`, a magic-method wrapper. This happens when:

- the id resolves to an **interface** or a non-class id, or
- the entry has [`extend()`](05-tags-and-decoration.md#extend-decoration)
  decorators, which may legitimately return a different type than the factory
  produced.

```php
$proxy = $container->get(SomeInterface::class);   // a LazyProxy
$proxy instanceof SomeInterface;                  // false — it delegates via __call
$proxy->isInitialized();                          // false
$proxy->getTarget();                              // forces creation, returns the real object
```

`Container::initialized()` works for both kinds.

## Caveats

- **Static-only methods** — a native lazy object initialises on the first access
  to *instance* state. A method that only returns static or constant data can run
  without triggering initialisation. This matters only if your constructor has
  side effects.
- **`flushInstances()`** rebuilds the proxy, so a flushed lazy service is
  un-initialised again and still defers correctly.
- For a transparent lazy object you can also use PHP's
  `ReflectionClass::newLazyGhost()` directly and register the result with
  [`instance()`](02-registering-services.md#pre-built-instance).
