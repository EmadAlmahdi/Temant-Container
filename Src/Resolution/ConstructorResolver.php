<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Reflection\ParameterDescriptor;
use Temant\Container\Reflection\ReflectionCache;

use function array_key_exists;
use function class_exists;
use function is_array;

/**
 * Instantiates a class from its cached {@see \Temant\Container\Reflection\ConstructorDescriptor}.
 *
 * All reflection happens once, in {@see ReflectionCache}; this class only executes
 * the resulting plan and spreads the arguments into `new`.
 *
 * @internal
 */
final class ConstructorResolver
{
    public function __construct(
        private readonly ParameterResolver $parameterResolver,
        private readonly ResolvingStack $stack,
        private readonly ReflectionCache $reflectionCache,
    ) {
    }

    /**
     * @param class-string $id
     * @param array<string, mixed> $overrides Named constructor-argument overrides.
     *
     * @throws ClassResolutionException If the class is missing, not instantiable, or
     *                                  part of a circular dependency chain.
     */
    public function resolve(string $id, array $overrides = []): object
    {
        if (!class_exists($id)) {
            throw ClassResolutionException::classNotFound($id);
        }

        if ($this->stack->contains($id)) {
            throw ClassResolutionException::circularDependency($id, $this->stack->chain($id));
        }

        $descriptor = $this->reflectionCache->constructorFor($id);

        if (!$descriptor->isInstantiable) {
            throw ClassResolutionException::notInstantiable($id);
        }

        if (!$descriptor->hasConstructor) {
            return new $id();
        }

        $this->stack->push($id);

        try {
            $arguments = $this->resolveArguments($descriptor->parameters, $overrides);

            return new $id(...$arguments);
        } finally {
            $this->stack->pop();
        }
    }

    /**
     * @param list<ParameterDescriptor> $parameters
     * @param array<string, mixed> $overrides
     * @return list<mixed>
     */
    private function resolveArguments(array $parameters, array $overrides): array
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            if (array_key_exists($parameter->name, $overrides)) {
                $override = $overrides[$parameter->name];

                if ($parameter->isVariadic && is_array($override)) {
                    foreach ($override as $value) {
                        $arguments[] = $value;
                    }
                } else {
                    $arguments[] = $override;
                }

                continue;
            }

            if ($parameter->isVariadic) {
                foreach ($this->parameterResolver->resolveVariadicParameter($parameter) as $value) {
                    $arguments[] = $value;
                }

                continue;
            }

            $arguments[] = $this->parameterResolver->resolveParameter($parameter);
        }

        return $arguments;
    }
}
