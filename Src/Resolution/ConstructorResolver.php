<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use ReflectionClass;
use ReflectionMethod;
use Temant\Container\Exception\ClassResolutionException;

use function array_key_exists;
use function class_exists;
use function is_array;

/**
 * Instantiates a class by reflecting its constructor and resolving each parameter.
 *
 * @internal
 */
final class ConstructorResolver
{
    public function __construct(
        private readonly ParameterResolver $parameterResolver,
        private readonly ResolvingStack $stack,
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

        $this->stack->push($id);

        try {
            $reflection = new ReflectionClass($id);

            if (!$reflection->isInstantiable()) {
                throw ClassResolutionException::notInstantiable($id);
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstance();
            }

            return $reflection->newInstanceArgs($this->resolveDependencies($constructor, $overrides));
        } finally {
            $this->stack->pop();
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<mixed>
     */
    private function resolveDependencies(ReflectionMethod $constructor, array $overrides): array
    {
        $args = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $overrides)) {
                $override = $overrides[$name];

                if ($parameter->isVariadic() && is_array($override)) {
                    foreach ($override as $value) {
                        $args[] = $value;
                    }
                } else {
                    $args[] = $override;
                }

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

        return $args;
    }
}
