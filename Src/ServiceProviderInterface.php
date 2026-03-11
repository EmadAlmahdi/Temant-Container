<?php

declare(strict_types=1);

namespace Temant\Container;

/**
 * Interface for service providers that register and bootstrap services.
 *
 * Service providers encapsulate related service registrations, keeping
 * container configuration organized and modular.
 *
 * The lifecycle is:
 *   1. {@see register()} is called immediately when the provider is added to the container.
 *      Use this to bind services, factories, aliases, and tags.
 *   2. {@see boot()} is called after all providers have been registered.
 *      Use this for logic that depends on other services being available.
 */
interface ServiceProviderInterface
{
    /**
     * Register services into the container.
     *
     * Called immediately when the provider is added via {@see Container::register()}.
     * Only bind definitions here; do not resolve services.
     *
     * @param Container $container The container instance.
     * @return void
     */
    public function register(Container $container): void;

    /**
     * Boot services after all providers have been registered.
     *
     * Called by {@see Container::boot()}. Safe to resolve services here.
     *
     * @param Container $container The container instance.
     * @return void
     */
    public function boot(Container $container): void;
}
