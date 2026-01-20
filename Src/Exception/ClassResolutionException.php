<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

use Exception;

/**
 * Exception thrown when a class cannot be resolved or instantiated.
 */
class ClassResolutionException extends Exception
{
    /**
     * Creates an exception for a class that does not exist.
     *
     * @param string $className The name of the class that could not be resolved.
     * @return self The constructed exception.
     */
    public static function classNotFound(string $className): self
    {
        return new self("Class $className is not a valid resolvable class.");
    }

    /**
     * Creates an exception for a class that is not instantiable.
     *
     * @param string $className The name of the class that could not be instantiated.
     * @return self The constructed exception.
     */
    public static function notInstantiable(string $className): self
    {
        return new self("Class $className is not instantiable.");
    }

    /**
     * Creates an exception for a circular dependency detected during class resolution.
     *
     * @param string $class The name of the class involved in the circular dependency.
     * @param string $chain A string representation of the dependency chain.
     * @return self The constructed exception.
     */
    public static function circularDependency(string $class, string $chain): self
    {
        return new self("Circular dependency detected while resolving $class: $chain");
    }
}
