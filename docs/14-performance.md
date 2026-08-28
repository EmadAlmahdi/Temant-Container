# Performance & the reflection cache

Autowiring means reflection: for every class it builds, the container inspects the
constructor and each parameter. That work is cached so it happens as few times as
possible.

## Two layers of caching

### Instance cache (always on)

An autowired class resolved through `get()` is built once and the instance is
reused (unless the container was created with `cacheAutowire: false`). This is the
common path and it does reflection **once per class per process**.

### Reflection cache (always on, in memory)

The reflected facts about a constructor — the parameter list, each type, defaults,
`#[Inject]` ids — are stored as a plain data structure and reused. This covers the
paths the instance cache does *not*:

- [`make()`](09-calling-callables.md#fresh-instances-with-make) calls
- autowired dependencies of [`factory()`](02-registering-services.md) services
- [tagged](05-tags-and-decoration.md#tags) services resolved repeatedly

Before this cache, each of those did a fresh `new ReflectionClass(...)` every time.

```
make() of a 30-class chain, repeated on the same container:
  warm reflection cache   0.005 ms
  fresh container / cold   0.245 ms      ~50x
```

### Persistent cache (opt-in, PSR-16)

Pass a [PSR-16](https://www.php-fig.org/psr/simple-cache/) cache to the constructor
and the reflection facts are also written there, so they survive **across
processes** — a traditional PHP-FPM deployment does the reflection once per deploy
instead of once per request.

```php
use Temant\Container\Container;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

$container = new Container(
    reflectionCache: new Psr16Cache(new PhpFilesAdapter('temant-container')),
);
```

Warm it after deployment by resolving your graph, calling
[`warmUp()`](11-lifecycle.md#warm-up), or listing the classes explicitly:

```php
$container->prewarmReflection([
    App\Http\Kernel::class,
    App\Console\Kernel::class,
    // ...
]);
```

The store is keyed by class name only, with no signature hash — **clear it on
every deploy** (an OPcache-backed adapter in a per-deploy directory does this for
free).

## Is the persistent cache worth it?

Honestly: it depends on the graph.

```
resolve a fresh container each request (simulated FPM):
  simple graph, no store           0.32 ms
  simple graph, warmed store        0.32 ms     ~1.0x
  constructors with #[Inject]+union 0.025 ms -> 0.020 ms   ~1.2x
```

On PHP 8.5, reflecting a simple constructor is fast enough that
serialising/deserialising the cached plan roughly breaks even. The persistent
cache earns its place when:

- the graph is large (hundreds of services), and/or
- constructors are heavy on attributes, union types, and many parameters, and/or
- the PSR-16 backend avoids `unserialize` (an OPcache / `var_export` adapter).

**Recommendation:**

| Runtime | Do this |
|---------|---------|
| Worker (Swoole, RoadRunner, FrankenPHP) | Reuse one container. The in-memory caches make everything cheap; a PSR-16 store adds nothing. |
| PHP-FPM, small/medium app | Skip the store. Container overhead is already below your noise floor. |
| PHP-FPM, large app, latency-sensitive | Add an OPcache-backed PSR-16 store and `prewarmReflection()` on deploy. Profile to confirm it helps. |

## What is not cached

`call()` on a **closure** reflects the closure every time — closures have no stable
cache key. `call()` on `[$object, 'method']` or `Class@method` still reflects per
call for the same reason. Keep hot closures small, or resolve the collaborators
once and call plain methods.
