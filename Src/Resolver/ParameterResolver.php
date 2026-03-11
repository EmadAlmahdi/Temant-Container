<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use Closure;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Temant\Container\ContainerInterface;
use Temant\Container\Exception\UnresolvableParameterException;

use function class_exists;

/**
 * Resolves constructor/callable parameters using reflection and container rules.
 *
 * Resolution rules (in order):
 *
 * - Variadic parameters: not supported (throws).
 * - Untyped parameters: not resolvable (throws).
 * - Union/Intersection types: not supported (throws).
 *
 * For object types (class/interface):
 *   1. If the container has the type registered, use it.
 *   2. If autowiring is enabled and the class exists, resolve via the container.
 *   3. If nullable, return null.
 *   4. If a default value exists, return it.
 *   5. Otherwise, throw.
 *
 * For built-in types (string, int, bool, array, etc.):
 *   1. If a default value exists, return it.
 *   2. If nullable, return null.
 *   3. Otherwise, throw.
 */
final class ParameterResolver
{
    /**
     * @param ContainerInterface       $container           The container used for resolving dependencies.
     * @param Closure(): bool $autowiringEnabled   Lazy callback returning the current autowiring state.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Closure $autowiringEnabled,
    ) {
    }

    /**
     * Resolves a single parameter to a value suitable for injection.
     *
     * @param ReflectionParameter $param The parameter reflection to resolve.
     * @return mixed The resolved value.
     *
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

        if ($type instanceof ReflectionIntersectionType) {
            throw UnresolvableParameterException::intersectionTypeNotSupported($param->getName());
        }

        if (!$type instanceof ReflectionNamedType) {
            throw UnresolvableParameterException::unsupportedType($param->getName(), (string) $type);
        }

        if (!$type->isBuiltin()) {
            return $this->resolveObjectType($param, $type);
        }

        return $this->resolveBuiltinType($param, $type);
    }

    /**
     * Resolves a parameter with an object (class/interface) type.
     *
     * @param ReflectionParameter $param The parameter reflection.
     * @param ReflectionNamedType $type  The named type reflection.
     * @return mixed The resolved object instance, default value, or null.
     *
     * @throws UnresolvableParameterException If the object type cannot be resolved.
     */
    private function resolveObjectType(ReflectionParameter $param, ReflectionNamedType $type): mixed
    {
        $className = $type->getName();

        // 1) Explicitly registered in the container?
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        // 2) Autowire if enabled and class exists
        if (($this->autowiringEnabled)() && class_exists($className)) {
            return $this->container->get($className);
        }

        // 3) Nullable => null
        if ($type->allowsNull()) {
            return null;
        }

        // 4) Default value (after attempting DI)
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw UnresolvableParameterException::notRegisteredInContainer($param->getName(), $className);
    }

    /**
     * Resolves a parameter with a built-in scalar/array type.
     *
     * @param ReflectionParameter $param The parameter reflection.
     * @param ReflectionNamedType $type  The named type reflection.
     * @return mixed The default value or null.
     *
     * @throws UnresolvableParameterException If no default or null fallback is available.
     */
    private function resolveBuiltinType(ReflectionParameter $param, ReflectionNamedType $type): mixed
    {
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($type->allowsNull()) {
            return null;
        }

        throw UnresolvableParameterException::unsupportedType($param->getName(), $type->getName());
    }
}
