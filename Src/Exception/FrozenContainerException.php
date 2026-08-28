<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

/**
 * Thrown when a mutating operation is attempted on a frozen container.
 *
 * Extends {@see ContainerException} so existing `catch (ContainerException $e)`
 * blocks keep working after the upgrade to v3.
 */
final class FrozenContainerException extends ContainerException
{
    /**
     * Creates an exception describing the rejected mutation.
     *
     * @param string $operation The mutating operation that was attempted (e.g. "set", "bind").
     * @return self
     */
    public static function forOperation(string $operation): self
    {
        return new self("Cannot {$operation}(): the container is frozen and no longer accepts modifications.");
    }
}
