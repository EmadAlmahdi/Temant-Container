<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

use Exception;

/**
 * Exception thrown when a parameter cannot be resolved in the dependency injection process.
 */
class UnresolvableParameterException extends Exception
{
    /**
     * Creates an exception for parameters without a type hint.
     *
     * @param string $parameterName The name of the parameter.
     * @return self The constructed exception.
     */
    public static function notTypeHinted(string $parameterName): self
    {
        return new self("Parameter {$parameterName} is not type hinted.");
    }

    /**
     * Creates an exception for parameters with unsupported union types.
     *
     * @param string $parameterName The name of the parameter.
     * @return self The constructed exception.
     */
    public static function unionTypeNotSupported(string $parameterName): self
    {
        return new self("Union types are not supported for parameter {$parameterName}.");
    }

    /**
     * Creates an exception when a class is not registered in the container and autowiring is disabled.
     *
     * @param string $parameterName The name of the parameter.
     * @param string $className The class name that could not be resolved.
     * @return self The constructed exception.
     */
    public static function notRegisteredInContainer(string $parameterName, string $className): self
    {
        return new self("Cannot resolve parameter {$parameterName} with type {$className}: not registered in container and autowiring is disabled.");
    }

    /**
     * Creates an exception when the parameter type is unsupported.
     *
     * @param string $parameterName The name of the parameter.
     * @param string $type The type of the parameter.
     * @return self The constructed exception.
     */
    public static function unsupportedType(string $parameterName, string $type): self
    {
        return new self("Cannot resolve parameter {$parameterName} with type {$type}.");
    }

    /**
     * Creates an exception for variadic parameters which are not supported.
     *
     * @param string $paramName The name of the variadic parameter.
     * @return self The constructed exception.
     */
    public static function variadicNotSupported(string $paramName): self
    {
        return new self("Variadic parameter '\$$paramName' is not supported for autowiring.");
    }
}
