<?php declare(strict_types=1);

namespace Temant\Container\Resolver;

use Temant\Container\ContainerInterface;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionUnionType;
use Temant\Container\Exception\UnresolvableParameterException;

/**
 * ParameterResolver class responsible for resolving dependencies based on reflection.
 *
 * This class resolves a single parameter by determining its type and retrieving the
 * corresponding dependency from the container. If autowiring is enabled, the resolver
 * will attempt to fetch the required dependencies automatically. The resolver throws
 * specific exceptions if a parameter cannot be resolved.
 */
class ParameterResolver
{
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
        $type = $param->getType();

        // Parameter must be type-hinted
        if ($type === null) {
            throw UnresolvableParameterException::notTypeHinted($param->getName());
        }

        // Union types are not supported
        if ($type instanceof ReflectionUnionType) {
            throw UnresolvableParameterException::unionTypeNotSupported($param->getName());
        }

        // Resolve if default value isset
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // Resolve parameter if it's a named type and not a built-in type
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $className = $type->getName();

            if ($this->container->has($className)) {
                return $this->container->get($className);
            }

            if ($this->autowiringEnabled) {
                return $this->container->get($className);
            }

            throw UnresolvableParameterException::notRegisteredInContainer($param->getName(), $className);
        }

        throw UnresolvableParameterException::unsupportedType($param->getName(), (string) $type);
    }
}