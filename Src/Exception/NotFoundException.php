<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when a requested entry is not found in the container.
 *
 * Extends ContainerException to maintain the PSR-11 hierarchy:
 *   NotFoundExceptionInterface extends ContainerExceptionInterface
 *   NotFoundException extends ContainerException
 *
 * This ensures `catch (ContainerException $e)` also catches not-found errors.
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    /**
     * Creates an exception for a missing entry in the container.
     *
     * @param string $id The identifier of the missing entry.
     * @return self
     */
    public static function forEntry(string $id): self
    {
        return new self("No entry found in the container for identifier: {$id}");
    }
}
