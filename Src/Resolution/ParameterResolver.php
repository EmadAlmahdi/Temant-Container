<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use Closure;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Temant\Container\Attribute\Inject;
use Temant\Container\Contract\ResolverContainer;
use Temant\Container\Exception\UnresolvableParameterException;

use function array_map;
use function array_values;
use function is_array;

/**
 * Resolves a single constructor or callable parameter to a value.
 *
 * Resolution order for a non-variadic parameter:
 *
 *   1. `#[Inject(id)]` attribute -- resolve that container ID directly.
 *   2. No type hint -- unresolvable (throws).
 *   3. Union type -- try each object member in turn; first that resolves wins.
 *   4. Intersection type -- unsupported (throws).
 *   5. Object type -- contextual binding, then registered entry, then autowiring,
 *      then null (if nullable), then default value, else throw.
 *   6. Built-in type -- default value, then null (if nullable), else throw.
 *
 * Variadic parameters resolve to an array via contextual bindings, tagged services,
 * or a single registered instance; an empty array is always an acceptable result.
 *
 * @internal
 */
final class ParameterResolver
{
    public function __construct(
        private readonly ResolverContainer $container,
        private readonly ResolvingStack $stack,
    ) {
    }

    /**
     * @throws UnresolvableParameterException If the parameter cannot be resolved.
     */
    public function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $inject = $this->injectAttribute($parameter);

        if ($inject !== null) {
            return $this->container->get($inject->id);
        }

        if ($parameter->isVariadic()) {
            throw UnresolvableParameterException::variadicNotSupported($parameter->getName());
        }

        $type = $parameter->getType();

        if ($type === null) {
            throw UnresolvableParameterException::notTypeHinted($parameter->getName());
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->resolveUnionType($parameter, $type);
        }

        if ($type instanceof ReflectionIntersectionType) {
            throw UnresolvableParameterException::intersectionTypeNotSupported($parameter->getName());
        }

        // Only ReflectionNamedType can remain, but narrow explicitly for the type checker.
        if (!$type instanceof ReflectionNamedType) {
            throw UnresolvableParameterException::unsupportedType($parameter->getName(), (string) $type);
        }

        if ($type->isBuiltin()) {
            return $this->resolveBuiltinType($parameter, $type);
        }

        return $this->resolveObjectType($parameter, $type->getName(), $type->allowsNull());
    }

    /**
     * Resolves a typed variadic parameter to a list of values.
     *
     * @return list<mixed>
     */
    public function resolveVariadicParameter(ReflectionParameter $parameter): array
    {
        $inject = $this->injectAttribute($parameter);

        if ($inject !== null) {
            /** @var list<mixed> $tagged */
            $tagged = $this->container->tagged($inject->id);

            if ($tagged !== []) {
                return $tagged;
            }

            return $this->container->has($inject->id) ? [$this->container->get($inject->id)] : [];
        }

        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $className = $type->getName();

        $contextual = $this->contextualBinding($className);

        if ($contextual !== null) {
            return $this->resolveVariadicContextual($contextual);
        }

        $tagged = $this->container->tagged($className);

        if ($tagged !== []) {
            return $tagged;
        }

        if ($this->container->has($className)) {
            return [$this->container->get($className)];
        }

        return [];
    }

    /**
     * @param string|Closure|list<string> $contextual
     * @return list<mixed>
     */
    private function resolveVariadicContextual(string|Closure|array $contextual): array
    {
        if ($contextual instanceof Closure) {
            $result = $contextual($this->container);

            return is_array($result) ? array_values($result) : [$result];
        }

        if (is_array($contextual)) {
            return array_map(fn(string $id): mixed => $this->container->get($id), $contextual);
        }

        return [$this->container->get($contextual)];
    }

    private function resolveUnionType(ReflectionParameter $parameter, ReflectionUnionType $type): mixed
    {
        $objectMembers = [];

        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionNamedType && !$member->isBuiltin()) {
                $objectMembers[] = $member->getName();
            }
        }

        // Pass 1: an explicit registration, binding, or contextual binding for any member.
        foreach ($objectMembers as $className) {
            $contextual = $this->contextualBinding($className);

            if ($contextual !== null && !is_array($contextual)) {
                return $this->resolveScalarContextual($contextual);
            }

            if ($this->container->isBound($className)) {
                return $this->container->get($className);
            }
        }

        // Pass 2: autowire the first member the container can build.
        if ($this->container->hasAutowiring()) {
            foreach ($objectMembers as $className) {
                if ($this->container->has($className)) {
                    return $this->container->get($className);
                }
            }
        }

        if ($type->allowsNull()) {
            return null;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw UnresolvableParameterException::unionTypeNotSupported($parameter->getName());
    }

    private function resolveObjectType(ReflectionParameter $parameter, string $className, bool $nullable): mixed
    {
        $contextual = $this->contextualBinding($className);

        if ($contextual !== null && !is_array($contextual)) {
            return $this->resolveScalarContextual($contextual);
        }

        // has() already accounts for autowiring, bindings and the parent container.
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        if ($nullable) {
            return null;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw UnresolvableParameterException::notRegisteredInContainer($parameter->getName(), $className);
    }

    private function resolveBuiltinType(ReflectionParameter $parameter, ReflectionNamedType $type): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type->allowsNull()) {
            return null;
        }

        throw UnresolvableParameterException::unsupportedType($parameter->getName(), $type->getName());
    }

    private function resolveScalarContextual(string|Closure $contextual): mixed
    {
        if ($contextual instanceof Closure) {
            return $contextual($this->container);
        }

        return $this->container->get($contextual);
    }

    /**
     * @return string|Closure|list<string>|null
     */
    private function contextualBinding(string $abstract): string|Closure|array|null
    {
        $consumer = $this->stack->current();

        if ($consumer === null) {
            return null;
        }

        return $this->container->getContextualBinding($consumer, $abstract);
    }

    private function injectAttribute(ReflectionParameter $parameter): ?Inject
    {
        $attributes = $parameter->getAttributes(Inject::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
