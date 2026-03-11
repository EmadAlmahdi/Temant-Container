<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

/**
 * Exception thrown when a constructor or callable parameter cannot be resolved
 * during dependency injection.
 *
 * This covers missing type hints, unsupported type combinations (union, intersection,
 * variadic), and parameters whose types are not registered in the container.
 */
class UnresolvableParameterException extends ContainerException
{
    /**
     * Creates an exception for parameters without a type hint.
     *
     * @param string $parameterName The name of the untyped parameter.
     * @return self
     */
    public static function notTypeHinted(string $parameterName): self
    {
        return new self("Parameter \${$parameterName} has no type hint and cannot be resolved.");
    }

    /**
     * Creates an exception for parameters with unsupported union types.
     *
     * @param string $parameterName The name of the parameter with a union type.
     * @return self
     */
    public static function unionTypeNotSupported(string $parameterName): self
    {
        return new self("Union types are not supported for parameter \${$parameterName}.");
    }

    /**
     * Creates an exception for parameters with unsupported intersection types.
     *
     * @param string $parameterName The name of the parameter with an intersection type.
     * @return self
     */
    public static function intersectionTypeNotSupported(string $parameterName): self
    {
        return new self("Intersection types are not supported for parameter \${$parameterName}.");
    }

    /**
     * Creates an exception when a class is not registered in the container and cannot be autowired.
     *
     * @param string $parameterName The name of the parameter.
     * @param string $className The fully qualified class name that could not be resolved.
     * @return self
     */
    public static function notRegisteredInContainer(string $parameterName, string $className): self
    {
        return new self(
            "Cannot resolve parameter \${$parameterName} of type {$className}: "
            . "not registered in container and autowiring is disabled."
        );
    }

    /**
     * Creates an exception when the parameter type is unsupported (e.g. built-in without default).
     *
     * @param string $parameterName The name of the parameter.
     * @param string $type The type name that cannot be resolved.
     * @return self
     */
    public static function unsupportedType(string $parameterName, string $type): self
    {
        return new self("Cannot resolve parameter \${$parameterName} of type {$type}.");
    }

    /**
     * Creates an exception for variadic parameters which are not supported.
     *
     * @param string $paramName The name of the variadic parameter.
     * @return self
     */
    public static function variadicNotSupported(string $paramName): self
    {
        return new self("Variadic parameter \${$paramName} is not supported for autowiring.");
    }
}
