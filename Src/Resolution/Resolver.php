<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use Closure;
use ReflectionFunction;
use Temant\Container\Contract\ResolverContainer;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Exception\UnresolvableParameterException;
use Temant\Container\Reflection\ParameterDescriptor;
use Temant\Container\Reflection\ReflectionCache;

use function array_key_exists;

/**
 * Entry point for the resolution layer: instantiates classes and invokes callables
 * with their dependencies injected.
 *
 * Delegates class instantiation to {@see ConstructorResolver} and per-parameter
 * resolution to {@see ParameterResolver}, sharing one {@see ResolvingStack} between
 * them for circular-dependency detection and contextual-binding context.
 *
 * @internal
 */
final class Resolver
{
    private readonly ResolvingStack $stack;
    private readonly ParameterResolver $parameterResolver;
    private readonly ConstructorResolver $constructorResolver;

    public function __construct(ResolverContainer $container, ReflectionCache $reflectionCache)
    {
        $this->stack = new ResolvingStack();
        $this->parameterResolver = new ParameterResolver($container, $this->stack);
        $this->constructorResolver = new ConstructorResolver(
            $this->parameterResolver,
            $this->stack,
            $reflectionCache,
        );
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
            if (array_key_exists($parameter->getName(), $namedOverrides)) {
                $args[] = $namedOverrides[$parameter->getName()];

                continue;
            }

            $descriptor = ParameterDescriptor::fromReflection($parameter);

            if ($descriptor->isVariadic) {
                foreach ($this->parameterResolver->resolveVariadicParameter($descriptor) as $value) {
                    $args[] = $value;
                }

                continue;
            }

            $args[] = $this->parameterResolver->resolveParameter($descriptor);
        }

        return $callable(...$args);
    }
}
