# Child containers

`createChild()` returns a container that inherits from its parent: anything the
child cannot resolve locally is delegated upward. The child can add or override
bindings without touching the parent.

```php
$root = new Container();
$root->set(LoggerInterface::class, fn() => new FileLogger());
$root->set(Config::class, fn() => new Config());

$scope = $root->createChild();
$scope->set(LoggerInterface::class, fn() => new BufferingLogger()); // override

$scope->get(LoggerInterface::class); // BufferingLogger  (local)
$scope->get(Config::class);          // Config           (from the parent)
$root->get(LoggerInterface::class);  // FileLogger        (parent unaffected)
```

## Behaviour

- The child starts empty. It inherits the parent's `autowiringEnabled` and
  `cacheAutowire` flags, nothing else.
- `get()`, `has()` and `make()` fall back to the parent when the child has no
  local match.
- A child's own registrations, cached singletons, tags, events and extenders are
  entirely separate from the parent's.
- Nesting is unlimited — `createChild()` on a child gives a grandchild.

## Navigating the hierarchy

```php
$scope->getParent();  // the parent Container
$root->getParent();   // null — this is a root container
```

## When to use them

- **Per-request scope** in a long-running worker (Swoole, RoadRunner, ReactPHP):
  build a child per request, register request-scoped services on it, discard it
  when the request ends. The root keeps application-wide singletons warm.
- **Test isolation**: give each test a child of a shared, pre-configured root.

For a single-process, one-request-per-run app you usually don't need child
containers — [`flushInstances()`](11-lifecycle.md#flushinstances) covers most
reset needs.
