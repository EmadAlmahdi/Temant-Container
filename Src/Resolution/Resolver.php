<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use Closure;
use ReflectionFunction;
use Temant\Container\Contract\ResolverContainer;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Exception\UnresolvableParameterException;

use function array_key_exists;

/**
 * Entry point for the resolution layer: instantiates classes and invokes callables
 * with their dependencies injected.
 *
 * Delegates class instantiation to {@see ConstructorResolver} and per-parameter
 * resolution to {@see ParameterResolver}, sharing a single {@see ResolvingStack}
 * between them for circular-dependency detection and contextual-binding context.
 *
 * @internal
 */
final class Resolver
{
    private readonly ResolvingStack $stack;
    private readonly ParameterResolver $parameterResolver;
    private readonly ConstructorResolver $constructorResolver;

    public function __construct(ResolverContainer $container)
    {
        $this->stack = new ResolvingStack();
        $this->parameterResolver = new ParameterResolver($container, $this->stack);
        $this->constructorResolver = new ConstructorResolver($this->parameterResolver, $this->stack);
    }

    /**
     * Instantiates a class, autowiring its constructor.
     *
     * @param class-string $id
     * @param array<string, mixed> $overrides Named constructor-argument overrides.
     *
     * @throws ClassResolutionException If the class cannot be resolved.
     */
    public function resolve(string $id, array $overrides = []): object
    {
        return $this->constructorResolver->resolve($id, $overrides);
    }

    /**
     * Invokes a callable, resolving its parameters from the container.
     *
     * @param array<string, mixed> $namedOverrides Values keyed by parameter name.
     *
     * @throws UnresolvableParameterException If a parameter cannot be resolved.
     */
    public function call(callable $callable, array $namedOverrides = []): mixed
    {
        $reflection = new ReflectionFunction(Closure::fromCallable($callable));

        $args = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $namedOverrides)) {
                $args[] = $namedOverrides[$name];

                continue;
            }

            if ($parameter->isVariadic()) {
                foreach ($this->parameterResolver->resolveVariadicParameter($parameter) as $value) {
                    $args[] = $value;
                }

                continue;
            }

            $args[] = $this->parameterResolver->resolveParameter($parameter);
        }

        return $callable(...$args);
    }
}
