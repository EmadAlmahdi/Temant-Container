<?php

declare(strict_types=1);

namespace Temant\Container;

use Closure;
use Temant\Container\Exception\ContainerException;

/**
 * Fluent builder for contextual bindings.
 *
 * ```php
 * $container->when(ReportController::class)
 *           ->needs(LoggerInterface::class)
 *           ->give(FileLogger::class);
 *
 * $container->when(Dashboard::class)
 *           ->needs(WidgetInterface::class)
 *           ->giveTagged('widgets');           // typed variadic: WidgetInterface ...$widgets
 * ```
 */
final class ContextualBindingBuilder
{
    private ?string $abstract = null;

    /**
     * @param string $consumer The consuming class that triggers this binding.
     */
    public function __construct(
        private readonly Container $container,
        private readonly string $consumer,
    ) {
    }

    /**
     * Declares the abstract type the consumer depends on.
     *
     * @return $this
     */
    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;

        return $this;
    }

    /**
     * Declares what to provide: a concrete class name, a factory closure, or -- for a
     * typed variadic parameter -- a list of identifiers.
     *
     * @param string|Closure(ContainerInterface): object|list<string> $concrete
     *
     * @throws ContainerException If {@see needs()} was not called first.
     */
    public function give(string|Closure|array $concrete): void
    {
        $this->container->addContextualBinding($this->consumer, $this->requireAbstract(), $concrete);
    }

    /**
     * Provides every service registered under a tag. Intended for typed variadic
     * parameters, where all tagged services are injected.
     *
     * @throws ContainerException If {@see needs()} was not called first.
     */
    public function giveTagged(string $tag): void
    {
        $abstract = $this->requireAbstract();

        $this->container->addContextualBinding(
            $this->consumer,
            $abstract,
            fn(Container $container): array => $container->tagged($tag),
        );
    }

    /**
     * @throws ContainerException
     */
    private function requireAbstract(): string
    {
        if ($this->abstract === null) {
            throw new ContainerException('Call needs() before give()/giveTagged(): when()->needs()->give().');
        }

        return $this->abstract;
    }
}
