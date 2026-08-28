<?php

declare(strict_types=1);

namespace Temant\Container\Reflection;

/**
 * Everything the resolver needs to instantiate a class, extracted from reflection once.
 *
 * @internal
 */
final class ConstructorDescriptor
{
    /**
     * @param list<ParameterDescriptor> $parameters Ordered constructor parameters (empty if none).
     */
    public function __construct(
        public readonly bool $isInstantiable,
        public readonly bool $hasConstructor,
        public readonly array $parameters,
    ) {
    }

    /**
     * Whether this descriptor is safe to write to a persistent store. Descriptors
     * with an object-valued default parameter are not -- that value must be
     * recreated from reflection each time, so they stay in the in-memory cache only.
     */
    public function isPersistable(): bool
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->defaultIsComplex) {
                return false;
            }
        }

        return true;
    }
}
