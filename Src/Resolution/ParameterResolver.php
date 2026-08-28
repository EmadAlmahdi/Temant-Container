<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use Closure;
use Temant\Container\Contract\ResolverContainer;
use Temant\Container\Exception\UnresolvableParameterException;
use Temant\Container\Reflection\ParameterDescriptor;
use Temant\Container\Reflection\ParameterTypeKind;

use function array_map;
use function array_values;
use function is_array;

/**
 * Turns a {@see ParameterDescriptor} (the reflected facts about a parameter) into
 * an actual value, applying the container's live state.
 *
 * Resolution order for a non-variadic parameter:
 *
 *   1. `#[Inject(id)]` -- resolve that container id directly.
 *   2. No type hint -- unresolvable (throws).
 *   3. Union type -- a bound member first, then the first autowirable member,
 *      then null / default, else throw.
 *   4. Intersection type -- unsupported (throws).
 *   5. Object type -- contextual binding, then any registered/autowirable entry,
 *      then null (if nullable), then default value, else throw.
 *   6. Built-in type -- default value, then null (if nullable), else throw.
 *
 * Variadic parameters resolve to a list via contextual bindings, tagged services,
 * or a single registered instance; an empty list is always acceptable.
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
    public function resolveParameter(ParameterDescriptor $parameter): mixed
    {
        if ($parameter->injectId !== null) {
            return $this->container->get($parameter->injectId);
        }

        if ($parameter->isVariadic) {
            throw UnresolvableParameterException::variadicNotSupported($parameter->name);
        }

        return match ($parameter->typeKind) {
            ParameterTypeKind::None => throw UnresolvableParameterException::notTypeHinted($parameter->name),
            ParameterTypeKind::Intersection => throw UnresolvableParameterException::intersectionTypeNotSupported($parameter->name),
            ParameterTypeKind::Union => $this->resolveUnion($parameter),
            ParameterTypeKind::Named => $parameter->isBuiltin
                ? $this->resolveBuiltin($parameter)
                : $this->resolveObject($parameter, (string) $parameter->typeName),
        };
    }

    /**
     * @return list<mixed>
     */
    public function resolveVariadicParameter(ParameterDescriptor $parameter): array
    {
        if ($parameter->injectId !== null) {
            $tagged = $this->container->tagged($parameter->injectId);

            if ($tagged !== []) {
                return $tagged;
            }

            return $this->container->has($parameter->injectId)
                ? [$this->container->get($parameter->injectId)]
                : [];
        }

        if ($parameter->typeKind !== ParameterTypeKind::Named || $parameter->isBuiltin || $parameter->typeName === null) {
            return [];
        }

        $className = $parameter->typeName;
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

    private function resolveObject(ParameterDescriptor $parameter, string $className): mixed
    {
        $contextual = $this->contextualBinding($className);

        if ($contextual !== null && !is_array($contextual)) {
            return $this->resolveScalarContextual($contextual);
        }

        // has() already accounts for autowiring, bindings and the parent container.
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        if ($parameter->allowsNull) {
            return null;
        }

        if ($parameter->hasDefault) {
            return $parameter->default();
        }

        throw UnresolvableParameterException::notRegisteredInContainer($parameter->name, $className);
    }

    private function resolveBuiltin(ParameterDescriptor $parameter): mixed
    {
        if ($parameter->hasDefault) {
            return $parameter->default();
        }

        if ($parameter->allowsNull) {
            return null;
        }

        throw UnresolvableParameterException::unsupportedType($parameter->name, $parameter->typeName ?? 'mixed');
    }

    private function resolveUnion(ParameterDescriptor $parameter): mixed
    {
        // Pass 1: a contextual binding or an explicit registration for any member.
        foreach ($parameter->unionTypeNames as $className) {
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
            foreach ($parameter->unionTypeNames as $className) {
                if ($this->container->has($className)) {
                    return $this->container->get($className);
                }
            }
        }

        if ($parameter->allowsNull) {
            return null;
        }

        if ($parameter->hasDefault) {
            return $parameter->default();
        }

        throw UnresolvableParameterException::unionTypeNotSupported($parameter->name);
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
}
