<?php

declare(strict_types=1);

namespace Temant\Container\Attribute;

use Attribute;

/**
 * Marks a constructor or callable parameter for explicit resolution by container ID.
 *
 * The container resolves the parameter by calling {@see \Psr\Container\ContainerInterface::get()}
 * with the given identifier, bypassing type-based autowiring. Useful when a parameter's
 * type is ambiguous, when several implementations of an interface are registered, or when
 * the value is keyed by a plain string ID rather than a class name.
 *
 * ```php
 * final class ReportService
 * {
 *     public function __construct(
 *         #[Inject(RedisCache::class)] private CacheInterface $cache,
 *         #[Inject('config.report.dsn')] private string $dsn,
 *     ) {}
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Inject
{
    /**
     * @param string $id The container identifier to resolve this parameter from.
     */
    public function __construct(
        public readonly string $id,
    ) {
    }
}
