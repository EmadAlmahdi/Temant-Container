<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use Temant\Container\ContainerInterface;
use Temant\Container\Resolver\ConstructorResolver;
use Temant\Container\Resolver\ParameterResolver;

/**
 * Resolver class for instantiating and resolving dependencies.
 *
 * This class handles the instantiation of classes and their dependencies
 * using reflection and optionally supports autowiring.
 */
class Resolver
{
    private ConstructorResolver $constructorResolver;

    /**
     * Constructor for the Resolver class.
     *
     * @param ContainerInterface $container The container used for resolving dependencies.
     * @param bool $autowiringEnabled Whether autowiring is enabled or not.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $autowiringEnabled
    ) {
        // Initialize ParameterResolver with the current container and autowiring setting
        $parameterResolver = new ParameterResolver($this->container, $this->autowiringEnabled);

        // Initialize ConstructorResolver with the ParameterResolver
        $this->constructorResolver = new ConstructorResolver($parameterResolver);
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
}