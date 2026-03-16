<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use Closure;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Temant\Container\Container;
use Temant\Container\Exception\UnresolvableParameterException;

use function class_exists;
use function end;

/**
 * Resolves constructor/callable parameters using reflection and container rules.
 *
 * Resolution rules (in order):
 *
 * - Untyped parameters: not resolvable (throws).
 * - Union/Intersection types: not supported (throws).
 *
 * For object types (class/interface):
 *   1. Check contextual bindings for the current consumer.
 *   2. If the container has the type registered, use it.
 *   3. If autowiring is enabled and the class exists, resolve via the container.
 *   4. If nullable, return null.
 *   5. If a default value exists, return it.
 *   6. Otherwise, throw.
 *
 * For built-in types (string, int, bool, array, etc.):
 *   1. If a default value exists, return it.
 *   2. If nullable, return null.
 *   3. Otherwise, throw.
 *
 * Variadic parameters:
 *   - Resolved via tagged services or single-instance resolution.
 *   - Gracefully returns empty if unresolvable (variadic allows 0 args).
 */
final class ParameterResolver
{
    /**
     * @param Container $container The container used for resolving dependencies.
     * @param list<class-string> $resolvingStack Reference to the resolving stack for contextual binding context.
     */
    public function __construct(
        private readonly Container $container,
        private array &$resolvingStack,
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
     * Resolves a variadic parameter to an array of values.
     *
     * For typed variadic parameters (e.g., Foo ...$foos):
     *   1. Check tagged services for the type name.
     *   2. Try resolving a single instance.
     *   3. Return empty array if unresolvable.
     *
     * @param ReflectionParameter $param The variadic parameter reflection.
     * @return list<mixed> The resolved values.
     */
    public function resolveVariadicParameter(ReflectionParameter $param): array
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $className = $type->getName();

        // 1) Check tagged services
        $tagged = $this->container->tagged($className);
        if ($tagged !== []) {
            return $tagged;
        }

        // 2) Try single resolution
        if ($this->container->has($className)) {
            return [$this->container->get($className)];
        }

        // 3) Variadic allows 0 args
        return [];
    }

    /**
     * Resolves a parameter with an object (class/interface) type.
     *
     * @param ReflectionParameter $param The parameter reflection.
     * @param ReflectionNamedType $type The named type reflection.
     * @return mixed The resolved object instance, default value, or null.
     *
     * @throws UnresolvableParameterException If the object type cannot be resolved.
     */
    private function resolveObjectType(ReflectionParameter $param, ReflectionNamedType $type): mixed
    {
        $className = $type->getName();

        // 0) Check contextual bindings
        if ($this->resolvingStack !== []) {
            $consumer = end($this->resolvingStack);
            $contextual = $this->container->getContextualBinding($consumer, $className);
            if ($contextual !== null) {
                if ($contextual instanceof Closure) {
                    return $contextual($this->container);
                }

                return $this->container->get($contextual);
            }
        }

        // 1) Explicitly registered in the container?
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        // 2) Autowire if enabled and class exists
        if ($this->container->hasAutowiring() && class_exists($className)) {
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
     * @param ReflectionNamedType $type The named type reflection.
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
