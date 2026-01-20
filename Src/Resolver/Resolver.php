<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use Temant\Container\ContainerInterface;
use Temant\Container\Resolver\ConstructorResolver;
use Temant\Container\Resolver\ParameterResolver;

use function array_key_exists;
use function is_array;

/**
 * Resolver class for instantiating and resolving dependencies.
 *
 * This class handles the instantiation of classes and their dependencies
 * using reflection and optionally supports autowiring.
 */
class Resolver
{
    private ConstructorResolver $constructorResolver;
    private ParameterResolver $parameterResolver;

    /**
     * Current resolving stack to detect circular dependencies.
     *
     * @var list<class-string>
     */
    private array $resolvingStack = [];

    /**
     * Constructor for the Resolver class.
     *
     * @param ContainerInterface $container The container used for resolving dependencies.
     */
    public function __construct(private readonly ContainerInterface $container)
    {
        // Autowiring setting is read dynamically at runtime.
        $this->parameterResolver = new ParameterResolver(
            $this->container,
            $this->container->hasAutowiring()
        );

        $this->constructorResolver = new ConstructorResolver(
            $this->parameterResolver,
            $this->resolvingStack
        );
    }

    /**
     * Resolves and instantiates a class based on its name.
     *
     * This method delegates the resolution and instantiation of the class
     * to the ConstructorResolver. It handles checking if the class exists and
     * is instantiable, and if so, it resolves any constructor dependencies.
     *
     * @param string $id The fully qualified class name to resolve.
     * @return object The resolved instance of the class.
     */
    public function resolve(string $id): object
    {
        return $this->constructorResolver->resolve($id);
    }

    /**
     * Call a callable and autowire its parameters.
     *
     * @param callable|Closure|string $callable
     * @param array<string,mixed> $namedOverrides Override by parameter name
     */
    public function call(callable|Closure|string $callable, array $namedOverrides = []): mixed
    {
        $ref = is_array($callable)
            ? new ReflectionMethod($callable[0], $callable[1])
            : new ReflectionFunction($callable);

        $args = [];

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $namedOverrides)) {
                $args[] = $namedOverrides[$name];
                continue;
            }

            $args[] = $this->parameterResolver->resolveParameter($param);
        }

        return $callable(...$args);
    }
}