<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use ReflectionClass;
use ReflectionMethod;
use Temant\Container\Exception\ClassResolutionException;

use function array_key_exists;
use function array_pop;
use function array_push;
use function class_exists;
use function implode;
use function in_array;
use function is_array;

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
     * @param class-string         $id        The fully qualified class name to resolve.
     * @param array<string, mixed> $overrides Named parameter overrides for the constructor.
     * @return object The newly created instance.
     *
     * @throws ClassResolutionException If the class does not exist, is not instantiable,
     *                                  or has a circular dependency.
     */
    public function resolve(string $id, array $overrides = []): object
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

            $dependencies = $this->resolveDependencies($constructor, $overrides);

            return $reflectionClass->newInstanceArgs($dependencies);
        } finally {
            array_pop($this->resolvingStack);
        }
    }

    /**
     * Resolves all parameters of a constructor method.
     *
     * Handles named overrides and variadic parameters.
     *
     * @param ReflectionMethod     $constructor The constructor reflection.
     * @param array<string, mixed> $overrides   Named parameter overrides.
     * @return list<mixed> The resolved dependency values.
     */
    private function resolveDependencies(ReflectionMethod $constructor, array $overrides = []): array
    {
        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            // Named override
            if (array_key_exists($name, $overrides)) {
                if ($param->isVariadic() && is_array($overrides[$name])) {
                    array_push($args, ...$overrides[$name]);
                } else {
                    $args[] = $overrides[$name];
                }
                continue;
            }

            // Variadic parameter
            if ($param->isVariadic()) {
                array_push($args, ...$this->parameterResolver->resolveVariadicParameter($param));
                continue;
            }

            $args[] = $this->parameterResolver->resolveParameter($param);
        }

        return $args;
    }
}
