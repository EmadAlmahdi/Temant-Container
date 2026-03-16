<?php

declare(strict_types=1);

namespace Temant\Container;

use Closure;

/**
 * Fluent builder for contextual bindings.
 *
 * Usage:
 *   $container->when(UserController::class)
 *             ->needs(LoggerInterface::class)
 *             ->give(FileLogger::class);
 */
final class ContextualBindingBuilder
{
    private string $abstract;

    /**
     * @param Container $container The container to register the binding on.
     * @param string    $consumer  The consuming class that triggers this binding.
     */
    public function __construct(
        private readonly Container $container,
        private readonly string $consumer,
    ) {
    }

    /**
     * Specifies the abstract type or interface that the consumer needs.
     *
     * @param string $abstract The abstract type identifier.
     * @return $this
     */
    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;

        return $this;
    }

    /**
     * Specifies the concrete implementation to provide.
     *
     * @param string|Closure(ContainerInterface): object $concrete A class name or factory closure.
     * @return void
     */
    public function give(string|Closure $concrete): void
    {
        $this->container->addContextualBinding($this->consumer, $this->abstract, $concrete);
    }
}
