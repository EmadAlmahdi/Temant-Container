<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

/**
 * Exception thrown when a class cannot be resolved or instantiated.
 *
 * This covers cases such as non-existent classes, abstract/non-instantiable classes,
 * and circular dependency chains detected during autowiring.
 */
class ClassResolutionException extends ContainerException
{
    /**
     * Creates an exception for a class that does not exist.
     *
     * @param string $className The fully qualified class name that could not be found.
     * @return self
     */
    public static function classNotFound(string $className): self
    {
        return new self("Class $className is not a valid resolvable class.");
    }

    /**
     * Creates an exception for a class that is not instantiable (e.g. abstract, interface).
     *
     * @param string $className The fully qualified class name that could not be instantiated.
     * @return self
     */
    public static function notInstantiable(string $className): self
    {
        return new self("Class $className is not instantiable.");
    }

    /**
     * Creates an exception for a circular dependency detected during class resolution.
     *
     * @param string $class The class name involved in the circular dependency.
     * @param string $chain A human-readable representation of the dependency chain (e.g. "A -> B -> A").
     * @return self
     */
    public static function circularDependency(string $class, string $chain): self
    {
        return new self("Circular dependency detected while resolving $class: $chain");
    }
}
