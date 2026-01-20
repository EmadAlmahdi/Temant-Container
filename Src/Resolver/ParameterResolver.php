<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Temant\Container\ContainerInterface;
use Temant\Container\Exception\UnresolvableParameterException;

use function class_exists;

/**
 * Resolves constructor/callable parameters using reflection + container rules.
 *
 * Resolution rules:
 * - Untyped parameters are not resolvable
 * - Union types are not supported (by design)
 * - Variadics are not supported (by design)
 * - For object types:
 *      1) if container has it => use it
 *      2) if autowiring enabled and class exists => container->get(type)
 *      3) if nullable => null
 *      4) if default exists => default
 *      5) else => throw
 * - For built-ins (string/int/bool/array/etc.):
 *      - use default if available
 *      - if nullable => null
 *      - else => throw
 */
final class ParameterResolver
{
    /**
     * @param bool $autowiringEnabled Whether autowiring is enabled.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $autowiringEnabled
    ) {
    }

    /**
     * Resolves a single parameter by its type.
     *
     * If autowiring is enabled, this method determines the type of the parameter
     * and retrieves the corresponding dependency from the container. If the
     * parameter type is not supported or autowiring is disabled, an exception
     * is thrown.
     *
     * @param ReflectionParameter $param The parameter reflection.
     * @return mixed The resolved parameter value.
     * @throws UnresolvableParameterException If the parameter cannot be resolved.
     */
    public function resolveParameter(ReflectionParameter $param): mixed
    {
        if ($param->isVariadic()) {
            throw UnresolvableParameterException::variadicNotSupported($param->getName());
        }

        $type = $param->getType();

        if ($type === null) {
            throw UnresolvableParameterException::notTypeHinted($param->getName());
        }

        if ($type instanceof ReflectionUnionType) {
            throw UnresolvableParameterException::unionTypeNotSupported($param->getName());
        }

        if (!$type instanceof ReflectionNamedType) {
            throw UnresolvableParameterException::unsupportedType($param->getName(), (string) $type);
        }

        // Object types (classes/interfaces)
        if (!$type->isBuiltin()) {
            $className = $type->getName();
            $nullable = $type->allowsNull();

            // 1) Explicitly registered?
            if ($this->container->has($className)) {
                return $this->container->get($className);
            }

            // 2) Autowire?
            if ($this->autowiringEnabled === true && class_exists($className)) {
                return $this->container->get($className);
            }

            // 3) Nullable => null
            if ($nullable) {
                return null;
            }

            // 4) Default (AFTER attempting DI;)
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw UnresolvableParameterException::notRegisteredInContainer($param->getName(), $className);
        }

        // Built-in scalar/array/etc.
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($type->allowsNull()) {
            return null;
        }

        throw UnresolvableParameterException::unsupportedType($param->getName(), $type->getName());
    }
}