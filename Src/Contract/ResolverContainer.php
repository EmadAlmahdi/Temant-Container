<?php

declare(strict_types=1);

namespace Temant\Container\Contract;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * The narrow view of the container that the resolution layer depends on.
 *
 * Decouples {@see \Temant\Container\Resolution\Resolver} and its collaborators from the
 * concrete {@see \Temant\Container\Container}, so they can be unit-tested against a
 * lightweight fake and so the dependency direction stays one-way.
 *
 * @internal Not part of the public API. May change between minor versions.
 */
interface ResolverContainer extends PsrContainerInterface
{
    /**
     * Whether reflection-based autowiring is currently enabled.
     */
    public function hasAutowiring(): bool;

    /**
     * Whether the ID has an explicit definition, cached instance, or binding --
     * i.e. {@see get()} would succeed without falling back to autowiring.
     */
    public function isBound(string $id): bool;

    /**
     * Resolves every service registered under a tag.
     *
     * @param string $tag The tag name.
     * @return list<object> The resolved service instances.
     */
    public function tagged(string $tag): array;

    /**
     * Returns the contextual binding registered for a consumer/abstract pair, if any.
     *
     * @param string $consumer The consuming class currently being resolved.
     * @param string $abstract The abstract type/interface the consumer depends on.
     * @return string|Closure|list<string>|null A target ID, a factory closure, a list of
     *                                          IDs (for variadic parameters), or null.
     */
    public function getContextualBinding(string $consumer, string $abstract): string|Closure|array|null;
}
