<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use Closure;
use ReflectionFunction;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Exception\UnresolvableParameterException;

use function array_key_exists;

/**
 * Orchestrates class instantiation and callable invocation with dependency injection.
 *
 * Delegates to {@see ConstructorResolver} for class instantiation and
 * {@see ParameterResolver} for individual parameter resolution. Maintains
 * a resolving stack to detect circular dependencies.
 *
 * @internal This is an internal component of the container — not part of the public API.
 */
final class Resolver
{
    private readonly ConstructorResolver $constructorResolver;
    private readonly ParameterResolver $parameterResolver;

    /**
     * Current resolving stack to detect circular dependencies.
     *
     * @var list<class-string>
     */
    private array $resolvingStack = [];

    /**
     * @param Container $container The container used for resolving dependencies.
     */
    public function __construct(private readonly Container $container)
    {
        $this->parameterResolver = new ParameterResolver(
            $this->container,
            $this->resolvingStack,
        );

        $this->constructorResolver = new ConstructorResolver(
            $this->parameterResolver,
            $this->resolvingStack,
        );
    }

    /**
     * Resolves and instantiates a class by its fully qualified name.
     *
     * @param class-string $id The class name to resolve.
     * @param array<string, mixed> $overrides Named parameter overrides for the constructor.
     * @return object The resolved instance.
     *
     * @throws ClassResolutionException If the class cannot be resolved.
     */
    public function resolve(string $id, array $overrides = []): object
    {
        return $this->constructorResolver->resolve($id, $overrides);
    }

    /**
     * Invokes a callable while resolving its type-hinted parameters from the container.
     *
     * Parameters can be overridden by name via the $namedOverrides array.
     *
     * @param callable $callable The callable to invoke.
     * @param array<string, mixed> $namedOverrides Override values keyed by parameter name.
     * @return mixed The return value of the callable.
     *
     * @throws UnresolvableParameterException If a parameter cannot be resolved.
     */
    public function call(callable $callable, array $namedOverrides = []): mixed
    {
        $ref = new ReflectionFunction(Closure::fromCallable($callable));

        $args = [];

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $namedOverrides)) {
                $args[] = $namedOverrides[$name];
                continue;
            }

            $args[] = $this->parameterResolver->resolveParameter($param);
        }

        return $callable(...$args);
    }
}
