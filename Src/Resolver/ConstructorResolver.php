<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use Temant\Container\Exception\ClassResolutionException;
use ReflectionClass;
use ReflectionMethod;

use function class_exists;
use function array_map;

/**
 * Resolves and instantiates a class by handling its constructor and dependencies.
 *
 * This class is responsible for creating instances of classes by resolving their
 * constructors and injecting any necessary dependencies. It uses reflection to
 * analyze the constructor and parameter types, and it relies on a parameter resolver
 * to provide the actual values.
 */
class ConstructorResolver
{
    public function __construct(
        private readonly ParameterResolver $parameterResolver
    ) {
    }

    /**
     * Resolves and instantiates a class based on its name.
     *
     * This method checks if the class exists and is instantiable. If the class has a
     * constructor, it resolves its dependencies and creates an instance of the class
     * with those dependencies injected. If there is no constructor, it creates an
     * instance with default arguments.
     *
     * @param string $id The fully qualified class name to resolve.
     * @return object The resolved instance of the class.
     * @throws ClassResolutionException If the class cannot be instantiated, or if there is an error
     *                                    resolving the class or its dependencies.
     */
    public function resolve(string $id): object
    {
        if (!class_exists($id)) {
            throw ClassResolutionException::classNotFound($id);
        }

        $reflectionClass = new ReflectionClass($id);

        if (!$reflectionClass->isInstantiable()) {
            throw ClassResolutionException::notInstantiable($id);
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return $reflectionClass->newInstance();
        }

        // Resolve and inject dependencies into the constructor
        $dependencies = $this->resolveDependencies($constructor);
        return $reflectionClass->newInstanceArgs($dependencies);
    }

    /**
     * Resolves the dependencies required by a constructor.
     *
     * This method retrieves the parameters of the constructor and uses the parameter
     * resolver to resolve each parameter's value. It returns an array of resolved
     * dependencies which are used to instantiate the class.
     *
     * @param ReflectionMethod $constructor The reflection of the constructor to resolve.
     * @return array<mixed> The resolved dependencies for the constructor.
     */
    private function resolveDependencies(ReflectionMethod $constructor): array
    {
        return array_map($this->parameterResolver->resolveParameter(...), $constructor->getParameters());
    }
}
