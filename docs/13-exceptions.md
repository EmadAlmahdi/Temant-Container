# Exceptions

Every exception lives under `Temant\Container\Exception\` and implements
`Psr\Container\ContainerExceptionInterface`, so PSR-11 consumers can catch them
generically.

## Hierarchy

```
Psr\Container\ContainerExceptionInterface
└── ContainerException
    ├── FrozenContainerException
    ├── ClassResolutionException
    ├── UnresolvableParameterException
    └── NotFoundException          (also implements Psr\Container\NotFoundExceptionInterface)
```

Because everything extends `ContainerException`, `catch (ContainerException $e)`
catches them all.

## `NotFoundException`

The requested id has no definition, no binding, cannot be resolved by a parent
container, and is not an autowirable class.

```php
No entry found in the container for identifier: App\Unknown
```

Also implements `Psr\Container\NotFoundExceptionInterface`.

## `ContainerException`

The base type, thrown directly for:

- **Duplicate registration** — `Entry 'X' is already registered in the container.`
- **Binding loop** — `Circular binding loop detected at 'X'.`
- **Non-object factory return** — `Shared entry 'X' must return an object, got string.`
- **Removing a missing entry** — `Cannot remove 'X': no entry found in the container.`
- **Wrapping** any unexpected `Exception` thrown inside a factory:
  `Error resolving entry 'X'.` (the original is available via `getPrevious()`).

## `FrozenContainerException`

A mutating method was called on a [frozen](11-lifecycle.md#freeze) container.

```php
Cannot set(): the container is frozen and no longer accepts modifications.
```

Extends `ContainerException`, so existing `catch (ContainerException)` blocks
keep working; catch this type to distinguish a freeze violation.

## `ClassResolutionException`

Autowiring could not build a class. Extends `ContainerException`.

| Message | Cause |
|---------|-------|
| `Class X is not a valid resolvable class.` | the class does not exist |
| `Class X is not instantiable.` | abstract class, interface, private constructor |
| `Circular dependency detected while resolving X: A -> B -> A` | a dependency cycle |

## `UnresolvableParameterException`

A constructor or callable parameter could not be filled. Extends
`ContainerException`.

| Message | Cause |
|---------|-------|
| `Parameter $x has no type hint and cannot be resolved.` | untyped parameter, no default |
| `Cannot resolve parameter $x of type int.` | built-in type, no default, not nullable |
| `Intersection types are not supported for parameter $x.` | `A&B` parameter |
| `Union types are not supported for parameter $x.` | no member of a `Foo\|Bar` could be resolved |
| `Cannot resolve parameter $x of type X: not registered in container and autowiring is disabled.` | object type, autowiring off |

## Catching

```php
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

try {
    $service = $container->get(Thing::class);
} catch (NotFoundExceptionInterface $e) {
    // unknown id
} catch (ContainerExceptionInterface $e) {
    // misconfiguration, unresolvable dependency, frozen container, ...
}
```
