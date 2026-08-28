<?php

declare(strict_types=1);

namespace Temant\Container\Registry;

use Temant\Container\Container;
use Temant\Container\ServiceProviderInterface;

/**
 * Tracks registered service providers and drives their register/boot lifecycle.
 *
 * @internal
 */
final class ProviderRegistry
{
    /** @var list<ServiceProviderInterface> */
    private array $providers = [];

    private bool $booted = false;

    /**
     * Registers a provider immediately. If the container is already booted, the
     * provider is booted straight away too.
     */
    public function add(ServiceProviderInterface $provider, Container $container): void
    {
        $provider->register($container);
        $this->providers[] = $provider;

        if ($this->booted) {
            $provider->boot($container);
        }
    }

    /**
     * Boots every registered provider once. Subsequent calls are no-ops.
     */
    public function boot(Container $container): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($container);
        }

        $this->booted = true;
    }

    public function clear(): void
    {
        $this->providers = [];
        $this->booted = false;
    }
}
