<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use ReflectionClass;
use ReflectionMethod;
use Temant\Container\Exception\ClassResolutionException;

use function array_map;
use function array_pop;
use function class_exists;
use function implode;
use function in_array;

/**
 * Resolves and instantiates a class by analyzing its constructor and injecting dependencies.
 *
 * Uses reflection to inspect the constructor, delegates parameter resolution to
 * {@see ParameterResolver}, and maintains a resolving stack (by reference) to detect
 * circular dependencies.
 */
final class ConstructorResolver
{
    /**
     * @param ParameterResolver    $parameterResolver The resolver for individual constructor parameters.
     * @param list<class-string> &$resolvingStack    Reference to the current resolving stack for circular dependency detection.
     */
    public function __construct(
        private readonly ParameterResolver $parameterResolver,
        private array &$resolvingStack,
    ) {
    }

    /**
     * Resolves and instantiates a class by its fully qualified name.
     *
     * Checks that the class exists and is instantiable, detects circular dependencies,
     * resolves constructor parameters, and returns a new instance.
     *
     * @param class-string $id The fully qualified class name to resolve.
     * @return object The newly created instance.
     *
     * @throws ClassResolutionException If the class does not exist, is not instantiable,
     *                                  or has a circular dependency.
     */
    public function resolve(string $id): object
    {
        if (!class_exists($id)) {
            throw ClassResolutionException::classNotFound($id);
        }

        if (in_array($id, $this->resolvingStack, true)) {
            $chain = implode(' -> ', [...$this->resolvingStack, $id]);
            throw ClassResolutionException::circularDependency($id, $chain);
        }

        $this->resolvingStack[] = $id;

        try {
            $reflectionClass = new ReflectionClass($id);

            if (!$reflectionClass->isInstantiable()) {
                throw ClassResolutionException::notInstantiable($id);
            }

            $constructor = $reflectionClass->getConstructor();

            if ($constructor === null) {
                return $reflectionClass->newInstance();
            }

            $dependencies = $this->resolveDependencies($constructor);

            return $reflectionClass->newInstanceArgs($dependencies);
        } finally {
            array_pop($this->resolvingStack);
        }
    }

    /**
     * Resolves all parameters of a constructor method.
     *
     * @param ReflectionMethod $constructor The constructor reflection.
     * @return list<mixed> The resolved dependency values.
     */
    private function resolveDependencies(ReflectionMethod $constructor): array
    {
        return array_map(
            $this->parameterResolver->resolveParameter(...),
            $constructor->getParameters(),
        );
    }
}
